<?php

namespace App\Domain\Pos\Tips;

use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * P6-22: the tips of a shift on the X/Z report, on their own lines (never in `sales` or `by_tender`): every tip row
 * of the shift's payments, corrections included (a correction belongs to the shift of the tip it corrects). The
 * cash ones stay in the drawer (cash.tips in the expected cash) until paid out (a `wage` movement of category
 * `tips`: cash.tip_payouts).
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
     * The cash tips of the shift now (in its drawer until paid out).
     */
    public function cash(PosShift $shift): Money
    {
        return Money::sum('0', ...PosTip::where('shift_id', $shift->id)->where('method', TipMethod::CASH->value)->pluck('amount')->all());
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
