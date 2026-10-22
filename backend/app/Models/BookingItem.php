<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-17: a dish ordered ahead with a booking (N kg osh by 19:00). On arrival it becomes an order line on hold with
 * this due time (PosOrderOpener).
 *
 * @property \App\Enums\MenuUnit $unit
 * @property numeric-string $quantity
 * @property list<array<string, mixed>>|null $modifiers
 * @property numeric-string $unit_price
 * @property \Illuminate\Support\Carbon|null $due_at
 */
class BookingItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit' => \App\Enums\MenuUnit::class,
        'quantity' => 'decimal:3',
        'modifiers' => 'array',
        'unit_price' => 'decimal:2',
        'due_at' => 'datetime',
    ];

    protected $attributes = [
        'unit' => 'piece',
        'unit_price' => '0.00',
    ];

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
