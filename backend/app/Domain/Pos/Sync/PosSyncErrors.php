<?php

namespace App\Domain\Pos\Sync;

use App\Models\Device;
use App\Models\PosShift;
use App\Models\PosSyncOperation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Sinxronlash xatolari" (POS_SPEC 5.3): offline operations a manager has to look at — rejected (not applied) and
 * conflicting (applied with a difference: oversold, payment on a cancelled order, amount mismatch, stale menu...) —
 * until somebody resolves them (did what was needed by hand) or dismisses them (nothing to do).
 */
final class PosSyncErrors
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The store's operations that need attention (or, with $all, every rejected and conflicting one), newest first.
     *
     * @return Builder<PosSyncOperation>
     */
    public function query(int $storeId, ?int $branchId, ?Device $device = null, bool $all = false): Builder
    {
        return PosSyncOperation::query()
            ->where('store_id', $storeId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($device !== null, fn (Builder $query) => $query->where('device_id', $device?->id))
            ->where('status', '!=', PosSyncStatus::APPLIED->value)
            ->when(!$all, fn (Builder $query) => $query->whereNull('resolved_at'))
            ->orderByDesc('id');
    }

    /**
     * Rejected operations of the shift's till since it opened that nobody resolved: the shift does not close over them
     * (money or food that is not on the server).
     *
     * @return list<string> their op ids
     */
    public function blockingShift(PosShift $shift): array
    {
        return PosSyncOperation::query()
            ->where('device_id', $shift->device_id)
            ->where('status', PosSyncStatus::REJECTED->value)
            ->whereNull('resolved_at')
            ->where('received_at', '>=', $shift->opened_at)
            ->orderBy('seq')
            ->pluck('op_id')
            ->all();
    }

    /**
     * @param  'resolved'|'dismissed'  $resolution
     */
    public function resolve(PosSyncOperation $op, User $manager, string $resolution, ?string $note): PosSyncOperation
    {
        return DB::transaction(function () use ($op, $manager, $resolution, $note) {
            $op = PosSyncOperation::whereKey($op->id)->lockForUpdate()->firstOrFail();
            if ($op->resolved_at !== null) {
                return $op;
            }

            $op->forceFill([
                'resolved_at' => now(),
                'resolved_by' => $manager->id,
                'resolution' => $resolution,
                'resolution_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ])->save();

            $this->audit->record('pos.sync_error_' . $resolution, $op->order, [], [], array_filter([
                'op_id' => $op->op_id,
                'type' => $op->type,
                'status' => $op->status,
                'reason' => $op->reason,
                'note' => $op->resolution_note,
            ], fn ($value) => $value !== null), ['actor' => $manager, 'store_id' => (int) $op->store_id, 'branch_id' => $op->branch_id]);

            return $op;
        });
    }
}
