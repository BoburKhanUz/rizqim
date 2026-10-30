<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Tips\TipDistributionStatus;
use App\Models\Branch;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * P6-23: the tips a wage rule's `tips` component pays, read from P6-22's ledger (App\Domain\Pos\Tips): personal tips
 * are the person's rows of mode `personal` (tips and their corrections, so a corrected tip counts as corrected) on
 * their business day; pool tips are the person's lines of the branch's CLOSED pool splits (per business day or
 * shift): an open split may still be recomputed, so a pay statement sees pool tips once the split is closed.
 */
final class PosTipsStaffTips implements StaffTipsSource
{
    public function tipsByDay(Branch $branch, string $from, string $to): array
    {
        $out = [];
        $add = function (int $userId, string $date, mixed $amount) use (&$out) {
            $out[$userId][$date] = Money::of($out[$userId][$date] ?? 0)->plus($amount)->toString();
        };

        $personal = DB::table('pos_tips')
            ->where('branch_id', $branch->id)
            ->where('mode', 'personal')
            ->whereNotNull('staff_id')
            ->whereBetween('business_date', [$from, $to])
            ->groupBy('staff_id', 'business_date')
            ->selectRaw('staff_id, business_date, sum(amount) as amount')
            ->get();
        foreach ($personal as $row) {
            $add((int) $row->staff_id, substr((string) $row->business_date, 0, 10), $row->amount);
        }

        $pool = DB::table('pos_tip_distribution_lines')
            ->join('pos_tip_distributions', 'pos_tip_distributions.id', '=', 'pos_tip_distribution_lines.distribution_id')
            ->where('pos_tip_distributions.branch_id', $branch->id)
            ->where('pos_tip_distributions.status', TipDistributionStatus::CLOSED->value)
            ->whereNotNull('pos_tip_distribution_lines.staff_id')
            ->whereBetween('pos_tip_distributions.business_date', [$from, $to])
            ->get(['pos_tip_distribution_lines.staff_id', 'pos_tip_distributions.business_date', 'pos_tip_distribution_lines.amount']);
        foreach ($pool as $row) {
            $add((int) $row->staff_id, substr((string) $row->business_date, 0, 10), $row->amount);
        }

        return $out;
    }
}
