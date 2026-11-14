<?php

namespace App\Domain\Pos\Brands;

use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * P6-55 (module multi_brand): an order line remembers the brand its dish was sold under (order_items.brand_id and the
 * `brand` snapshot {id, name, code, color}), whichever path creates it (Mini App checkout, the till, offline sync, a
 * booking's pre-order), so kitchen tickets, labels and the channel report read it without asking the catalogue. A
 * dish of the house brand (no brand) leaves both null: nothing changes for a store without brands.
 */
class PosBrandsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        OrderItem::creating(function (OrderItem $item) {
            if ($item->getAttribute('brand_id') !== null || $item->product_variant_id === null) {
                return;
            }
            // A combo's kitchen part is cooked under the combo's brand (the one the guest ordered)
            if ($item->combo_parent_id !== null) {
                $parent = DB::table('order_items')->where('id', $item->combo_parent_id)->first(['brand_id', 'brand']);
                if ($parent !== null && $parent->brand_id !== null) {
                    $item->setAttribute('brand_id', (int) $parent->brand_id);
                    $item->setAttribute('brand', json_decode((string) $parent->brand, true));
                }

                return;
            }

            $brand = DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->join('brands', 'brands.id', '=', 'products.brand_id')
                ->where('product_variants.id', $item->product_variant_id)
                ->first(['brands.id', 'brands.name', 'brands.short_code', 'brands.color']);
            if ($brand === null) {
                return;
            }

            $item->setAttribute('brand_id', (int) $brand->id);
            $item->setAttribute('brand', ['id' => (int) $brand->id, 'name' => (string) $brand->name, 'code' => (string) $brand->short_code, 'color' => (string) $brand->color]);
        });
    }
}
