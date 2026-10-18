<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperadminController extends Controller
{
    /**
     * Get regions and districts for forms
     */
    public function getLocations(Request $request)
    {
        if ($request->user()->role !== \App\Enums\UserRole::SUPER_ADMIN) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $regions = \App\Models\Region::where('is_active', true)->get(['id', 'name_uz as name']);
        $districts = \App\Models\District::where('is_active', true)->get(['id', 'region_id', 'name_uz as name']);

        return response()->json([
            'success' => true,
            'data' => [
                'regions' => $regions,
                'districts' => $districts,
            ]
        ]);
    }

    /**
     * Get global dashboard stats for Super Admin
     */
    public function dashboard(Request $request)
    {
        if ($request->user()->role !== \App\Enums\UserRole::SUPER_ADMIN) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $totalStores = Store::count();
        $activeStores = Store::where('status', \App\Enums\StoreStatus::ACTIVE->value)->count();
        $paymentRequiredStores = Store::where('status', \App\Enums\StoreStatus::PAYMENT_REQUIRED->value)->count();
        
        $totalUsers = User::count();
        
        $orders = Order::where('status', 'delivered')->get();
        $totalGmv = $orders->sum('total_price');
        
        // Calculate platform commission based on store commission_rate
        $totalCommission = $orders->sum(function ($order) {
            $store = $order->store;
            if (!$store) return 0;
            return ($order->total_price * $store->commission_rate) / 100;
        });

        // Get monthly GMV & Commission
        $monthlyOrders = Order::where('status', 'delivered')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();
        $monthlyGmv = $monthlyOrders->sum('total_price');
        $monthlyCommission = $monthlyOrders->sum(function ($order) {
            $store = $order->store;
            if (!$store) return 0;
            return ($order->total_price * $store->commission_rate) / 100;
        });

        // Get last 30 days GMV & Commission
        $last30DaysOrders = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
        $last30DaysGmv = $last30DaysOrders->sum('total_price');
        $last30DaysCommission = $last30DaysOrders->sum(function ($order) {
            $store = $order->store;
            if (!$store) return 0;
            return ($order->total_price * $store->commission_rate) / 100;
        });

        // Today's Stats
        $todayOrdersQuery = Order::where('status', 'delivered')->whereDate('created_at', now()->toDateString())->get();
        $todayGmv = $todayOrdersQuery->sum('total_price');
        $todayOrdersCount = $todayOrdersQuery->count();
        $todayCommission = $todayOrdersQuery->sum(function ($order) {
            $store = $order->store;
            if (!$store) return 0;
            return ($order->total_price * $store->commission_rate) / 100;
        });

        // Additional Platform Metrics
        $pendingPayouts = \App\Models\PayoutRequest::where('status', 'pending')->count();
        $totalStoreDebt = Store::where('debt_balance', '>', 0)->sum('debt_balance');
        $totalCashbackBalance = User::where('role', \App\Enums\UserRole::CUSTOMER->value)->sum('balance');

        return response()->json([
            'success' => true,
            'data' => [
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'payment_required_stores' => $paymentRequiredStores,
                'total_users' => $totalUsers,
                'total_gmv' => $totalGmv,
                'total_commission' => $totalCommission,
                'monthly_gmv' => $monthlyGmv,
                'monthly_commission' => $monthlyCommission,
                'last_30_days_gmv' => $last30DaysGmv,
                'last_30_days_commission' => $last30DaysCommission,
                'today_gmv' => $todayGmv,
                'today_orders' => $todayOrdersCount,
                'today_commission' => $todayCommission,
                'pending_payouts' => $pendingPayouts,
                'total_store_debt' => $totalStoreDebt,
                'total_cashback_balance' => $totalCashbackBalance,
            ]
        ]);
    }

    /**
     * Get all global stores
     */
    public function stores(Request $request)
    {
        if ($request->user()->role !== \App\Enums\UserRole::SUPER_ADMIN) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stores = Store::withCount('branches')->latest()->get()->map(function ($store) {
            $owner = User::where('store_id', $store->id)->where('role', 'owner')->first();
            
            $botUsername = env('TELEGRAM_BOT_USERNAME', 'foods_order_test_bot');
            $inviteLink = null;
            if ($owner && $owner->telegram_invite_token) {
                $inviteLink = "https://t.me/{$botUsername}?start=invite_{$owner->telegram_invite_token}";
            }

            return [
                'id' => $store->id,
                'uuid' => $store->uuid,
                'name' => $store->name,
                'slug' => $store->slug,
                'is_active' => $store->is_active,
                'commission_rate' => $store->commission_rate,
                'branches_count' => $store->branches_count,
                'created_at' => $store->created_at,
                'owner' => $owner ? [
                    'name' => $owner->name,
                    'phone' => $owner->phone,
                    'invite_link' => $inviteLink,
                    'has_joined' => !empty($owner->telegram_id)
                ] : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }

    /**
     * Create a new Global Store
     */
    public function createStore(Request $request)
    {
        if ($request->user()->role !== \App\Enums\UserRole::SUPER_ADMIN) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'owner_name' => 'required|string|max:255',
            'owner_phone' => 'nullable|string|max:255',
            'branch_region_id' => 'required|integer|exists:regions,id',
            'branch_district_id' => 'required|integer|exists:districts,id',
            'branch_address' => 'required|string|max:1000',
            'branch_phone' => 'nullable|string|max:255',
            'branch_lat' => 'required|numeric',
            'branch_lng' => 'required|numeric',
            'branch_radius' => 'required|numeric|min:1',
            'branch_open_time' => 'nullable|string',
            'branch_close_time' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. Create the Store
            $store = Store::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . rand(1000, 9999),
                'uuid' => Str::uuid(),
                'phone' => $validated['phone'],
                'commission_rate' => $validated['commission_rate'],
                // What a store without settings got before (cash only), written out in full
                'payment_settings' => Store::initialPaymentSettings(['cash']),
                'is_active' => true,
            ]);

            // 2. Create the Owner User
            $ownerToken = Str::uuid()->toString();
            $owner = User::create([
                'name' => $validated['owner_name'],
                'phone' => $validated['owner_phone'],
                'role' => 'owner',
                'store_id' => $store->id,
                'telegram_invite_token' => $ownerToken,
            ]);

            // 3. Create the Main Branch
            Branch::create([
                'store_id' => $store->id,
                'region_id' => $validated['branch_region_id'],
                'district_id' => $validated['branch_district_id'],
                'name' => 'Asosiy Filial',
                'address' => $validated['branch_address'],
                'phone' => $validated['branch_phone'],
                'latitude' => $validated['branch_lat'],
                'longitude' => $validated['branch_lng'],
                'radius_km' => $validated['branch_radius'],
                'open_time' => $validated['branch_open_time'] ?? null,
                'close_time' => $validated['branch_close_time'] ?? null,
                'is_active' => true,
            ]);

            DB::commit();
            
            $botUsername = env('TELEGRAM_BOT_USERNAME', 'foods_order_test_bot');
            $inviteLink = "https://t.me/{$botUsername}?start=invite_{$ownerToken}";

            // Notify Super Admins via Telegram
            $telegram = app(\App\Services\Telegram\TelegramMessenger::class);
            if ($telegram->isConfigured()) {
                $superAdmins = User::where('role', \App\Enums\UserRole::SUPER_ADMIN)
                    ->whereNotNull('telegram_id')
                    ->get();

                foreach ($superAdmins as $admin) {
                    $telegram->sendMessage($admin->telegram_id, "🏢 <b>Yangi do'kon qo'shildi!</b>\n\n" .
                        "Nomi: <b>" . htmlspecialchars($store->name) . "</b>\n" .
                        "Komissiya: <b>{$store->commission_rate}%</b>\n" .
                        "Egasi: <b>" . htmlspecialchars($owner->name) . "</b> (" . htmlspecialchars($owner->phone) . ")\n" .
                        "Filial: <b>" . htmlspecialchars($validated['branch_address']) . "</b>\n\n" .
                        "🔗 <b>Egasi uchun taklifnoma havolasi:</b>\n{$inviteLink}", ['parse_mode' => 'HTML']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Store created successfully',
                'data' => [
                    'store_id' => $store->id,
                    'invite_link' => $inviteLink
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create store',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
