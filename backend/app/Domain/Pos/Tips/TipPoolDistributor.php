<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\StaffPay\SalesAttribution;
use App\Domain\Pos\StaffPay\StaffPaySettings;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\PosTipDistribution;
use App\Models\PosTipDistributionLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * P6-22 (owner decision 29.10.2026): the split of a branch's tip pool — the `pool` tips of a business day (or one
 * shift) — among the people who worked in it, by the branch's rule (TipSettings), computed and stored
 * (pos_tip_distributions and its lines), recomputed on request until a manager closes it.
 *
 * The period follows the branch's `pool_period`: `day` splits a business day (no shift given), `shift` one till shift
 * (required). A day split and shift splits of the same date never both exist (whichever comes first, the other is
 * refused: 409 tips_pool_period_conflict), so a reader adding up the splits never counts a tip twice. Computes of a
 * branch's date run one at a time; two first computes racing to the unique row give the loser a 409
 * (tips_distribution_busy), never a 500.
 *
 * People: those of the settings' `roles` who worked in the period (TipWorkTime), or the manager's own list
 * (`participants`: staff ids with optional minutes) — only people of the branch (or of the whole store) whose role is
 * one of those `roles`, and never the one computing it unless that is the owner (a manager does not put himself in
 * the pool: 403 tips_pool_self). The audit row keeps the list and each share. Basis per person: `equal` 1; `hours` minutes worked; `sales` the
 * paid bills of the orders they served (tiyin; whose a bill is follows the staff pay `sales_attribution`: the waiter at
 * payment time, order_bills.waiter_id, by default, else orders.waiter_id); `role_weights` their role's weight. When every
 * basis is 0 (nobody clocked any time, nobody sold) the pool is split equally instead (the stored `rule` says
 * `equal`).
 *
 * Rounding to the so'm (the remainder rule): the pool's whole so'm W are shared in proportion, each share rounded
 * down; the so'm left over (fewer than the people) go one each to the largest remainders (ties: the larger basis,
 * then the lower staff id); the pool's tiyin below a so'm, if any, go to the largest share (ties: the lower staff
 * id). The lines always add up to the pool exactly. A split whose pool nobody takes (no people) is never closed (409
 * tips_distribution_unassigned). Staff pay (PosTipsStaffTips) counts a split's lines only once it is closed.
 */
final class TipPoolDistributor
{
    public const VIEW = 'array{uuid: string, branch_id: int, business_date: string, shift_uuid: string|null, rule: string, roles: list<string>, role_weights: array<string, string>, pool_amount: string, tips_count: int, distributed: string, status: string, computed_at: string|null, closed_at: string|null, closed_by: array{id: int, name: string|null}|null, manual_participants: bool, lines: list<array{staff_id: int|null, name: string|null, role: string|null, basis: string, amount: string}>}';

    public function __construct(
        private readonly TipSettings $settings,
        private readonly TipWorkTime $workTime,
        private readonly AuditLogger $audit,
        private readonly StaffPaySettings $staffPay,
    ) {}

    /**
     * The pool tips (with their corrections) of the period: their sum and how many tips.
     *
     * @return array{0: Money, 1: int}
     */
    public function pool(Branch $branch, string $date, ?PosShift $shift): array
    {
        $rows = PosTip::where('branch_id', $branch->id)->whereDate('business_date', $date)->where('mode', TipMode::POOL->value)
            ->when($shift !== null, fn ($query) => $query->where('shift_id', $shift->id))
            ->get(['amount', 'kind', 'corrects_id', 'id']);

        $tips = $rows->map(fn (PosTip $row) => $row->corrects_id ?? $row->id)->unique()->count();

        return [Money::sum('0', ...$rows->pluck('amount')->all()), $tips];
    }

    /**
     * Computes (or recomputes) and stores the split of the period. `$participants`: the manager's own list
     * ([{staff_id, minutes?}]; null: who worked, by the settings' roles).
     *
     * @param  list<array{staff_id: int, minutes?: int|null}>|null  $participants
     *
     * @throws PosActionException 409 tips_distribution_closed, tips_pool_period_conflict, tips_distribution_busy;
     *                             422 staff_not_found, staff_not_in_pool, tips_pool_period_day, tips_pool_period_shift;
     *                             403 tips_pool_self
     */
    public function compute(Branch $branch, string $date, ?PosShift $shift, ?array $participants, User $actor): PosTipDistribution
    {
        $row = $this->settings->row($branch);
        $rule = $row->pool_rule ?? TipPoolRule::EQUAL;
        $roles = $this->settings->roles($row);
        $weights = $this->settings->weights($row);
        $period = $row->pool_period ?? TipPoolPeriod::DAY;
        if ($period === TipPoolPeriod::DAY && $shift !== null) {
            throw PosActionException::invalid('tips_pool_period_day', "Qozon biznes kun bo'yicha bo'linadi: smenani tanlamang.");
        }
        if ($period === TipPoolPeriod::SHIFT && $shift === null) {
            throw PosActionException::invalid('tips_pool_period_shift', "Qozon smena bo'yicha bo'linadi: smenani tanlang.");
        }

        try {
            return DB::transaction(fn () => $this->store($branch, $date, $shift, $participants, $actor, $rule, $roles, $weights));
        } catch (UniqueConstraintViolationException) {
            throw PosActionException::conflict('tips_distribution_busy', "Bu bo'linish hozirgina boshqa joydan hisoblandi: ro'yxatni yangilab, qayta urinib ko'ring.");
        }
    }

    /**
     * The body of compute(), in its transaction.
     *
     * @param  list<array{staff_id: int, minutes?: int|null}>|null  $participants
     * @param  list<string>  $roles
     * @param  array<string, string>  $weights
     *
     * @throws PosActionException
     */
    private function store(Branch $branch, string $date, ?PosShift $shift, ?array $participants, User $actor, TipPoolRule $rule, array $roles, array $weights): PosTipDistribution
    {
        // One compute of a branch's date at a time (the first one creates the row, nothing to lock before it)
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['pos_tip_pool:' . $branch->id . ':' . $date]);
        }
        $distribution = PosTipDistribution::where('branch_id', $branch->id)->whereDate('business_date', $date)
            ->where('shift_id', $shift?->id)->lockForUpdate()->first();
        if ($distribution?->status === TipDistributionStatus::CLOSED) {
            throw PosActionException::conflict('tips_distribution_closed', "Bu choy puli bo'linishi yopilgan: qayta hisoblab bo'lmaydi.");
        }
        // A day split and the shift splits of the same date exclude each other
        $other = PosTipDistribution::where('branch_id', $branch->id)->whereDate('business_date', $date)
            ->when($shift === null, fn ($query) => $query->whereNotNull('shift_id'), fn ($query) => $query->whereNull('shift_id'))->exists();
        if ($other) {
            throw PosActionException::conflict('tips_pool_period_conflict', $shift === null
                ? "Bu kunning qozoni smenalar bo'yicha bo'lingan: kun bo'yicha bo'lib bo'lmaydi."
                : "Bu kunning qozoni butun kun bo'yicha bo'lingan: smena bo'yicha bo'lib bo'lmaydi.");
        }

        $minutes = $this->workTime->minutes($branch, $date, $shift);
        $people = $this->people($branch, $participants, $minutes, $roles, $actor);

        $bases = [];
        $used = $rule;
        foreach ($people as $id => $person) {
            $bases[$id] = match ($rule) {
                TipPoolRule::EQUAL => BigInteger::one(),
                TipPoolRule::HOURS => BigInteger::of($person['minutes']),
                TipPoolRule::SALES => BigInteger::zero(), // below
                TipPoolRule::ROLE_WEIGHTS => BigInteger::of(Money::of($weights[$person['role']] ?? '1')->toTiyin()),
            };
        }
        if ($rule === TipPoolRule::SALES) {
            foreach ($this->sales($branch, $date, $shift, array_keys($people)) as $id => $tiyin) {
                $bases[$id] = BigInteger::of($tiyin);
            }
        }
        if ($people !== [] && array_reduce($bases, fn (bool $zero, BigInteger $basis) => $zero && $basis->isZero(), true)) {
            $used = TipPoolRule::EQUAL;
            $bases = array_map(fn () => BigInteger::one(), $bases);
        }

        [$pool, $count] = $this->pool($branch, $date, $shift);
        $shares = self::split($pool, $bases);

        $distribution ??= new PosTipDistribution([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'business_date' => $date,
            'shift_id' => $shift?->id,
        ]);
        $distribution->forceFill([
            'rule' => $used,
            'roles' => $roles,
            'role_weights' => $weights,
            'participants' => $participants !== null ? array_map(fn (array $person) => ['staff_id' => (int) $person['staff_id'], 'minutes' => isset($person['minutes']) ? (int) $person['minutes'] : null], $participants) : null,
            'pool_amount' => $pool->toString(),
            'tips_count' => $count,
            'status' => TipDistributionStatus::OPEN,
            'computed_at' => now(),
            'computed_by' => $actor->id,
        ])->save();

        PosTipDistributionLine::where('distribution_id', $distribution->id)->delete();
        foreach ($people as $id => $person) {
            PosTipDistributionLine::create([
                'distribution_id' => $distribution->id,
                'staff_id' => $id,
                'role' => $person['role'],
                'basis' => $used === TipPoolRule::SALES ? Money::fromTiyin((string) $bases[$id])->toString()
                    : ($used === TipPoolRule::ROLE_WEIGHTS ? Money::fromTiyin((string) $bases[$id])->toString() : (string) $bases[$id]),
                'amount' => ($shares[$id] ?? Money::zero())->toString(),
            ]);
        }

        $this->audit->record('pos.tips_distribution_computed', $distribution, [], [], [
            'business_date' => $date,
            'shift_id' => $shift?->id,
            'rule' => $used->value,
            'pool' => $pool->toString(),
            'people' => count($people),
            'manual' => $participants !== null,
            'participants' => array_keys($people),
            'shares' => array_map(fn (Money $share) => $share->toString(), $shares),
        ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $distribution->refresh();
    }

    /**
     * Freezes the split: no more recomputing, and the tips of the period take no more corrections.
     *
     * @throws PosActionException 409 tips_distribution_closed, tips_distribution_stale, tips_distribution_unassigned
     */
    public function close(PosTipDistribution $distribution, User $actor): PosTipDistribution
    {
        return DB::transaction(function () use ($distribution, $actor) {
            $distribution = PosTipDistribution::whereKey($distribution->id)->lockForUpdate()->firstOrFail();
            if ($distribution->status === TipDistributionStatus::CLOSED) {
                throw PosActionException::conflict('tips_distribution_closed', "Bu choy puli bo'linishi allaqachon yopilgan.");
            }
            $branch = Branch::findOrFail($distribution->branch_id);
            $shift = $distribution->shift_id !== null ? PosShift::find($distribution->shift_id) : null;
            [$pool] = $this->pool($branch, $distribution->business_date->toDateString(), $shift);
            if (!$pool->equals($distribution->pool_amount)) {
                throw PosActionException::conflict('tips_distribution_stale', "Qozon hisoblangandan keyin o'zgardi: avval qayta hisoblang.", ['pool_amount' => $pool->toString()]);
            }
            $distributed = Money::sum('0', ...PosTipDistributionLine::where('distribution_id', $distribution->id)->pluck('amount')->all());
            if ($pool->isPositive() && !$distributed->equals($pool)) {
                throw PosActionException::conflict('tips_distribution_unassigned', "Qozonni oladigan xodim yo'q: ro'yxatni kiriting yoki rollarni tekshirib, qayta hisoblang.", ['pool_amount' => $pool->toString(), 'distributed' => $distributed->toString()]);
            }

            $distribution->forceFill(['status' => TipDistributionStatus::CLOSED, 'closed_at' => now(), 'closed_by' => $actor->id])->save();
            $this->audit->record('pos.tips_distribution_closed', $distribution, [], [], [
                'business_date' => $distribution->business_date->toDateString(),
                'pool' => Money::of($distribution->pool_amount)->toString(),
            ], ['actor' => $actor, 'store_id' => (int) $distribution->store_id, 'branch_id' => (int) $distribution->branch_id]);

            return $distribution;
        });
    }

    /**
     * Splits `$pool` in proportion to `$bases` (person => basis), to the so'm, by the remainder rule above.
     *
     * @param  array<int, BigInteger>  $bases
     * @return array<int, Money>
     */
    public static function split(Money $pool, array $bases): array
    {
        if ($bases === []) {
            return [];
        }
        $total = array_reduce($bases, fn (BigInteger $sum, BigInteger $basis) => $sum->plus($basis), BigInteger::zero());
        $tiyin = $pool->toTiyin();
        if ($tiyin <= 0 || $total->isZero()) {
            return array_map(fn () => Money::zero(), $bases);
        }

        $whole = BigInteger::of(intdiv($tiyin, 100));
        $cents = $tiyin % 100;
        $floors = [];
        $remainders = [];
        $given = BigInteger::zero();
        foreach ($bases as $id => $basis) {
            $product = $whole->multipliedBy($basis);
            $floors[$id] = $product->quotient($total);
            $remainders[$id] = $product->remainder($total);
            $given = $given->plus($floors[$id]);
        }

        $order = array_keys($bases);
        usort($order, fn (int $a, int $b) => $remainders[$b]->compareTo($remainders[$a]) ?: ($bases[$b]->compareTo($bases[$a]) ?: $a <=> $b));
        $left = $whole->minus($given)->toInt();
        foreach ($order as $i => $id) {
            if ($i >= $left) {
                break;
            }
            $floors[$id] = $floors[$id]->plus(1);
        }

        $shares = array_map(fn (BigInteger $soms) => Money::fromTiyin((string) $soms->multipliedBy(100)), $floors);
        if ($cents > 0) {
            $ids = array_keys($shares);
            usort($ids, fn (int $a, int $b) => [$shares[$b]->toTiyin(), $a] <=> [$shares[$a]->toTiyin(), $b]);
            $shares[$ids[0]] = $shares[$ids[0]]->plus(Money::fromTiyin($cents));
        }

        return $shares;
    }

    /**
     * The people of the split: id => {role, minutes}.
     *
     * @param  list<array{staff_id: int, minutes?: int|null}>|null  $participants
     * @param  array<int, int>  $minutes
     * @param  list<string>  $roles
     * @return array<int, array{role: string|null, minutes: int}>
     *
     * @throws PosActionException 422 staff_not_found, staff_not_in_pool; 403 tips_pool_self
     */
    private function people(Branch $branch, ?array $participants, array $minutes, array $roles, User $actor): array
    {
        if ($participants !== null) {
            $ids = array_values(array_unique(array_map(fn (array $person) => (int) $person['staff_id'], $participants)));
            if (in_array((int) $actor->id, $ids, true) && $actor->role !== UserRole::OWNER) {
                throw PosActionException::forbidden('tips_pool_self', "O'zingizni qozon ro'yxatiga qo'sha olmaysiz: buni do'kon egasi qiladi.");
            }
            $users = User::whereIn('id', $ids)->where('store_id', $branch->store_id)
                ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::terminalRoles()))
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                ->get(['id', 'role'])->keyBy('id');
            if ($users->count() !== count($ids)) {
                throw PosActionException::invalid('staff_not_found', "Ro'yxatdagi xodim topilmadi.", ['staff_ids' => array_values(array_diff($ids, $users->keys()->all()))]);
            }
            $outside = $users->filter(fn (User $user) => !in_array($user->role->value, $roles, true))->keys()->sort()->values()->all();
            if ($outside !== []) {
                throw PosActionException::invalid('staff_not_in_pool', "Ro'yxatdagi xodimning roli qozonda qatnashmaydi (choy puli sozlamasidagi rollar).", ['staff_ids' => array_map('intval', $outside)]);
            }
            $people = [];
            foreach ($participants as $person) {
                $id = (int) $person['staff_id'];
                $people[$id] = [
                    'role' => $users[$id]->role->value,
                    'minutes' => isset($person['minutes']) ? max(0, (int) $person['minutes']) : ($minutes[$id] ?? 0),
                ];
            }
            ksort($people);

            return $people;
        }

        $users = User::whereIn('id', array_keys($minutes))->where('store_id', $branch->store_id)->whereIn('role', $roles)->get(['id', 'role']);
        $people = [];
        foreach ($users as $user) {
            $people[(int) $user->id] = ['role' => $user->role->value, 'minutes' => $minutes[(int) $user->id] ?? 0];
        }
        ksort($people);

        return $people;
    }

    /**
     * The paid bills (tiyin) of the orders each person served in the period.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function sales(Branch $branch, string $date, ?PosShift $shift, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $shiftIds = $shift !== null ? [$shift->id] : PosShift::where('branch_id', $branch->id)->whereDate('business_date', $date)->pluck('id')->all();
        // The same person as in the pay statements (StaffPayMetrics): the waiter at payment time by default
        $waiter = $this->staffPay->salesAttribution($branch) === SalesAttribution::AT_PAYMENT
            ? 'coalesce(order_bills.waiter_id, orders.waiter_id)'
            : 'orders.waiter_id';
        $rows = DB::table('order_bills')->join('orders', 'orders.id', '=', 'order_bills.order_id')
            ->whereIn('order_bills.shift_id', $shiftIds)->where('order_bills.status', BillStatus::PAID->value)
            ->whereIn(DB::raw($waiter), $ids)
            ->groupBy(DB::raw($waiter))->selectRaw("{$waiter} as staff_id, sum(order_bills.total) as total")->get();

        $sales = [];
        foreach ($rows as $row) {
            $sales[(int) $row->staff_id] = Money::of((string) $row->total)->toTiyin();
        }

        return $sales;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PosTipDistribution $distribution): array
    {
        $lines = PosTipDistributionLine::where('distribution_id', $distribution->id)->orderBy('staff_id')->get();
        $names = User::whereIn('id', $lines->pluck('staff_id')->push($distribution->closed_by)->filter())->pluck('name', 'id');

        return [
            'uuid' => $distribution->uuid,
            'branch_id' => (int) $distribution->branch_id,
            'business_date' => $distribution->business_date->toDateString(),
            'shift_uuid' => $distribution->shift_id !== null ? PosShift::whereKey($distribution->shift_id)->value('uuid') : null,
            'rule' => $distribution->rule->value,
            'roles' => $distribution->roles ?? [],
            'role_weights' => $distribution->role_weights ?? [],
            'pool_amount' => Money::of($distribution->pool_amount)->toString(),
            'tips_count' => (int) $distribution->tips_count,
            'distributed' => Money::sum('0', ...$lines->pluck('amount')->all())->toString(),
            'status' => $distribution->status->value,
            'computed_at' => $distribution->computed_at?->toIso8601String(),
            'closed_at' => $distribution->closed_at?->toIso8601String(),
            'closed_by' => $distribution->closed_by !== null ? ['id' => (int) $distribution->closed_by, 'name' => $names[$distribution->closed_by] ?? null] : null,
            'manual_participants' => $distribution->participants !== null,
            'lines' => $lines->map(fn (PosTipDistributionLine $line) => [
                'staff_id' => $line->staff_id !== null ? (int) $line->staff_id : null,
                'name' => $line->staff_id !== null ? ($names[$line->staff_id] ?? null) : null,
                'role' => $line->role,
                'basis' => Money::of($line->basis)->toString(),
                'amount' => Money::of($line->amount)->toString(),
            ])->values()->all(),
        ];
    }
}
