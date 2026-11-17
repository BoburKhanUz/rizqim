<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

use App\Models\Store;

#[Group('Stores', "Do'konlar ro'yxati va baholash.")]
class StoreController extends Controller
{
    /**
     * Stores near the customer
     *
     * Active stores with a branch that delivers to the point (nearest first, `min_distance` in km), or with a branch
     * in the region/district. Without parameters the user's default address is used. Cash is shown as disabled when
     * the customer may not pay cash.
     *
     * Wave M2: answered from the marketplace read model (App\Domain\Marketplace\MarketplaceBrowse, docs/API.md "Market
     * o'qish modeli"): the same cards plus `accepting_orders` (open now, not order-locked, reachable). With `page` or
     * `per_page` one page is answered, with `meta` {page, per_page, total, last_page}; without them every store, as
     * before. `for=checkout` (the cart) reads the database, as before. This method is the read model's fallback.
     */
    #[QueryParameter('page', description: 'Wave M2: the page (from 1); with it or `per_page` the answer is one page and carries `meta`.', type: 'int', required: false)]
    #[QueryParameter('per_page', description: 'Wave M2: stores per page (default 20, at most 50).', type: 'int', required: false)]
    #[QueryParameter('lat', description: 'Latitude of the delivery point (with `lng`).', type: 'float')]
    #[QueryParameter('lng', description: 'Longitude of the delivery point (with `lat`).', type: 'float')]
    #[QueryParameter('region_id', description: 'Without coordinates: stores with a branch in this region.', type: 'int')]
    #[QueryParameter('district_id', description: 'Without coordinates: stores with a branch in this district.', type: 'int')]
    public function index(Request $request)
    {
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

        $query = Store::query()
            ->select('id', 'uuid', 'name', 'phone', 'address', 'logo', 'banner', 'description', 'is_open', 'is_premium', 'review_count', 'payment_settings', 'status', 'debt_balance', 'subscription_debt', 'credit_limit', 'open_time', 'close_time', 'min_order_amount') // P4-21: subscription_debt, so is_order_locked counts only the commission debt
            ->where('is_active', true)
            ->where('status', '!=', \App\Enums\StoreStatus::PAYMENT_REQUIRED->value)
            ->with(['branches' => function ($q) {
                $q->select('id', 'store_id', 'name', 'latitude', 'longitude', 'radius_km', 'address', 'phone');
            }]);

        // Filter by Radius (if coords are present)
        if ($lat && $lng) {
            $query->whereHas('branches', function ($q) use ($lat, $lng) {
                $q->whereRaw("
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(latitude)) * 
                        cos(radians(longitude) - radians(?)) + 
                        sin(radians(?)) * sin(radians(latitude))
                    )) <= radius_km
                ", [$lat, $lng, $lat]);
            });

            // Include distance to nearest branch
            $query->withCount([
                'branches as min_distance' => function ($query) use ($lat, $lng) {
                    $query->select(\DB::raw(
                        "MIN(
                            6371 * acos(
                                cos(radians(?)) * cos(radians(latitude)) * 
                                cos(radians(longitude) - radians(?)) + 
                                sin(radians(?)) * sin(radians(latitude))
                            )
                        )"
                    ))->setBindings([$lat, $lng, $lat]);
                }
            ]);
            $query->orderBy('min_distance');
        } else {
            // Filter by Region/District fallback
            if ($districtId) {
                $query->whereHas('branches', function ($q) use ($districtId) {
                    $q->where('district_id', $districtId);
                });
            } elseif ($regionId) {
                $query->whereHas('branches', function ($q) use ($regionId) {
                    $q->where('region_id', $regionId);
                });
            }
        }

        // A deterministic order (heap order changes with every update): id, after the distance when there is one; the
        // read model (App\Domain\Marketplace\StoreDirectory) answers in the same order
        $query->orderBy('id');

        $stores = $query->get();

        $user = $request->user('sanctum') ?: $request->user();
        if ($user) {
            // Same rule checkout enforces, so cash is hidden exactly when an order would be refused
            if (\App\Models\Order::cashPickupBanApplies($user->id)) {
                $stores->each(function ($store) {
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
                });
            }
        }
            
        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }
}
