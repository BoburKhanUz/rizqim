<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\Auditable;
use App\Traits\HasMenuImage;

/**
 * @property bool $batch_tracked available on a branch only while an open batch has something left (P1-21)
 * @property array<string, string>|null $image_thumbs
 * @property string|null $image_disk
 */
class Product extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    use Auditable;
    use HasMenuImage;
    
    protected $guarded = ['id'];
    
    protected $appends = ['image_url', 'is_active'];

    protected $casts = [
        'batch_tracked' => 'boolean',
    ];

    protected $attributes = [
        'batch_tracked' => false,
    ];

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['status'] = $value ? 'active' : 'inactive';
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    
    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    
    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * The modifier groups this product offers, with the per-product overrides on the pivot (P1-21).
     *
     * @return BelongsToMany<ModifierGroup, $this, ProductModifierGroup>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups')
            ->using(ProductModifierGroup::class)
            ->withPivot(['id', 'min_select', 'max_select', 'is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<ProductModifierGroup, $this>
     */
    public function modifierGroupLinks(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class);
    }

    /**
     * Per-channel prices and visibility of the product and its variants (P1-21).
     *
     * @return HasMany<ChannelPrice, $this>
     */
    public function channelPrices(): HasMany
    {
        return $this->hasMany(ChannelPrice::class);
    }

    /**
     * @return HasMany<MenuBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(MenuBatch::class);
    }
}
