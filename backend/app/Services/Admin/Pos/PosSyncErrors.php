<?php

namespace App\Services\Admin\Pos;

use App\Domain\Pos\Sync\PosSyncPresenter;
use App\Domain\Pos\Sync\PosSyncStatus;
use App\Models\PosSyncOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P4-24 Oflayn sinxron xatolari: the offline operations of every till that were rejected or applied with a conflict
 * (POS_SPEC 5.3). Resolving one goes through App\Domain\Pos\Sync\PosSyncErrors (the controller).
 */
class PosSyncErrors
{
    public const ROW = 'array{op_id: string, seq: int, type: string, type_label: string, status: string, reason: string|null, message: string|null, store: array{id: int, name: string}|null, device: array{uuid: string, name: string|null}|null, staff: array{id: int, name: string|null}|null, order: array{uuid: string, local_number: string|null, short_code: string|null, table_ref: string|null, status: string}|null, occurred_at: string|null, received_at: string|null, clock_skew_seconds: int|null, resolved_at: string|null, resolved_by: array{id: int, name: string|null}|null, resolution: string|null, resolution_note: string|null}';

    /** Keys of an operation's payload that are never shown (a till may carry a phone, a card, a PIN). */
    private const HIDDEN_PAYLOAD_KEYS = '/phone|card|pin|token|password|secret/i';

    /**
     * @param  array<string, mixed>  $filters  state (open = unresolved, the default; resolved; all), status (rejected|conflict), type, store_id, device (uuid), from / to (dates, Asia/Tashkent)
     * @return Builder<PosSyncOperation>
     */
    public function query(array $filters): Builder
    {
        $state = (string) ($filters['state'] ?? 'open');
        $timezone = (string) config('billing.timezone');
        $query = PosSyncOperation::query()->where('status', '!=', PosSyncStatus::APPLIED->value);

        match ($state) {
            'open' => $query->whereNull('resolved_at'),
            'resolved' => $query->whereNotNull('resolved_at'),
            default => null,
        };

        return $query
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(filled($filters['type'] ?? null), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(filled($filters['store_id'] ?? null), fn (Builder $q) => $q->where('store_id', (int) $filters['store_id']))
            ->when(filled($filters['device'] ?? null), fn (Builder $q) => $q->whereIn('device_id', \App\Models\Device::query()->where('uuid', strtolower((string) $filters['device']))->select('id')))
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q->where('received_at', '>=', Carbon::parse($filters['from'], $timezone)->startOfDay()->utc()))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q->where('received_at', '<', Carbon::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc()));
    }

    /**
     * @return array{rejected: int, conflict: int, open: int}
     */
    public function counts(): array
    {
        $open = PosSyncOperation::query()->where('status', '!=', PosSyncStatus::APPLIED->value)->whereNull('resolved_at')
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            'rejected' => (int) ($open[PosSyncStatus::REJECTED->value] ?? 0),
            'conflict' => (int) ($open[PosSyncStatus::CONFLICT->value] ?? 0),
            'open' => (int) $open->sum(),
        ];
    }

    /**
     * @param  Builder<PosSyncOperation>  $query
     * @return Builder<PosSyncOperation>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with(['staff:id,name', 'device:id,uuid,name', 'order:id,uuid,local_number,short_code,table_ref,status', 'resolver:id,name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function row(PosSyncOperation $op, ?string $storeName): array
    {
        $data = PosSyncPresenter::operation($op);
        unset($data['payload'], $data['refs'], $data['applied_at']);

        return [...$data, 'store' => $storeName !== null ? ['id' => (int) $op->store_id, 'name' => $storeName] : null];
    }

    /**
     * The operation card: the row, the payload as the till sent it (without phones, cards and secrets) and what the
     * server made of it.
     *
     * @return array<string, mixed>
     */
    public function detail(PosSyncOperation $op, ?string $storeName): array
    {
        $full = PosSyncPresenter::operation($op);

        return [
            ...$this->row($op, $storeName),
            'applied_at' => $full['applied_at'],
            'payload' => $this->scrub((array) $full['payload']),
            'refs' => $full['refs'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function scrub(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match(self::HIDDEN_PAYLOAD_KEYS, $key) === 1) {
                $clean[$key] = '***';

                continue;
            }
            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }
}
