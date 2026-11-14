<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-55 (module multi_brand): one of a store's brands sold from the same kitchen (App\Domain\Pos\Brands).
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property string $name
 * @property string $short_code shown on the kitchen ticket and the label
 * @property string $color #RRGGBB
 * @property string|null $logo_url
 * @property bool $is_active
 * @property int $sort_order
 */
class Brand extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The line snapshot (order_items.brand) and the kitchen ticket badge.
     *
     * @return array{id: int, name: string, code: string, color: string}
     */
    public function badge(): array
    {
        return ['id' => (int) $this->id, 'name' => (string) $this->name, 'code' => (string) $this->short_code, 'color' => (string) $this->color];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<BrandChannel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(BrandChannel::class);
    }

    /**
     * @return HasMany<BranchBrand, $this>
     */
    public function branchSwitches(): HasMany
    {
        return $this->hasMany(BranchBrand::class);
    }
}
