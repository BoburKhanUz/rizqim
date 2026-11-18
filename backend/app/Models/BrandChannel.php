<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Enums\OrderChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-55: a brand on one sales channel: hidden there and/or its dishes' price adjusted by a percent (ChannelMenu).
 *
 * @property int $brand_id
 * @property OrderChannel $channel
 * @property bool $is_hidden
 * @property numeric-string|null $price_percent
 */
class BrandChannel extends Model
{
    use BelongsToStore;

    protected $guarded = ['id'];

    protected $casts = [
        'brand_id' => 'integer',
        'channel' => OrderChannel::class,
        'is_hidden' => 'boolean',
        'price_percent' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
