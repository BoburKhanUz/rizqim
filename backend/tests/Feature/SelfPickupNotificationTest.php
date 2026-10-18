<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Branch;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Support\Facades\Http;

class SelfPickupNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $customer;
    protected TelegramNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:fake-token']);

        $this->notificationService = app(TelegramNotificationService::class);

        $this->store = Store::create([
            'name' => 'Test Cafe Name',
            'slug' => 'test-cafe-name',
            'address' => 'Test Cafe Main Address 456',
            'debt_balance' => 0,
            'wallet_balance' => 0,
            'credit_limit' => -100000,
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Mijoz Name',
            'phone' => '+998901112233',
            'telegram_id' => '987654321',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_pickup_order_notification_contains_cafe_details_and_phone(): void
    {
        Http::fake();

        // 1. Create a pickup order (order_type = pickup / olib ketish)
        $order = Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'status' => OrderStatus::ACCEPTED,
            'order_type' => 'pickup',
            'total_price' => 50000,
            'commission_amount' => 5000,
        ]);

        // 2. Create store manager with phone to check fallback phone logic
        User::create([
            'store_id' => $this->store->id,
            'name' => 'Store Manager',
            'phone' => '+998909998877',
            'role' => UserRole::MANAGER,
            'password' => bcrypt('password'),
        ]);

        $this->notificationService->sendOrderStatusChanged($order);

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';
            return str_contains($text, 'Olib ketish manzili') &&
                   str_contains($text, 'Test Cafe Name') &&
                   str_contains($text, 'Test Cafe Main Address 456') &&
                   str_contains($text, '+998909998877');
        });
    }

    public function test_pickup_order_notification_gives_the_store_phone_when_the_branch_has_none(): void
    {
        Http::fake();

        $this->store->update(['phone' => '+998712223344']);
        $branch = Branch::create([
            'store_id' => $this->store->id,
            'name' => 'Yunusobod Branch',
            'address' => 'Yunusobod 4, Cafe 7',
            'status' => \App\Enums\BranchStatus::ACTIVE,
        ]);
        User::create([
            'store_id' => $this->store->id,
            'name' => 'Store Manager',
            'phone' => '+998909998877',
            'role' => UserRole::MANAGER,
            'password' => bcrypt('password'),
        ]);

        $order = Order::create([
            'store_id' => $this->store->id,
            'branch_id' => $branch->id,
            'user_id' => $this->customer->id,
            'status' => OrderStatus::ACCEPTED,
            'order_type' => 'pickup',
            'total_price' => 40000,
            'commission_amount' => 4000,
        ]);

        $this->notificationService->sendOrderStatusChanged($order);

        // The store's own number before a staff member's personal one
        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';
            return str_contains($text, 'Olib ketish manzili') &&
                   str_contains($text, 'Yunusobod 4, Cafe 7') &&
                   str_contains($text, '+998712223344') &&
                   !str_contains($text, '+998909998877');
        });
    }

    public function test_pickup_order_notification_with_branch_details(): void
    {
        Http::fake();

        // 1. Create a branch with specific address and phone
        $branch = Branch::create([
            'store_id' => $this->store->id,
            'name' => 'Chilonzor Branch',
            'address' => 'Chilonzor block 5, Cafe 12',
            'phone' => '+998935554433',
            'status' => \App\Enums\BranchStatus::ACTIVE,
        ]);

        $order = Order::create([
            'store_id' => $this->store->id,
            'branch_id' => $branch->id,
            'user_id' => $this->customer->id,
            'status' => OrderStatus::DELIVERED,
            'order_type' => 'pickup',
            'total_price' => 30000,
            'commission_amount' => 3000,
        ]);

        $this->notificationService->sendOrderStatusChanged($order);

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';
            return str_contains($text, 'Buyurtmangiz tayyor, olib ketishingiz mumkin') &&
                   str_contains($text, 'Chilonzor Branch') &&
                   str_contains($text, 'Chilonzor block 5, Cafe 12') &&
                   str_contains($text, '+998935554433');
        });
    }
}
