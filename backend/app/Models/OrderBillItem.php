<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-18: a bill's share of an order line: a quantity (fractions too: 0.5 kg of 1.5 kg), its share of the line total
 * and of the order discount.
 *
 * @property numeric-string $quantity
 * @property numeric-string $amount
 * @property numeric-string $discount_amount
 */
class OrderBillItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<OrderBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(OrderBill::class, 'bill_id');
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class)->withTrashed();
    }
}
