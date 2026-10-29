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
 *
 * P3-11: what a fiscal receipt line needs (App\Domain\Fiscal)
 * @property \App\Enums\ProductKind $kind dish | service
 * @property bool $quick_add a one-tap button at the till and on the waiter phone (P3-26, GET /pos/bootstrap quick_items)
 * @property string|null $mxik_code 17 digits; a fiscal store sells nothing without it (FiscalReadiness)
 * @property string|null $package_code
 * @property numeric-string|null $vat_rate null: the store's tax regime decides
 * @property list<int>|null $quick_multipliers P6-25: the one-tap ×N buttons of a skewer or piece dish
 * @property int|null $wait_minutes P6-25: how long the grill takes
 * @property string|null $weight_mode P6-25: raw|cooked — weighed after it is chosen (App\Domain\Pos\Orders\WeightBasis)
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
        'quick_add' => 'boolean', // P3-26
        // P3-11: fiscal fields
        'kind' => \App\Enums\ProductKind::class,
        'vat_rate' => 'decimal:2',
        // P6-25: skewer buttons, grill time, weight later
        'quick_multipliers' => 'array',
        'wait_minutes' => 'integer',
    ];

    protected $attributes = [
        'batch_tracked' => false,
        'quick_add' => false, // P3-26
        'kind' => 'dish', // P3-11
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
        // In the order they were added: Postgres returns unordered rows in any order, and menus, APIs and tests rely on it
        return $this->hasMany(ProductVariant::class)->orderBy('id');
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
