<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-31: one operation a till sent from its offline queue (POST /pos/sync) and what became of it (App\Domain\Pos\Sync).
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $branch_id
 * @property int $device_id
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
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
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
