<?php

namespace App\Domain\Pos\TerminalFlow;

use App\Models\BankTerminalReport;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;

/**
 * A shift's bank terminal report as the till, the Z and the panel show it (amounts as decimal strings; `difference`
 * = bank − Rizqim).
 */
final class BankTerminalPresenter
{
    public const REPORT = 'array{uuid: string, shift_uuid: string|null, z_number: int|null, branch_id: int, business_date: string, source: \'manual\'|\'import\'|\'provider\', terminal_ref: string|null, bank_total: string, bank_count: int|null, rizqim_total: string, rizqim_count: int, rizqim_breakdown: ' . ShiftCardTotals::BREAKDOWN . '|null, difference: string, threshold: string, over_threshold: bool, transactions: list<array{rrn: string|null, amount: string, matched: bool}>|null, unmatched: array{bank: list<array{rrn: string|null, amount: string}>, rizqim: list<array{rrn: string|null, amount: string, kind: string}>}|null, note: string|null, entered_by: array{id: int, name: string|null}|null, alerted_at: string|null, created_at: string|null, updated_at: string|null}';

    /**
     * @return array<string, mixed>
     */
    public static function present(BankTerminalReport $report, ?PosShift $shift = null): array
    {
        $shift ??= PosShift::find($report->shift_id);
        $by = $report->getAttribute('entered_by');

        return [
            'uuid' => (string) $report->uuid,
            'shift_uuid' => $shift?->uuid,
            'z_number' => $shift?->z_number !== null ? (int) $shift->z_number : null,
            'branch_id' => (int) $report->branch_id,
            'business_date' => $report->business_date->toDateString(),
            'source' => $report->source,
            'terminal_ref' => $report->terminal_ref,
            'bank_total' => Money::of($report->bank_total)->toString(),
            'bank_count' => $report->bank_count,
            'rizqim_total' => Money::of($report->rizqim_total)->toString(),
            'rizqim_count' => (int) $report->rizqim_count,
            'rizqim_breakdown' => $report->rizqim_breakdown,
            'difference' => Money::of($report->difference)->toString(),
            'threshold' => Money::of($report->threshold)->toString(),
            'over_threshold' => (bool) $report->over_threshold,
            'transactions' => $report->transactions,
            'unmatched' => $report->unmatched,
            'note' => $report->note,
            'entered_by' => $by !== null ? ['id' => (int) $by, 'name' => User::whereKey($by)->value('name')] : null,
            'alerted_at' => $report->alerted_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }
}
