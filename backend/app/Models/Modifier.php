<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One choice of a modifier group with its price delta (added to the item's price per piece, or once per line for an
 * item sold by weight). May point at a product variant whose stock it will use once ingredient stock exists.
 *
 * @property array<string, string>|null $name_i18n
 * @property numeric-string $price_delta
 * @property int $max_quantity
 * @property bool $is_default P6-32: preselected on the till's tile (a hint; the order carries explicit choices)
 * @property bool $is_active
 */
class Modifier extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'name_i18n' => 'array',
        'price_delta' => 'decimal:2',
        'max_quantity' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'price_delta' => '0.00',
        'max_quantity' => 1,
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<ModifierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
