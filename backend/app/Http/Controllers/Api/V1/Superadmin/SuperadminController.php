<?php

namespace App\Http\Controllers\Api\V1\Superadmin;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Group('Superadmin', "Platforma boshqaruvi (faqat superadmin).")]
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

        // P2-30: plain {id, name} rows (the Uzbek name), so the OpenAPI document says what is sent
        $regions = \App\Models\Region::where('is_active', true)->get(['id', 'name_uz'])
            ->map(fn (\App\Models\Region $region) => ['id' => (int) $region->id, 'name' => (string) $region->name_uz])->values();
        $districts = \App\Models\District::where('is_active', true)->get(['id', 'region_id', 'name_uz'])
            ->map(fn (\App\Models\District $district) => ['id' => (int) $district->id, 'region_id' => (int) $district->region_id, 'name' => (string) $district->name_uz])->values();

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
        
        // P1-23: the commission each order was charged (its snapshot), not today's store rate, which a tariff plan may override
        $totalCommission = \App\Support\Money::sum(...$orders->pluck('commission_amount')->all())->toFloat();

        // Get monthly GMV & Commission
        $monthlyOrders = Order::where('status', 'delivered')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();
        $monthlyGmv = $monthlyOrders->sum('total_price');
        $monthlyCommission = \App\Support\Money::sum(...$monthlyOrders->pluck('commission_amount')->all())->toFloat();

        // Get last 30 days GMV & Commission
        $last30DaysOrders = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
        $last30DaysGmv = $last30DaysOrders->sum('total_price');
        $last30DaysCommission = \App\Support\Money::sum(...$last30DaysOrders->pluck('commission_amount')->all())->toFloat();

        // Today's Stats
        $todayOrdersQuery = Order::where('status', 'delivered')->whereDate('created_at', now()->toDateString())->get();
        $todayGmv = $todayOrdersQuery->sum('total_price');
        $todayOrdersCount = $todayOrdersQuery->count();
        $todayCommission = \App\Support\Money::sum(...$todayOrdersQuery->pluck('commission_amount')->all())->toFloat();

        // Additional Platform Metrics
        $pendingPayouts = \App\Models\PayoutRequest::where('status', 'pending')->count();
        // P2-30: numbers, like the other money figures here (a SQL sum arrives as a string)
        $totalStoreDebt = \App\Support\Money::of(Store::where('debt_balance', '>', 0)->sum('debt_balance'))->toFloat();
        $totalCashbackBalance = \App\Support\Money::of(User::where('role', \App\Enums\UserRole::CUSTOMER->value)->sum('balance'))->toFloat();

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
            
            $botUsername = config('services.telegram.bot_username');
            // Null once the owner has joined (the invite is spent)
            $inviteLink = $owner && $owner->telegram_invite_token ? "https://t.me/{$botUsername}?start=invite_{$owner->telegram_invite_token}" : null;

            return [
                'id' => $store->id,
                'uuid' => $store->uuid,
                'name' => $store->name,
                'phone' => $store->phone,
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
                'phone' => $validated['phone'] ?? null,
                'commission_rate' => $validated['commission_rate'],
                // What a store without settings got before (cash only), written out in full
                'payment_settings' => Store::initialPaymentSettings(['cash']),
                'is_active' => true,
            ]);

            // 2. Create the Owner User
            $ownerToken = Str::uuid()->toString();
            $owner = User::create([
                'name' => $validated['owner_name'],
                'phone' => $validated['owner_phone'] ?? null,
                'role' => 'owner',
                'store_id' => $store->id,
                'telegram_invite_token' => $ownerToken,
            ]);
            // P1-36: a +998 number from the platform is the owner's login phone (SMS sign-in to the partner panel);
            // anything else stays a contact detail
            if (is_string($owner->phone) && preg_match(\App\Services\Auth\StaffPhoneLoginService::PHONE_PATTERN, $owner->phone)) {
                app(\App\Services\Auth\StaffPhoneLoginService::class)->vouch($owner, $owner->phone);
            }

            // 3. Create the Main Branch
            Branch::create([
                'store_id' => $store->id,
                'region_id' => $validated['branch_region_id'],
                'district_id' => $validated['branch_district_id'],
                'name' => 'Asosiy Filial',
                'address' => $validated['branch_address'],
                'phone' => $validated['branch_phone'] ?? null,
                'latitude' => $validated['branch_lat'],
                'longitude' => $validated['branch_lng'],
                'radius_km' => $validated['branch_radius'],
                'open_time' => $validated['branch_open_time'] ?? null,
                'close_time' => $validated['branch_close_time'] ?? null,
                'is_active' => true,
            ]);

            DB::commit();
            
            $botUsername = config('services.telegram.bot_username');
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
                        "Egasi: <b>" . htmlspecialchars($owner->name) . "</b> (" . htmlspecialchars((string) $owner->phone) . ")\n" .
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
