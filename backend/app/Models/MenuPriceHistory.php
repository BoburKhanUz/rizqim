<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Review P3-35: from `effective_from` on, a variant cost `price` on a POS channel, or a modifier (channel null) added
 * `price` (App\Services\Catalog\MenuPriceHistory writes and reads it).
 *
 * @property int $store_id
 * @property int|null $product_variant_id
 * @property int|null $modifier_id
 * @property string|null $channel
 * @property numeric-string $price
 * @property \Illuminate\Support\Carbon $effective_from
 */
class MenuPriceHistory extends Model
{
    use BelongsToStore;

    protected $table = 'menu_price_history';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'effective_from' => 'datetime',
    ];
}
