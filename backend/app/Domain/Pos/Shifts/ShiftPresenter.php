<?php

namespace App\Domain\Pos\Shifts;

use App\Models\Device;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;

/**
 * The JSON of a shift for the till and the panel (amounts as decimal strings). The Z report (`summary`) only on the
 * detail.
 */
final class ShiftPresenter
{
    public const SHIFT = 'array{uuid: string, status: string, branch_id: int, device: array{uuid: string, name: string}|null, business_date: string, opened_at: string, opened_by: array{id: int, name: string}|null, opening_cash: string, closed_at: string|null, closed_by: array{id: int, name: string}|null, expected_cash: string|null, counted_cash: string|null, counted_breakdown: array<string, int>|null, difference: string|null, z_number: int|null, force_closed: bool, close_note: string|null}';

    /** The Z report as kept at closing (`summary`): the X report then, plus what was left open by a forced close. */
    public const SUMMARY = 'array{' . ShiftReport::REPORT_FIELDS . ', open_orders?: list<string>, pending_receipts?: array{bills: list<string>, refunds: list<string>}}';

    /** A shift with its Z report (`summary`: null while open) and, P6-16, its bank terminal report (`bank_terminal`). */
    public const DETAIL = 'array{uuid: string, status: string, branch_id: int, device: array{uuid: string, name: string}|null, business_date: string, opened_at: string, opened_by: array{id: int, name: string}|null, opening_cash: string, closed_at: string|null, closed_by: array{id: int, name: string}|null, expected_cash: string|null, counted_cash: string|null, counted_breakdown: array<string, int>|null, difference: string|null, z_number: int|null, force_closed: bool, close_note: string|null, summary: ' . self::SUMMARY . '|null, bank_terminal: ' . \App\Domain\Pos\TerminalFlow\BankTerminalPresenter::REPORT . '|null}';

    /**
     * @return array<string, mixed>
     */
    public function shift(PosShift $shift, bool $withSummary = false): array
    {
        $device = $shift->device_id !== null ? Device::find($shift->device_id) : null;
        $people = User::whereIn('id', array_filter([$shift->opened_by, $shift->closed_by]))->pluck('name', 'id');
        $person = fn ($id) => $id !== null ? ['id' => (int) $id, 'name' => (string) ($people[$id] ?? '')] : null;

        $data = [
            'uuid' => (string) $shift->uuid,
            'status' => $shift->status->value,
            'branch_id' => (int) $shift->branch_id,
            'device' => $device !== null ? ['uuid' => (string) $device->uuid, 'name' => (string) $device->name] : null,
            'business_date' => $shift->business_date->toDateString(),
            'opened_at' => $shift->opened_at->toIso8601String(),
            'opened_by' => $person($shift->opened_by),
            'opening_cash' => Money::of($shift->opening_cash)->toString(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'closed_by' => $person($shift->closed_by),
            'expected_cash' => $shift->expected_cash !== null ? Money::of($shift->expected_cash)->toString() : null,
            'counted_cash' => $shift->counted_cash !== null ? Money::of($shift->counted_cash)->toString() : null,
            'counted_breakdown' => $shift->counted_breakdown,
            'difference' => $shift->difference !== null ? Money::of($shift->difference)->toString() : null,
            'z_number' => $shift->z_number !== null ? (int) $shift->z_number : null,
            'force_closed' => (bool) $shift->force_closed,
            'close_note' => $shift->getAttribute('close_note'),
        ];

        if ($withSummary) {
            $data['summary'] = $shift->summary;
            // P6-16: the bank terminal's day total compared with the shift's card money, a row of the Z
            // (its own report or its shared terminal's day; review P6 S#5: without finance.manage the comparison shows
            // once final)
            $bank = \App\Models\BankTerminalReport::forShift($shift);
            $viewer = request()->user();
            $reveal = $viewer instanceof User && app(\App\Services\Auth\PermissionService::class)->allows($viewer, \App\Enums\Permission::FINANCE_MANAGE, $shift);
            $data['bank_terminal'] = $bank !== null ? \App\Domain\Pos\TerminalFlow\BankTerminalPresenter::present($bank, $shift, hideUntilFinal: !$reveal) : null;
        }

        return $data;
    }
}
