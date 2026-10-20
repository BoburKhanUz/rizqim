<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A product offering a modifier group, with its own min/max/required (null: the group's) and position.
 *
 * @property int|null $min_select
 * @property int|null $max_select
 * @property bool|null $is_required
 */
class ProductModifierGroup extends Pivot
{
    protected $table = 'product_modifier_groups';

    public $incrementing = true;

    protected $guarded = ['id'];

    protected $casts = [
        'min_select' => 'integer',
        'max_select' => 'integer',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ModifierGroup, $this>
     */
    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }
}
