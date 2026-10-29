<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "kompleks" (P6-15): the menu product `product_id` is sold at its own price as one line; `items` are the dishes the
 * kitchen prepares for it. See App\Domain\Pos\Menu\MealSets.
 *
 * @property bool $is_active
 */
class DailySet extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<DailySetItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DailySetItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
