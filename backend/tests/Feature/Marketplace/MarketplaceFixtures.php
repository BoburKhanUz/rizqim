<?php

namespace Tests\Feature\Marketplace;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores spread around Tashkent for the read-model tests, and a query log of the tables a request read.
 */
trait MarketplaceFixtures
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function marketStore(string $name, float $lat, float $lng, float $radius = 5.0, array $attributes = []): Store
    {
        $store = Store::forceCreate(array_merge([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'is_active' => true,
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => true, 'pickup_enabled' => true, 'delivery_fee' => 0],
        ], $attributes));

        Branch::create([
            'store_id' => $store->id,
            'name' => $name.' filial',
            'latitude' => $lat,
            'longitude' => $lng,
            'radius_km' => $radius,
            'status' => 'active',
        ]);

        return $store;
    }

    protected function marketProduct(Store $store, string $name, string $price, ?Category $category = null): ProductVariant
    {
        $category ??= Category::firstOrCreate(['slug' => 'mkt-taomlar'], ['name' => 'Taomlar', 'is_active' => true]);
        $product = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Oddiy',
            'price' => $price,
            'stock' => 100,
            'is_active' => true,
        ]);
    }

    /**
     * The tables read by the statements that $callback runs (FROM / JOIN targets of every query).
     *
     * @return list<string>
     */
    protected function tablesRead(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $tables = [];
        foreach ($log as $entry) {
            if (preg_match_all('/\b(?:from|join)\s+"?([a-z_]+)"?/i', $entry['query'], $m)) {
                foreach ($m[1] as $table) {
                    $tables[strtolower($table)] = true;
                }
            }
        }
        ksort($tables);

        return array_keys($tables);
    }

    protected function withReadModel(bool $on): void
    {
        config(['marketplace.read_model' => $on]);
    }
}
