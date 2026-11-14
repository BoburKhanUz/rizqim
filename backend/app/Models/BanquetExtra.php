<?php

namespace App\Models;

use App\Domain\Pos\Banquet\BanquetExtraPricing;
use Illuminate\Database\Eloquent\Model;

/**
 * P6-52: an extra of the store's banquet catalogue (extra hour, tort, dekor…), priced per event or per person.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property string $name
 * @property numeric-string $price
 * @property BanquetExtraPricing $pricing
 * @property bool $is_active
 * @property int $sort_order
 */
class BanquetExtra extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'pricing' => 'per_event',
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pricing' => BanquetExtraPricing::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
