<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * P6-55 / B9 (module ingredient_stoplist): an ingredient the store's dishes use. Stopping it on a branch closes every
 * critically linked dish there at once (App\Domain\Pos\Ingredients). Not a recipe: no quantities.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property string $name
 * @property string|null $unit
 */
class Ingredient extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')->withPivot('is_critical')->withTimestamps();
    }
}
