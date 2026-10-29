<?php

namespace App\Domain\Pos\StaffPay;

use App\Models\Branch;

/**
 * P6-23: the tips each person of a branch received per business day (personal tips and their share of a pool), read
 * by the `tips` component of a wage rule. P6-22 owns the tips (pos_tips); the binding in StaffPayServiceProvider
 * reads them.
 */
interface StaffTipsSource
{
    /**
     * @return array<int, array<string, string>> user id => [business date (YYYY-MM-DD) => amount (decimal string)]
     */
    public function tipsByDay(Branch $branch, string $from, string $to): array;
}
