<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_KEY = 'platform-click-key';

    protected Store $store;
    protected Branch $branch;
    protected ProductVariant $variant;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.click.secret_key' => self::PLATFORM_KEY]);

        $this->store = Store::forceCreate([
            'name' => 'Own Account Cafe',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'payment_account_type' => 'own_account',
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'click' => ['enabled' => true],
                'pickup_enabled' => true,
            ],
        ]);

        $this->branch = Branch::create([
            'store_id' => $this->store->id,
            'name' => 'Main',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
        ]);

        $product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => Category::create(['name' => 'Food', 'slug' => 'food', 'is_active' => true])->id,
            'name' => 'Plov',
            'slug' => 'plov',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Portion',
            'price' => 50000,
            'stock' => 10,
            'is_active' => true,
            'uuid' => (string) Str::uuid(),
        ]);

        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998908880001', 'password' => bcrypt('password')]);
    }

    private function placeOrder(string $method): Order
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => $method,
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ])->assertSuccessful();

        return Order::latest('id')->firstOrFail();
    }

    private function payByClick(Order $order, string $key)
    {
        return $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '5' . $order->id, 'service_id' => '1', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '50000', 'action' => 1, 'error' => 0, 'sign_time' => '2026-10-18 12:00:00',
            'sign_string' => md5('5' . $order->id . '1' . $key . $order->uuid . $order->id . '50000' . '1' . '2026-10-18 12:00:00'),
        ]);
    }

    private function deliver(Order $order): void
    {
        $order->update(['status' => OrderStatus::DELIVERED->value]);
        app(LedgerService::class)->processOrderDelivered($order->fresh());
    }

    public function test_own_account_without_click_credentials_is_platform_collected_and_settled_to_the_wallet(): void
    {
        // Merchant id/service id without the key is not enough to collect Click payments itself.
        $this->store->update(['payment_settings' => ['click' => ['enabled' => true, 'service_id' => 's', 'merchant_id' => 'm']]]);

        $order = $this->placeOrder('click');
        $this->assertSame('PLATFORM', $order->payment_owner);

        $this->payByClick($order, self::PLATFORM_KEY)->assertJson(['error' => 0]);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);

        $this->deliver($order);

        // The platform holds the money: the full total is credited to the wallet, the 10% commission becomes debt.
        $store = $this->store->fresh();
        $this->assertEquals(50000, (float) $store->wallet_balance);
        $this->assertEquals(5000, (float) $store->debt_balance);
    }

    public function test_own_account_with_complete_click_credentials_collects_its_own_payments(): void
    {
        $this->store->update(['payment_settings' => ['click' => ['enabled' => true, 'service_id' => 's', 'merchant_id' => 'm', 'secret_key' => 'store-click-key']]]);

        $order = $this->placeOrder('click');
        $this->assertSame('STORE', $order->payment_owner);

        $this->payByClick($order, self::PLATFORM_KEY)->assertJson(['error' => -1]);
        $this->payByClick($order, 'store-click-key')->assertJson(['error' => 0]);

        $this->deliver($order);

        // The money went to the store's own account: no wallet credit, only the commission becomes debt.
        $store = $this->store->fresh();
        $this->assertEquals(0, (float) $store->wallet_balance);
        $this->assertEquals(5000, (float) $store->debt_balance);
    }

    public function test_platform_account_and_cash_ownership_are_unchanged(): void
    {
        $this->assertSame('STORE', $this->placeOrder('cash')->payment_owner);

        $this->store->update(['payment_account_type' => 'platform_account']);
        $this->assertSame('PLATFORM', $this->placeOrder('click')->payment_owner);
    }
}
