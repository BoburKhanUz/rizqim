<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "kombo" (P6-31): the menu product `product_id` sold at its own price plus the deltas of the options picked in its
 * slots. See App\Domain\Pos\Menu\Combos.
 *
 * @property bool $is_active
 */
class Combo extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<ComboSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(ComboSlot::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
