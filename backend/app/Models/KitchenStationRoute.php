<?php

namespace App\Models;

use App\Domain\Pos\Setup\KitchenStationRouter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sends one product or one whole category of a branch to a kitchen station (P3-13). Exactly one of `product_id` and
 * `category_id` is set; a product route beats the route of its category.
 */
class KitchenStationRoute extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // The resolver keeps a branch's routes for the request: a change drops them
        $forget = fn (self $route) => app(KitchenStationRouter::class)->forget((int) $route->branch_id);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return BelongsTo<KitchenStation, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'station_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
