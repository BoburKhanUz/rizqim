<?php

namespace App\Models;

use App\Domain\Pos\Billing\BillStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P3-18: one bill (schyot) of an order and its share of the lines, the discount and the charges (POS_SPEC 2.9).
 *
 * @property BillStatus $status
 * @property numeric-string $subtotal
 * @property numeric-string $discount
 * @property numeric-string $room_fee_amount
 * @property numeric-string $min_spend_topup_amount
 * @property numeric-string $service_charge_amount
 * @property numeric-string $place_fee_amount
 * @property numeric-string $total
 * @property int $printed_count
 * @property \Illuminate\Support\Carbon|null $printed_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $voided_at
 */
class OrderBill extends Model
{
    use \App\Traits\HasPublicUuid;

    /** fiscal_receipts.subject_type of a bill's sale receipt */
    public const FISCAL_SUBJECT = 'order_bill';

    /** The charges a bill carries next to its lines, as FiscalLineBuilder::forCharge names them => column */
    public const CHARGES = [
        'room_fee' => 'room_fee_amount',
        'min_spend_topup' => 'min_spend_topup_amount',
        'service_charge' => 'service_charge_amount',
        'place_fee' => 'place_fee_amount',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => BillStatus::class,
            'number' => 'integer',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'room_fee_amount' => 'decimal:2',
            'min_spend_topup_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'place_fee_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'guests_share' => 'integer',
            'seat_no' => 'integer',
            'printed_count' => 'integer',
            'printed_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<OrderBillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderBillItem::class, 'bill_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bill_id');
    }
}
