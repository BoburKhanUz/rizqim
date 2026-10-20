<?php

namespace App\Models;

use App\Enums\OrderChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's or variant's own price and/or visibility on one sales channel. A row without a variant is about the
 * whole product (hidden only). No row: the variant's own price, visible.
 *
 * @property OrderChannel $channel
 * @property numeric-string|null $price
 * @property bool $is_hidden
 */
class ChannelPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'channel' => OrderChannel::class,
        'price' => 'decimal:2',
        'is_hidden' => 'boolean',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
