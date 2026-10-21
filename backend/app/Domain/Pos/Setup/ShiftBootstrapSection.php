<?php

namespace App\Domain\Pos\Setup;

use App\Domain\Pos\Shifts\ShiftStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\User;

/**
 * The `shift` of GET /pos/bootstrap (P3-18's model): the shift open on this terminal, so the till knows at start-up
 * whether to ask for the opening cash. null outside a terminal session or when no shift is open there; the full shift
 * (cash movements, X report) is the shift endpoints' business.
 */
final class ShiftBootstrapSection implements PosBootstrapContributor
{
    /**
     * @return array{shift: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        if ($device === null) {
            return ['shift' => null];
        }

        $shift = PosShift::query()
            ->where('branch_id', $branch->id)
            ->where('device_id', $device->id)
            ->where('status', ShiftStatus::OPEN->value)
            ->latest('id')
            ->first();

        return ['shift' => $shift === null ? null : [
            'uuid' => $shift->uuid,
            'status' => $shift->status->value,
            'business_date' => $shift->business_date->format('Y-m-d'),
            'opened_at' => $shift->opened_at->toIso8601String(),
            'opened_by' => $shift->opened_by !== null ? (int) $shift->opened_by : null,
            'opening_cash' => $shift->opening_cash,
            'device_id' => $device->uuid,
        ]];
    }
}
