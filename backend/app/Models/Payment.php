<?php

namespace App\Models;

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
 */
class Payment extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

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
