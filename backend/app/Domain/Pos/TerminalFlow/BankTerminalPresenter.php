<?php

namespace App\Domain\Pos\TerminalFlow;

use App\Models\BankTerminalReport;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;

/**
 * A bank terminal report as the till, the Z and the panel show it (amounts as decimal strings; `difference` = bank −
 * Rizqim). `shifts`: the shifts it compares (one, or — `shared` — every shift of the branch's business day on the
 * shared terminal); `final`: all of them are closed. `history`: the earlier entries a correction replaced.
 *
 * Review P6 S#5: for a caller without finance.manage (the till, a cashier, a manager: $hideUntilFinal) a report that is
 * not final shows what was entered only — Rizqim's side, the threshold, the difference, `over_threshold`, the RRN
 * matching are null — so the bank total is typed from the slip, not fitted to Rizqim's figure. finance.manage and the
 * report list (finance.view, reports.view) see everything.
 */
final class BankTerminalPresenter
{
    public const REPORT = 'array{uuid: string, shift_uuid: string|null, z_number: int|null, branch_id: int, business_date: string, source: \'manual\'|\'import\'|\'provider\', terminal_ref: string|null, shared: bool, final: bool, shifts: list<array{uuid: string, z_number: int|null, status: string}>, bank_total: string, bank_count: int|null, rizqim_total: string|null, rizqim_count: int|null, rizqim_breakdown: ' . ShiftCardTotals::BREAKDOWN . '|null, difference: string|null, threshold: string|null, over_threshold: bool|null, transactions: list<array{rrn: string|null, amount: string, matched: bool|null}>|null, unmatched: array{bank: list<array{rrn: string|null, amount: string}>, rizqim: list<array{rrn: string|null, amount: string, kind: string}>}|null, history: list<array{source: string, bank_total: string, bank_count: int|null, difference: string, over_threshold: bool, entered_by: int|null, entered_at: string|null}>, note: string|null, entered_by: array{id: int, name: string|null}|null, alerted_at: string|null, created_at: string|null, updated_at: string|null}';

    /**
     * @return array<string, mixed>
     */
    public static function present(BankTerminalReport $report, ?PosShift $shift = null, bool $hideUntilFinal = false): array
    {
        $shift ??= PosShift::find($report->shift_id);
        $by = $report->getAttribute('entered_by');
        $covered = $report->coveredShifts();
        $final = $covered->isNotEmpty() && $covered->every(fn (PosShift $one) => !$one->isOpen());
        $hide = $hideUntilFinal && !$final;

        return [
            'uuid' => (string) $report->uuid,
            'shift_uuid' => $shift?->uuid,
            'z_number' => $shift?->z_number !== null ? (int) $shift->z_number : null,
            'branch_id' => (int) $report->branch_id,
            'business_date' => $report->business_date->toDateString(),
            'source' => $report->source,
            'terminal_ref' => $report->terminal_ref,
            'shared' => (bool) $report->shared,
            'final' => $final,
            'shifts' => $covered->map(fn (PosShift $one) => [
                'uuid' => (string) $one->uuid,
                'z_number' => $one->z_number !== null ? (int) $one->z_number : null,
                'status' => $one->status->value,
            ])->values()->all(),
            'bank_total' => Money::of($report->bank_total)->toString(),
            'bank_count' => $report->bank_count,
            'rizqim_total' => $hide ? null : Money::of($report->rizqim_total)->toString(),
            'rizqim_count' => $hide ? null : (int) $report->rizqim_count,
            'rizqim_breakdown' => $hide ? null : $report->rizqim_breakdown,
            'difference' => $hide ? null : Money::of($report->difference)->toString(),
            'threshold' => $hide ? null : Money::of($report->threshold)->toString(),
            'over_threshold' => $hide ? null : (bool) $report->over_threshold,
            'transactions' => $hide && is_array($report->transactions)
                ? array_map(fn (array $row) => ['rrn' => $row['rrn'], 'amount' => $row['amount'], 'matched' => null], $report->transactions)
                : $report->transactions,
            'unmatched' => $hide ? null : $report->unmatched,
            'history' => is_array($report->history) ? $report->history : [],
            'note' => $report->note,
            'entered_by' => $by !== null ? ['id' => (int) $by, 'name' => User::whereKey($by)->value('name')] : null,
            'alerted_at' => $hide ? null : $report->alerted_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }
}
