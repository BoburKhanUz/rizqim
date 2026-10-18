<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\UserRole;

class SuperadminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_dashboard_returns_correct_stats_for_delivered_orders(): void
    {
        // 1. Create a store with 10% commission rate
        $store = Store::create([
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'address' => 'Chilonzor',
            'balance' => 0,
            'wallet_balance' => 0,
            'credit_limit' => -50000,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        // 2. Create a superadmin user
        $superadmin = User::create([
            'name' => 'Super Admin User',
            'phone' => '+998991112233',
            'role' => UserRole::SUPER_ADMIN,
            'password' => bcrypt('password'),
        ]);

        // 3. Create a delivered order (total = 100,000 UZS, commission = 10,000 UZS)
        Order::create([
            'store_id' => $store->id,
            'user_id' => $superadmin->id, // just reuse the user
            'status' => OrderStatus::DELIVERED,
            'order_type' => 'delivery',
            'total_price' => 100000,
            'commission_amount' => 10000,
            'created_at' => now(),
        ]);

        // 4. Create an active (non-delivered) order to make sure it is not counted in GMV
        Order::create([
            'store_id' => $store->id,
            'user_id' => $superadmin->id,
            'status' => OrderStatus::ACCEPTED,
            'order_type' => 'delivery',
            'total_price' => 50000,
            'commission_amount' => 5000,
            'created_at' => now(),
        ]);

        // 5. Call dashboard API
        $response = $this->actingAs($superadmin)->getJson('/api/v1/superadmin/dashboard');

        // 6. Assertions
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_stores' => 1,
                'active_stores' => 1,
                'payment_required_stores' => 0,
                'total_users' => 1,
                'total_gmv' => 100000,
                'total_commission' => 10000,
                'monthly_gmv' => 100000,
                'monthly_commission' => 10000,
                'last_30_days_gmv' => 100000,
                'last_30_days_commission' => 10000,
                'today_gmv' => 100000,
                'today_orders' => 1,
                'today_commission' => 10000,
                'pending_payouts' => 0,
                'total_store_debt' => 0,
                'total_cashback_balance' => 0,
            ]
        ]);
    }
}
