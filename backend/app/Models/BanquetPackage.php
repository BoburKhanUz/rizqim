<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-52: a banquet package of the store: the per-person price, the minimum guests, what is included (free text lines)
 * and optional menu items with quantities per person (the production sheet reads them, P6-54).
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property string $name
 * @property numeric-string $per_person_price
 * @property int $minimum_guests
 * @property list<string>|null $included
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
class BanquetPackage extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'minimum_guests' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'per_person_price' => 'decimal:2',
            'minimum_guests' => 'integer',
            'included' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<BanquetPackageItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BanquetPackageItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
