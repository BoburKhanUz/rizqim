<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Domain\Pos\Sync\PosSyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-31: one operation a till sent from its offline queue (POST /pos/sync) and what became of it (App\Domain\Pos\Sync).
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $branch_id
 * @property int $device_id
 * @property int|null $origin_device_id P6: the terminal the branch's hub relayed it for (null: the sender's own)
 * @property string $op_id
 * @property int $seq
 * @property string $type
 * @property int|null $staff_id
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property array<string, mixed> $payload
 * @property string $status
 * @property string|null $reason
 * @property string|null $error
 * @property array<string, mixed>|null $result
 * @property string|null $client_ref
 * @property int|null $order_id
 * @property int|null $clock_skew_seconds
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution
 * @property string|null $resolution_note
 */
class PosSyncOperation extends Model
{
    use BelongsToStore;
    use MassPrunable;

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'store_id', 'branch_id', 'device_id', 'op_id', 'seq', 'type', 'staff_id', 'occurred_at', 'payload', 'status',
        'reason', 'error', 'result', 'client_ref', 'order_id', 'clock_skew_seconds', 'received_at', 'applied_at',
        'resolved_at', 'resolved_by', 'resolution', 'resolution_note', 'created_at', 'updated_at', 'origin_device_id',
    ];

    /**
     * M3: an applied operation older than 12 months is deleted by the daily `php artisan model:prune`: a till never
     * resends anything that old, and the order it made keeps its own history. Conflicts and rejected operations
     * ("Sinxronlash xatolari") are kept.
     *
     * @return Builder<PosSyncOperation>
     */
    public function prunable(): Builder
    {
        return static::where('status', PosSyncStatus::APPLIED->value)->where('received_at', '<', now()->subMonths(12));
    }

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'origin_device_id' => 'integer',
            'staff_id' => 'integer',
            'order_id' => 'integer',
            'occurred_at' => 'datetime',
            'payload' => 'array',
            'result' => 'array',
            'clock_skew_seconds' => 'integer',
            'received_at' => 'datetime',
            'applied_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * P6 local bridge: the terminal the hub relayed this operation for.
     *
     * @return BelongsTo<Device, $this>
     */
    public function originDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'origin_device_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Rejected or conflicting and nobody has dealt with it yet ("Sinxronlash xatolari").
     */
    public function needsAttention(): bool
    {
        return $this->status !== 'applied' && $this->resolved_at === null;
    }
}
