<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A store's group of modifiers ("Sous tanlang", "Qo'shimchalar"): how many of them an item takes (min/max) and
 * whether choosing is required. Products offer groups through product_modifier_groups, which may override the rules.
 *
 * @property array<string, string>|null $name_i18n
 * @property int $min_select
 * @property int|null $max_select
 * @property bool $is_required
 * @property bool $is_active
 */
class ModifierGroup extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'name_i18n' => 'array',
        'min_select' => 'integer',
        'max_select' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'min_select' => 0,
        'is_required' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<Modifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Product, $this, ProductModifierGroup>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups')
            ->using(ProductModifierGroup::class)
            ->withPivot(['id', 'min_select', 'max_select', 'is_required', 'sort_order'])
            ->withTimestamps();
    }
}
