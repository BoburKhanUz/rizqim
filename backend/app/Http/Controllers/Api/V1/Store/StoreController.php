<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Store;

class StoreController extends Controller
{
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
            ->select('id', 'uuid', 'name', 'address', 'logo', 'banner', 'description', 'is_open', 'is_premium', 'review_count', 'payment_settings', 'status', 'balance', 'credit_limit', 'open_time', 'close_time', 'min_order_amount')
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
