<?php

namespace App\Models;

use App\Domain\Pos\Booking\BookingPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-17: a deposit (avans) taken against a booking, with its ADVANCE receipt; offset against the order's bills
 * (AdvanceLedger), or refunded or retained (App\Domain\Pos\Booking\BookingDepositService).
 *
 * @property numeric-string $amount
 * @property BookingPaymentStatus $status
 * @property numeric-string $applied_amount
 * @property list<array{bill_uuid: string, amount: string, at: string}>|null $applications
 * @property numeric-string $refunded_amount
 * @property numeric-string $retained_amount
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property int|null $refunded_device_id the terminal the refund was made on (P3 integration fix)
 * @property int|null $refunded_shift_id the shift open on that terminal: a cash refund came out of its drawer (ShiftReport)
 */
class BookingPayment extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => BookingPaymentStatus::class,
        'applied_amount' => 'decimal:2',
        'applications' => 'array',
        'refunded_amount' => 'decimal:2',
        'retained_amount' => 'decimal:2',
        'settled_at' => 'datetime',
        'refunded_device_id' => 'integer',
        'refunded_shift_id' => 'integer',
    ];

    protected $attributes = [
        'status' => 'taken',
        'applied_amount' => '0.00',
        'refunded_amount' => '0.00',
        'retained_amount' => '0.00',
    ];

    /**
     * What is still neither offset, refunded nor retained.
     */
    public function remaining(): \App\Support\Money
    {
        return \App\Support\Money::of($this->amount)
            ->minus($this->applied_amount)
            ->minus($this->refunded_amount)
            ->minus($this->retained_amount);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
