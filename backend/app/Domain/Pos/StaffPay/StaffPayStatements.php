<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Shifts\CashMovementType;
use App\Domain\Pos\Shifts\ShiftService;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\StaffPayRule;
use App\Models\StaffPayStatement;
use App\Models\StaffPayStatementLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-23: a branch's pay statements ("Hisob-kitob"). One per pay period (the branch's PayPeriod around a chosen
 * date); periods of a branch never overlap.
 *
 *  - draft: computed from the rules and the period's work and sales (StaffPayMetrics, StaffPayCalculator), and
 *    recomputed on demand; a line per staff member with a rule and any work, sales or events in the period (somebody
 *    who worked without a rule is a warning, as are sessions left open); a manual adjustment with a reason survives a
 *    recompute;
 *  - close: recomputed one last time and frozen (the components and metrics are kept as they were);
 *  - reopen (to a draft, with a reason) while no line is paid;
 *  - a closed line is paid once: from an open till shift of the branch (a `wage` cash movement, category payroll, on
 *    that shift, through ShiftService: the drawer must hold it; POST with an Idempotency-Key), or marked paid another
 *    way (bank transfer, card); when every line with money to pay is paid the statement is `paid`.
 *
 * payable = gross − advances (wage money given from the till in the period) − tips_paid + adjustment. Review P6 wave 2
 * (S1/C1): `tips_paid` is the tips given to the person from the till in the period (wage movements of category tips),
 * taken off only when the line pays the `tips` part (the tips are in its gross); on a line without it they were the
 * guest's tips, not a wage, and stay 0. Every action is audited.
 */
final class StaffPayStatements
{
    public function __construct(
        private readonly StaffPaySettings $settings,
        private readonly StaffPayRules $rules,
        private readonly StaffPayMetrics $metrics,
        private readonly StaffPayCalculator $calculator,
        private readonly ShiftService $shifts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * A draft for the branch's pay period holding $date.
     *
     * @throws PosActionException 409 statement_exists | statement_overlaps
     */
    public function create(Branch $branch, string $date, User $actor): StaffPayStatement
    {
        $period = $this->settings->period($branch);
        [$from, $to] = $period->range($date);

        $existing = StaffPayStatement::query()->where('branch_id', $branch->id)->where('period_from', '<=', $to)->where('period_to', '>=', $from)->first();
        if ($existing !== null) {
            $same = $existing->period_from->toDateString() === $from && $existing->period_to->toDateString() === $to;
            throw PosActionException::conflict(
                $same ? 'statement_exists' : 'statement_overlaps',
                $same ? 'Bu davr uchun hisob-kitob allaqachon bor.' : "Bu davr boshqa hisob-kitob davri bilan ustma-ust tushadi.",
                ['statement_uuid' => $existing->uuid],
            );
        }

        try {
            $statement = DB::transaction(function () use ($branch, $period, $from, $to, $actor) {
                $statement = StaffPayStatement::create([
                    'store_id' => $branch->store_id,
                    'branch_id' => $branch->id,
                    'period' => $period->value,
                    'period_from' => $from,
                    'period_to' => $to,
                    'status' => StatementStatus::DRAFT->value,
                    'created_by' => $actor->id,
                ]);
                $this->fill($statement, $branch);
                $this->audit->record('staff_pay.statement_created', $statement, [], [
                    'period' => $period->value, 'from' => $from, 'to' => $to, 'payable_total' => $statement->payable_total,
                ], ['branch_name' => $branch->name], ['actor' => $actor]);

                return $statement;
            });
        } catch (UniqueConstraintViolationException) {
            throw PosActionException::conflict('statement_exists', 'Bu davr uchun hisob-kitob allaqachon bor.');
        }

        return $statement;
    }

    /**
     * @throws PosActionException 409 statement_not_draft
     */
    public function recompute(StaffPayStatement $statement, User $actor): StaffPayStatement
    {
        return DB::transaction(function () use ($statement, $actor) {
            $statement = $this->locked($statement);
            $this->assertDraft($statement);
            $this->fill($statement, Branch::findOrFail($statement->branch_id));
            $this->audit->record('staff_pay.statement_recomputed', $statement, [], ['payable_total' => $statement->payable_total], [], ['actor' => $actor]);

            return $statement;
        });
    }

    /**
     * @throws PosActionException 409 statement_not_draft
     */
    public function close(StaffPayStatement $statement, User $actor): StaffPayStatement
    {
        return DB::transaction(function () use ($statement, $actor) {
            $statement = $this->locked($statement);
            $this->assertDraft($statement);
            $this->fill($statement, Branch::findOrFail($statement->branch_id));
            $statement->forceFill(['status' => StatementStatus::CLOSED->value, 'closed_at' => now(), 'closed_by' => $actor->id])->save();
            $this->settle($statement);
            $this->audit->record('staff_pay.statement_closed', $statement, ['status' => StatementStatus::DRAFT->value], [
                'status' => $statement->status->value, 'gross_total' => $statement->gross_total, 'payable_total' => $statement->payable_total,
            ], [], ['actor' => $actor]);

            return $statement;
        });
    }

    /**
     * @throws PosActionException 409 statement_not_closed | statement_has_payments
     */
    public function reopen(StaffPayStatement $statement, string $reason, User $actor): StaffPayStatement
    {
        return DB::transaction(function () use ($statement, $reason, $actor) {
            $statement = $this->locked($statement);
            if ($statement->status !== StatementStatus::CLOSED) {
                throw PosActionException::conflict('statement_not_closed', 'Faqat yopilgan hisob-kitob qayta ochiladi.');
            }
            if ($statement->lines()->whereNotNull('paid_at')->exists()) {
                throw PosActionException::conflict('statement_has_payments', "To'langan qatori bor hisob-kitob qayta ochilmaydi.");
            }
            $statement->forceFill(['status' => StatementStatus::DRAFT->value, 'closed_at' => null, 'closed_by' => null])->save();
            $this->audit->record('staff_pay.statement_reopened', $statement, ['status' => StatementStatus::CLOSED->value], ['status' => StatementStatus::DRAFT->value], ['reason' => $reason], ['actor' => $actor]);

            return $statement;
        });
    }

    /**
     * @throws PosActionException 409 statement_not_draft
     */
    public function delete(StaffPayStatement $statement, User $actor): void
    {
        DB::transaction(function () use ($statement, $actor) {
            $statement = $this->locked($statement);
            $this->assertDraft($statement);
            $this->audit->record('staff_pay.statement_deleted', $statement, [
                'from' => $statement->period_from->toDateString(), 'to' => $statement->period_to->toDateString(), 'payable_total' => $statement->payable_total,
            ], [], [], ['actor' => $actor]);
            $statement->delete();
        });
    }

    /**
     * A manual adjustment of a draft line (+ or −, so'm), with its reason.
     *
     * @throws PosActionException 409 statement_not_draft, 422 adjustment_reason_required
     */
    public function adjust(StaffPayStatementLine $line, Money $adjustment, ?string $reason, User $actor): StaffPayStatementLine
    {
        if (!$adjustment->isZero() && blank($reason)) {
            throw PosActionException::invalid('adjustment_reason_required', "Tuzatish sababini yozing.");
        }

        return DB::transaction(function () use ($line, $adjustment, $reason, $actor) {
            $statement = $this->locked(StaffPayStatement::findOrFail($line->statement_id));
            $this->assertDraft($statement);
            $line = StaffPayStatementLine::query()->lockForUpdate()->findOrFail($line->id);
            $before = ['adjustment' => $line->adjustment, 'adjustment_reason' => $line->adjustment_reason];
            $line->forceFill([
                'adjustment' => $adjustment->toString(),
                'adjustment_reason' => $adjustment->isZero() ? null : $reason,
                'payable' => self::payable($line->gross, $line->advances, $line->tips_paid, $adjustment),
            ])->save();
            $this->totals($statement);
            $this->audit->record('staff_pay.line_adjusted', $statement, $before, [
                'adjustment' => $line->adjustment, 'adjustment_reason' => $line->adjustment_reason,
            ], ['staff_name' => $line->user_name, 'user_id' => $line->user_id], ['actor' => $actor]);

            return $line;
        });
    }

    /**
     * Pays a line of a closed statement: from an open till shift of the branch (a wage cash movement, category payroll),
     * or marked paid another way.
     *
     * @throws PosActionException
     */
    public function pay(StaffPayStatementLine $line, string $method, ?PosShift $shift, ?string $note, User $actor, ?Closure $applied = null): StaffPayStatementLine
    {
        return DB::transaction(function () use ($line, $method, $shift, $note, $actor, $applied) {
            $statement = $this->locked(StaffPayStatement::findOrFail($line->statement_id));
            if ($statement->status === StatementStatus::DRAFT) {
                throw PosActionException::conflict('statement_not_closed', "Avval hisob-kitobni yoping, keyin to'lang.");
            }
            $line = StaffPayStatementLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($line->paid_at !== null) {
                throw PosActionException::conflict('line_already_paid', "Bu xodimga allaqachon to'langan.");
            }
            $payable = Money::of($line->payable);

            $movementId = null;
            if ($method === 'cash_drawer') {
                if (!$payable->isPositive()) {
                    throw PosActionException::invalid('nothing_to_pay', "To'lanadigan summa yo'q.");
                }
                if ($shift === null || (int) $shift->branch_id !== (int) $statement->branch_id) {
                    throw PosActionException::invalid('shift_not_found', 'Filialning ochiq smenasini tanlang.');
                }
                $movement = $this->shifts->addMovement(
                    $actor,
                    $shift,
                    CashMovementType::WAGE,
                    'payroll',
                    $payable,
                    trim('Ish haqi ' . $statement->period_from->format('d.m') . '–' . $statement->period_to->format('d.m.Y') . ($note !== null && $note !== '' ? ": {$note}" : '')),
                    $line->user_id !== null ? (int) $line->user_id : null,
                    null,
                    null,
                    statementPayment: true,
                );
                $movementId = $movement->id;
            }

            $line->forceFill([
                'paid_at' => now(),
                'paid_by' => $actor->id,
                'paid_method' => $method,
                'paid_note' => $note,
                'cash_movement_id' => $movementId,
            ])->save();
            $this->settle($statement);
            $this->audit->record('staff_pay.line_paid', $statement, [], [
                'user_id' => $line->user_id, 'amount' => $line->payable, 'method' => $method, 'shift_uuid' => $shift?->uuid,
            ], ['staff_name' => $line->user_name, 'note' => $note], ['actor' => $actor]);

            if ($applied) {
                $applied();
            }

            return $line;
        });
    }

    /**
     * (Re)computes the lines of a draft from the rules and the period's metrics.
     */
    private function fill(StaffPayStatement $statement, Branch $branch): void
    {
        $from = $statement->period_from->toDateString();
        $to = $statement->period_to->toDateString();
        $dates = self::dates($from, $to);
        $rules = $this->rules->forBranch($branch, $to);
        $metrics = $this->metrics->collect($branch, $from, $to);

        $staff = $this->rules->staff($branch)->get()->keyBy('id');
        $extra = array_diff(array_keys($metrics['days']), $staff->keys()->all());
        if ($extra !== []) {
            // Somebody who worked here but is now bound to another branch: still paid for this branch's days
            $staff = $staff->union(User::query()->where('store_id', $branch->store_id)->whereIn('id', $extra)
                ->whereIn('role', array_map(fn ($role) => $role->value, StaffPayRules::roles()))->get()->keyBy('id'));
        }

        $existing = $statement->lines()->get()->keyBy('user_id');
        $kept = [];
        $warnings = [];
        foreach ($staff as $user) {
            /** @var User $user */
            $personDays = $metrics['days'][$user->id] ?? [];
            $uncounted = (int) ($metrics['uncounted_sessions'][$user->id] ?? 0);
            if ($personDays === [] && $uncounted === 0) {
                continue;
            }

            $days = [];
            $anyRule = false;
            foreach ($dates as $date) {
                $rule = StaffPayRules::effective($rules, $user, $date);
                $anyRule = $anyRule || $rule !== null;
                $metric = $personDays[$date] ?? null;
                $days[] = [
                    'date' => $date,
                    'rule' => $rule !== null ? ['uuid' => (string) $rule->uuid, 'components' => $rule->components] : null,
                    'worked' => $metric['worked'] ?? false,
                    'minutes' => $metric['minutes'] ?? 0,
                    'sales' => $metric['sales'] ?? Money::zero(),
                    'service_charge' => $metric['service_charge'] ?? Money::zero(),
                    'events' => $metric['events'] ?? [],
                    'tips' => $metric['tips'] ?? Money::zero(),
                ];
            }
            if ($uncounted > 0) {
                $warnings[] = ['type' => 'open_sessions', 'user_id' => (int) $user->id, 'name' => $user->name, 'count' => $uncounted];
            }
            if (!$anyRule) {
                $warnings[] = ['type' => 'no_rule', 'user_id' => (int) $user->id, 'name' => $user->name];

                continue;
            }

            $result = $this->calculator->calculate($days, count($dates));
            $advances = $metrics['advances'][$user->id] ?? Money::zero();
            $paysTips = in_array(PayComponentType::TIPS->value, array_column($result['components'], 'type'), true);
            $tipsPaid = $paysTips ? ($metrics['tip_payouts'][$user->id] ?? Money::zero()) : Money::zero();
            $line = $existing->get($user->id) ?? new StaffPayStatementLine(['statement_id' => $statement->id, 'user_id' => $user->id]);
            $adjustment = Money::of($line->adjustment ?? 0);
            $line->forceFill([
                'user_name' => $user->name,
                'role' => $user->role->value,
                'metrics' => self::summary($days, $personDays, $uncounted, $rules, $user),
                'components' => $result['components'],
                'gross' => $result['gross'],
                'advances' => $advances->toString(),
                'tips_paid' => $tipsPaid->toString(),
                'adjustment' => $adjustment->toString(),
                'payable' => self::payable($result['gross'], $advances, $tipsPaid, $adjustment),
            ])->save();
            $kept[] = $line->id;
        }

        $statement->lines()->whereNotIn('id', $kept)->delete();
        $statement->forceFill(['warnings' => $warnings, 'computed_at' => now(), 'version' => (int) $statement->version + 1]);
        $this->totals($statement);
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @param  array<string, array<string, mixed>>  $personDays
     * @param  Collection<int, StaffPayRule>  $rules
     * @return array<string, mixed>
     */
    private static function summary(array $days, array $personDays, int $uncounted, Collection $rules, User $user): array
    {
        $minutes = array_sum(array_column($days, 'minutes'));
        $events = 0;
        foreach ($days as $day) {
            $events += array_sum($day['events']);
        }

        return [
            'shifts' => count(array_filter($days, fn ($day) => $day['worked'])),
            'minutes' => $minutes,
            'bills' => array_sum(array_map(fn ($day) => (int) ($day['bills'] ?? 0), $personDays)),
            'sales' => Money::sum(...array_column($days, 'sales'))->toString(),
            'service_charge' => Money::sum(...array_column($days, 'service_charge'))->toString(),
            'events' => $events,
            'tips' => Money::sum(...array_column($days, 'tips'))->toString(),
            'uncounted_sessions' => $uncounted,
            'rules' => array_values(array_unique(array_filter(array_map(fn ($day) => $day['rule']['uuid'] ?? null, $days)))),
            'own_rule' => $rules->contains(fn (StaffPayRule $rule) => $rule->subject === 'user:' . $user->id),
        ];
    }

    /**
     * gross − advances − tips paid from the till + adjustment.
     */
    private static function payable(Money|string $gross, Money|string $advances, Money|string $tipsPaid, Money|string $adjustment): string
    {
        return Money::of($gross)->minus($advances)->minus($tipsPaid)->plus($adjustment)->toString();
    }

    private function totals(StaffPayStatement $statement): void
    {
        $lines = $statement->lines()->get(['gross', 'payable']);
        $statement->forceFill([
            'gross_total' => Money::sum(...$lines->pluck('gross')->all())->toString(),
            'payable_total' => Money::sum(...$lines->pluck('payable')->all())->toString(),
        ])->save();
    }

    /**
     * paid_total, and `paid` once every line with money to pay is paid.
     */
    private function settle(StaffPayStatement $statement): void
    {
        $lines = $statement->lines()->get(['payable', 'paid_at']);
        $paid = Money::sum(...$lines->whereNotNull('paid_at')->pluck('payable')->all());
        $open = $lines->filter(fn (StaffPayStatementLine $line) => $line->paid_at === null && Money::of($line->payable)->isPositive())->count();
        $statement->forceFill([
            'paid_total' => $paid->toString(),
            'status' => $open === 0 && $lines->whereNotNull('paid_at')->isNotEmpty() ? StatementStatus::PAID->value : $statement->status->value,
        ])->save();
    }

    private function locked(StaffPayStatement $statement): StaffPayStatement
    {
        return StaffPayStatement::query()->lockForUpdate()->findOrFail($statement->id);
    }

    /**
     * @throws PosActionException
     */
    private function assertDraft(StaffPayStatement $statement): void
    {
        if ($statement->status !== StatementStatus::DRAFT) {
            throw PosActionException::conflict('statement_not_draft', "Hisob-kitob yopilgan: o'zgartirish uchun avval qayta oching.");
        }
    }

    /**
     * @return list<string>
     */
    private static function dates(string $from, string $to): array
    {
        $dates = [];
        for ($day = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC'); $day->format('Y-m-d') <= $to; $day = $day->addDay()) {
            $dates[] = $day->format('Y-m-d');
        }

        return $dates;
    }
}
