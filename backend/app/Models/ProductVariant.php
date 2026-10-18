<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property array<array-key, mixed>|null $attributes_json
 */
class ProductVariant extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    
    protected $guarded = ['id'];
    
    protected function casts(): array
    {
        return [
            'attributes_json' => 'array',
        ];
    }
    
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($variant) {
            $variant->loadMissing('product');
            $storeId = $variant->product?->store_id;
            if ($storeId) {
                \Illuminate\Support\Facades\Cache::forget("ops:inventory_risk:store_{$storeId}_variant_{$variant->id}");
                \Illuminate\Support\Facades\Cache::forget("ops:low_stock_alerts_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:low_stock_names_string:store_{$storeId}_limit_3");
                \Illuminate\Support\Facades\Cache::forget("ops:out_of_stock_count:store_{$storeId}");
            }
        });

        static::deleted(function ($variant) {
            $variant->loadMissing('product');
            $storeId = $variant->product?->store_id;
            if ($storeId) {
                \Illuminate\Support\Facades\Cache::forget("ops:inventory_risk:store_{$storeId}_variant_{$variant->id}");
                \Illuminate\Support\Facades\Cache::forget("ops:low_stock_alerts_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:low_stock_names_string:store_{$storeId}_limit_3");
                \Illuminate\Support\Facades\Cache::forget("ops:out_of_stock_count:store_{$storeId}");
            }
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
