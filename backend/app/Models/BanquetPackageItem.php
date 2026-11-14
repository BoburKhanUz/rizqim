<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-52: a menu item of a banquet package with its quantity per person (portions, pieces, kg or litres).
 *
 * @property int $id
 * @property int $banquet_package_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string $name
 * @property numeric-string $quantity_per_person
 * @property string $unit
 * @property int $sort_order
 */
class BanquetPackageItem extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'unit' => 'portion',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_person' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BanquetPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(BanquetPackage::class, 'banquet_package_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
