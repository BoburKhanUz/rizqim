<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PaymentStatus;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property \App\Enums\PaymentStatus $status
 * @property numeric-string $amount
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $expired_at
 *
 * P3-18: POS payments (bill, shift, terminal, cash tendered and change, card details)
 * @property int|null $bill_id
 * @property int|null $shift_id
 * @property numeric-string|null $tendered_amount
 * @property numeric-string|null $change_amount
 *
 * P3-31: money a till took offline (App\Domain\Pos\Sync)
 * @property \Illuminate\Support\Carbon|null $offline_paid_at
 * @property \Illuminate\Support\Carbon|null $synced_at
 *
 * M4 stage 2: the store of the parent (orders.store_id, or the top-up request's for a store's Payme top-up). Set
 * explicitly by every creation site; a database trigger sets it from the parent for any other writer and keeps it equal
 * to the parent's (migration 2026_11_27_100000)
 * @property int $store_id
 */
class Payment extends Model
{
    use BelongsToStore;
    use \App\Traits\HasPublicUuid;

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'uuid', 'order_id', 'provider', 'provider_transaction_id', 'amount', 'status', 'payload', 'paid_at',
        'cancelled_at', 'expired_at', 'created_at', 'updated_at', 'bill_id', 'shift_id', 'device_id', 'received_by',
        'tendered_amount', 'change_amount', 'card_type', 'transaction_id', 'reference', 'offline_paid_at',
        'synced_at', 'card_last4', 'store_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            // P3-18: POS payments
            'bill_id' => 'integer',
            'shift_id' => 'integer',
            'tendered_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            // P3-31: money taken offline (the device's clock) and when it reached the server
            'offline_paid_at' => 'datetime',
            'synced_at' => 'datetime',
            'store_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // --- P3-18: POS payments ------------------------------------------------------------------------------------------

    /**
     * @return BelongsTo<OrderBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(OrderBill::class, 'bill_id');
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    // --- end P3-18 ------------------------------------------------------------------------------------------------
}
