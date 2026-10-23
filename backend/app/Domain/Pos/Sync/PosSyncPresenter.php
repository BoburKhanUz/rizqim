<?php

namespace App\Domain\Pos\Sync;

use App\Models\Device;
use App\Models\PosSyncOperation;

/**
 * The JSON of offline operations (POST /pos/sync results, GET /pos/sync/status, "Sinxronlash xatolari").
 */
final class PosSyncPresenter
{
    public const RESULT = 'array{op_id: string, seq: int, type: string, status: string, reason: string|null, message: string|null, duplicate: bool, refs: array<string, mixed>}';

    public const OPERATION = 'array{op_id: string, seq: int, type: string, type_label: string, status: string, reason: string|null, message: string|null, staff: array{id: int, name: string|null}|null, device: array{uuid: string, name: string|null}|null, occurred_at: string|null, received_at: string|null, applied_at: string|null, clock_skew_seconds: int|null, order: array{uuid: string, local_number: string|null, short_code: string|null, table_ref: string|null, status: string}|null, payload: array<string, mixed>, refs: array<string, mixed>, resolved_at: string|null, resolved_by: array{id: int, name: string|null}|null, resolution: string|null, resolution_note: string|null}';

    public const DEVICE = 'array{uuid: string, name: string|null, prefix: string|null, last_seq: int, pending_ops: int, oldest_op_at: string|null, reported_at: string|null, clock_skew_seconds: int|null, clock_warning: bool}';

    /**
     * @return array<string, mixed>
     */
    public static function result(PosSyncOperation $op, bool $duplicate = false): array
    {
        return [
            'op_id' => $op->op_id,
            'seq' => (int) $op->seq,
            'type' => $op->type,
            'status' => $op->status,
            'reason' => $op->reason,
            'message' => $op->error,
            'duplicate' => $duplicate,
            'refs' => (array) ($op->result ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function operation(PosSyncOperation $op): array
    {
        $op->loadMissing(['staff:id,name', 'device:id,uuid,name', 'order:id,uuid,local_number,short_code,table_ref,status', 'resolver:id,name']);
        $type = PosSyncOperationType::tryFrom($op->type);

        return [
            'op_id' => $op->op_id,
            'seq' => (int) $op->seq,
            'type' => $op->type,
            'type_label' => $type?->label() ?? $op->type,
            'status' => $op->status,
            'reason' => $op->reason,
            'message' => $op->error,
            'staff' => $op->staff ? ['id' => (int) $op->staff->id, 'name' => $op->staff->name] : null,
            'device' => $op->device ? ['uuid' => (string) $op->device->uuid, 'name' => $op->device->name] : null,
            'occurred_at' => $op->occurred_at?->toJSON(),
            'received_at' => $op->received_at->toJSON(),
            'applied_at' => $op->applied_at?->toJSON(),
            'clock_skew_seconds' => $op->clock_skew_seconds,
            'order' => $op->order ? [
                'uuid' => (string) $op->order->uuid,
                'local_number' => $op->order->local_number,
                'short_code' => $op->order->short_code,
                'table_ref' => $op->order->table_ref,
                'status' => $op->order->status->value,
            ] : null,
            'payload' => $op->payload,
            'refs' => (array) ($op->result ?? []),
            'resolved_at' => $op->resolved_at?->toJSON(),
            'resolved_by' => $op->resolver ? ['id' => (int) $op->resolver->id, 'name' => $op->resolver->name] : null,
            'resolution' => $op->resolution,
            'resolution_note' => $op->resolution_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function device(Device $device): array
    {
        return [
            'uuid' => (string) $device->uuid,
            'name' => $device->name,
            'prefix' => $device->getAttribute('sync_prefix'),
            'last_seq' => (int) $device->getAttribute('sync_last_seq'),
            'pending_ops' => (int) $device->getAttribute('sync_pending_ops'),
            'oldest_op_at' => $device->getAttribute('sync_oldest_op_at')?->toJSON(),
            'reported_at' => $device->getAttribute('sync_reported_at')?->toJSON(),
            'clock_skew_seconds' => $device->getAttribute('sync_clock_skew_seconds'),
            'clock_warning' => PosSyncService::clockWarning($device->getAttribute('sync_clock_skew_seconds')),
        ];
    }
}
