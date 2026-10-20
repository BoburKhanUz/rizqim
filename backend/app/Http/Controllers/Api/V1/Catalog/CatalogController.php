<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Category;
use App\Models\Product;

#[Group('Catalog', "Mijoz uchun menyu: umumiy lenta yoki bitta do'kon menyusi.")]
class CatalogController extends Controller
{
    use ApiResponse;

    /**
     * Menu: global feed or one store
     *
     * Without `store_id`: the global feed (up to 50 random active products of open stores near the location, the
     * categories, the promo banners and a highlighted product). With `store_id`: that store (branches, ratings) with
     * its active products and their categories; 404 for an unknown store, 403 when the store is unavailable.
     * Without a location the user's default address is used.
     */
    #[QueryParameter('store_id', description: "The store's uuid (or numeric id): store menu instead of the global feed.", type: 'string')]
    #[QueryParameter('lat', description: 'Global feed: latitude of the delivery point (with `lng`).', type: 'float')]
    #[QueryParameter('lng', description: 'Global feed: longitude of the delivery point (with `lat`).', type: 'float')]
    #[QueryParameter('region_id', description: 'Global feed without coordinates: stores with a branch in this region.', type: 'int')]
    #[QueryParameter('district_id', description: 'Global feed without coordinates: stores with a branch in this district.', type: 'int')]
    #[QueryParameter('category_id', description: 'Global feed: only this category and its subcategories.', type: 'int')]
    public function index(Request $request): JsonResponse
    {
        $storeUuid = $request->query('store_id');
        
        // GLOBAL FEED MODE
        if (!$storeUuid) {
            $user = $request->user('sanctum') ?: $request->user();
            $lat = $request->query('lat');
            $lng = $request->query('lng');
            $regionId = $request->query('region_id');
            $districtId = $request->query('district_id');

            // Fallback to user default address if no parameters provided
            if (!$lat && !$lng && !$regionId && !$districtId && $user) {
                $defaultAddress = $user->addresses()->where('is_default', true)->first() 
                    ?: $user->addresses()->first();
                if ($defaultAddress) {
                    $lat = $defaultAddress->latitude;
                    $lng = $defaultAddress->longitude;
                    $regionId = $defaultAddress->region_id;
                    $districtId = $defaultAddress->district_id;
                }
            }

            $now = now()->setTimezone('Asia/Tashkent')->format('H:i:s');

            $query = Product::with(['store', 'variants' => function($q) {
                $q->where('is_active', true);
            }])
            ->where('status', 'active');

            // Location filtering on store and operating hours check
            $query->whereHas('store', function ($sq) use ($lat, $lng, $regionId, $districtId, $now) {
                $sq->where('is_active', true)
                   ->where('status', '!=', \App\Enums\StoreStatus::PAYMENT_REQUIRED->value)
                   ->where('is_open', true)
                   ->where(function ($q) use ($now) {
                       $q->whereNull('open_time')
                         ->orWhereNull('close_time')
                         ->orWhereColumn('open_time', 'close_time')
                         ->orWhere(function ($q2) use ($now) {
                             $q2->whereRaw('close_time > open_time')
                                ->whereRaw('? BETWEEN open_time AND close_time', [$now]);
                         })
                         ->orWhere(function ($q2) use ($now) {
                             $q2->whereRaw('close_time < open_time')
                                ->where(function ($q3) use ($now) {
                                    $q3->whereRaw('? >= open_time', [$now])
                                       ->orWhereRaw('? <= close_time', [$now]);
                                });
                         });
                   });

                if ($lat && $lng) {
                    $sq->whereHas('branches', function ($bq) use ($lat, $lng) {
                        $bq->whereRaw("
                            (6371 * acos(
                                cos(radians(?)) * cos(radians(latitude)) * 
                                cos(radians(longitude) - radians(?)) + 
                                sin(radians(?)) * sin(radians(latitude))
                            )) <= radius_km
                        ", [$lat, $lng, $lat]);
                    });
                } else {
                    if ($districtId) {
                        $sq->whereHas('branches', function ($bq) use ($districtId) {
                            $bq->where('district_id', $districtId);
                        });
                    } elseif ($regionId) {
                        $sq->whereHas('branches', function ($bq) use ($regionId) {
                            $bq->where('region_id', $regionId);
                        });
                    }
                }
            });

            $catId = $request->query('category_id');
            if ($catId) {
                $childIds = Category::where('parent_id', $catId)->pluck('id')->toArray();
                $allIds = array_merge([(int)$catId], $childIds);
                $query->whereIn('category_id', $allIds);
            }

            $products = $query->inRandomOrder()
            ->limit(50)
            ->get();
            
            $categories = Category::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'uuid', 'name', 'slug', 'parent_id', 'image']);
                
            $promos = \App\Models\Promo::with(['store', 'product.store'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($promo) {
                    $storeUuid = $promo->store?->uuid;
                    $productId = $promo->product_id;
                    if ($promo->product && $promo->product->store) {
                        $storeUuid = $promo->product->store->uuid;
                    }
                    return [
                        'id' => $promo->id,
                        'title' => $promo->title,
                        'image_url' => $promo->image_url,
                        'store_uuid' => $storeUuid,
                        'product_id' => $productId,
                    ];
                });
                
            return $this->success([
                'categories' => $categories,
                'products' => $products,
                'promos' => $promos,
                'highlight_product' => $products->first()
            ]);
        }

        // STORE-SPECIFIC MODE
        $queryField = 'uuid';
        if (is_numeric($storeUuid)) {
            $queryField = 'id';
        } elseif (!preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $storeUuid)) {
            return $this->error('Store not found.', [], 404);
        }

        $store = \App\Models\Store::with([
            'branches' => function ($q) {
                $q->select('id', 'store_id', 'name', 'latitude', 'longitude', 'radius_km', 'address', 'phone');
            },
            'ratings' => function ($q) {
                $q->with('user:id,name')->orderBy('created_at', 'desc');
            }
        ])->where($queryField, $storeUuid)->first();
            
        if (!$store || !$store->is_active || ($store->status->value ?? $store->status) === \App\Enums\StoreStatus::PAYMENT_REQUIRED->value) {
            return $this->error('Store is currently unavailable.', [], 403);
        }

        $user = $request->user('sanctum') ?: $request->user();
        if ($user) {
            // Same rule checkout enforces, so cash is hidden exactly when an order would be refused
            if (\App\Models\Order::cashPickupBanApplies($user->id)) {
                $settings = $store->payment_settings ?? [];
                if (isset($settings['cash'])) {
                    if (is_array($settings['cash'])) {
                        $settings['cash']['enabled'] = false;
                    } else {
                        $settings['cash'] = false;
                    }
                } else {
                    $settings['cash'] = false;
                }
                $store->payment_settings = $settings;
            }
        }

        $storeId = $store->id;

        $products = Product::with(['variants' => function($q) {
                $q->where('is_active', true);
            }])
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->get();
            
        $categoryIds = $products->pluck('category_id')->unique()->toArray();
        $childCategories = Category::whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->get(['id', 'uuid', 'name', 'slug', 'parent_id', 'image']);
            
        $parentIds = $childCategories->pluck('parent_id')->filter()->unique()->toArray();
        $parentCategories = Category::whereIn('id', $parentIds)
            ->where('is_active', true)
            ->get(['id', 'uuid', 'name', 'slug', 'parent_id', 'image']);
            
        $categories = $childCategories->merge($parentCategories)->sortBy('sort_order')->values();

        return $this->success([
            'store' => $store,
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
