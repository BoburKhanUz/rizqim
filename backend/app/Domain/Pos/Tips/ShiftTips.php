<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Shifts\CashMovementType;
use App\Models\CashMovement;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\PosTipDistributionLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * P6-22: the tips of a shift on the X/Z report, on their own lines (never in `sales` or `by_tender`): every tip row
 * of the shift's payments, corrections included (a correction belongs to the shift of the tip it corrects). The
 * cash ones stay in the drawer (cash.tips in the expected cash) until paid out (a `wage` movement of category
 * `tips`: cash.tip_payouts). Review P6 wave 2 (S6): the drawer holds the cash tips as they were taken — a correction
 * moves a tip between people (or changes what it gives) but never the cash in the drawer.
 *
 * Review P6 wave 2 (S2/S7), paying tips out of the till: never more than the shift's cash tips less the tips already
 * paid out of it (`payable`), and never more than the person is owed in cash on the shift's business day (`owed`):
 * their personal cash tips of that day at the branch (corrections included) plus their share of a closed pool split of
 * that day (a day or a shift split; an open one is not owed yet), less the tips paid to them from the branch's tills
 * that day. Pool shares the day did not pay out are paid with the pay statement (its tips part) instead.
 */
final class ShiftTips
{
    public const SHAPE = 'array{count: int, amount: string, by_method: list<array{method: string, count: int, amount: string}>, personal: string, pool: string, unassigned: string, by_staff: list<array{staff_id: int, name: string|null, amount: string}>, corrections: array{count: int, amount: string}}';

    /**
     * @return array{count: int, amount: string, by_method: list<array{method: string, count: int, amount: string}>, personal: string, pool: string, unassigned: string, by_staff: list<array{staff_id: int, name: string|null, amount: string}>, corrections: array{count: int, amount: string}}
     */
    public function forShift(PosShift $shift): array
    {
        return self::summarize(PosTip::where('shift_id', $shift->id)->orderBy('id')->get());
    }

    /**
     * The cash tips the shift took (in its drawer until paid out): the tips as taken, a correction never moves cash.
     */
    public function cash(PosShift $shift): Money
    {
        return Money::sum('0', ...PosTip::where('shift_id', $shift->id)->where('kind', TipKind::TIP->value)->where('method', TipMethod::CASH->value)->pluck('amount')->all());
    }

    /**
     * The tips paid out of the shift's drawer (`wage` movements of category tips).
     */
    public function paidOut(PosShift $shift): Money
    {
        return Money::sum('0', ...CashMovement::where('shift_id', $shift->id)->where('type', CashMovementType::WAGE->value)
            ->where('category', CashMovementType::TIPS_CATEGORY)->pluck('amount')->all());
    }

    /**
     * What may still be paid out of the shift's drawer as tips: its cash tips less the tips paid out of it.
     */
    public function payable(PosShift $shift): Money
    {
        return Money::max(Money::zero(), $this->cash($shift)->minus($this->paidOut($shift)));
    }

    /**
     * What the person is owed in cash on a business day of the branch: their personal cash tips (corrections
     * included) and their share of the day's closed pool splits, less the tips paid to them from the branch's tills.
     */
    public function owed(int $branchId, string $date, int $staffId): Money
    {
        $personal = PosTip::where('branch_id', $branchId)->whereDate('business_date', $date)->where('mode', TipMode::PERSONAL->value)
            ->where('method', TipMethod::CASH->value)->where('staff_id', $staffId)->pluck('amount')->all();
        $pool = PosTipDistributionLine::query()
            ->join('pos_tip_distributions', 'pos_tip_distributions.id', '=', 'pos_tip_distribution_lines.distribution_id')
            ->where('pos_tip_distributions.branch_id', $branchId)->whereDate('pos_tip_distributions.business_date', $date)
            ->where('pos_tip_distributions.status', TipDistributionStatus::CLOSED->value)
            ->where('pos_tip_distribution_lines.staff_id', $staffId)->pluck('pos_tip_distribution_lines.amount')->all();
        $paid = CashMovement::query()->join('pos_shifts', 'pos_shifts.id', '=', 'cash_movements.shift_id')
            ->where('cash_movements.branch_id', $branchId)->whereDate('pos_shifts.business_date', $date)
            ->where('cash_movements.type', CashMovementType::WAGE->value)->where('cash_movements.category', CashMovementType::TIPS_CATEGORY)
            ->where('cash_movements.employee_id', $staffId)->pluck('cash_movements.amount')->all();

        return Money::max(Money::zero(), Money::sum('0', ...$personal, ...$pool)->minus(Money::sum('0', ...$paid)));
    }

    /**
     * @param  Collection<int, PosTip>  $rows
     * @return array{count: int, amount: string, by_method: list<array{method: string, count: int, amount: string}>, personal: string, pool: string, unassigned: string, by_staff: list<array{staff_id: int, name: string|null, amount: string}>, corrections: array{count: int, amount: string}}
     */
    public static function summarize(Collection $rows): array
    {
        $sum = fn (Collection $rows) => Money::sum('0', ...$rows->pluck('amount')->all())->toString();
        $tips = $rows->filter(fn (PosTip $row) => $row->kind === TipKind::TIP);
        $corrections = $rows->filter(fn (PosTip $row) => $row->kind === TipKind::CORRECTION);
        $personal = $rows->filter(fn (PosTip $row) => $row->mode === TipMode::PERSONAL);

        $byMethod = [];
        foreach (TipMethod::cases() as $method) {
            $of = $rows->filter(fn (PosTip $row) => $row->method === $method);
            $byMethod[] = [
                'method' => $method->value,
                'count' => $of->filter(fn (PosTip $row) => $row->kind === TipKind::TIP)->count(),
                'amount' => $sum($of),
            ];
        }

        $names = User::whereIn('id', $personal->pluck('staff_id')->filter()->unique())->pluck('name', 'id');
        $byStaff = $personal->filter(fn (PosTip $row) => $row->staff_id !== null)
            ->groupBy(fn (PosTip $row) => (int) $row->staff_id)
            ->map(fn (Collection $rows, int $staffId) => ['staff_id' => $staffId, 'name' => $names[$staffId] ?? null, 'amount' => $sum($rows)])
            ->filter(fn (array $row) => !Money::of($row['amount'])->isZero())
            ->sortKeys()->values()->all();

        return [
            'count' => $tips->count(),
            'amount' => $sum($rows),
            'by_method' => $byMethod,
            'personal' => $sum($personal),
            'pool' => $sum($rows->filter(fn (PosTip $row) => $row->mode === TipMode::POOL)),
            'unassigned' => $sum($personal->filter(fn (PosTip $row) => $row->staff_id === null)),
            'by_staff' => $byStaff,
            'corrections' => ['count' => $corrections->pluck('corrects_id')->unique()->count(), 'amount' => $sum($corrections)],
        ];
    }
}
