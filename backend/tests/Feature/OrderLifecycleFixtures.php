<?php

namespace Tests\Feature;

use App\Actions\Order\CreateOrderAction;
use App\Enums\BranchStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A store with two branches, its staff (owner, branch manager, branch courier), a customer and a stocked product.
 */
trait OrderLifecycleFixtures
{
    protected Store $store;
    protected Branch $branch;
    protected Branch $otherBranch;
    protected User $owner;
    protected User $manager;
    protected User $courier;
    protected User $customer;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUpLifecycleStore(): void
    {
        $this->store = $this->makeStore('Lifecycle Burger');
        $this->branch = $this->makeBranch($this->store, 'Main Branch');
        $this->otherBranch = $this->makeBranch($this->store, 'Second Branch');

        $this->owner = $this->makeStaff(UserRole::OWNER);
        $this->manager = $this->makeStaff(UserRole::MANAGER, $this->branch);
        $this->courier = $this->makeStaff(UserRole::COURIER, $this->branch);
        $this->customer = $this->makeCustomer();

        $category = Category::create(['name' => 'Burgers', 'slug' => 'burgers-' . Str::random(5), 'is_active' => true]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cheeseburger',
            'slug' => 'cheeseburger-' . Str::random(5),
            'status' => 'active',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Regular',
            'price' => 50000,
            'stock' => 10,
            'is_active' => true,
            'uuid' => (string) Str::uuid(),
        ]);
    }

    protected function makeStore(string $name): Store
    {
        return Store::forceCreate([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'click' => ['enabled' => true],
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'delivery_fee' => 0,
            ],
        ]);
    }

    protected function makeBranch(Store $store, string $name): Branch
    {
        return Branch::create([
            'store_id' => $store->id,
            'name' => $name,
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
        ]);
    }

    protected function makeStaff(UserRole $role, ?Branch $branch = null, ?Store $store = null, array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        // Staff who joined through their invite (a real Telegram account, no pending invite token)
        return User::create(array_merge([
            'name' => ucfirst($role->value) . " {$sequence}",
            'phone' => '+99890' . str_pad((string) (7000000 + $sequence), 7, '0', STR_PAD_LEFT),
            'password' => bcrypt('password'),
            'store_id' => ($store ?? $this->store)->id,
            'branch_id' => $branch?->id,
            'role' => $role->value,
            'telegram_id' => (string) (700000000 + $sequence),
        ], $attributes));
    }

    protected function makeCustomer(float $balance = 0, array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        $user = User::create(array_merge([
            'name' => "Customer {$sequence}",
            'phone' => '+99891' . str_pad((string) (8000000 + $sequence), 7, '0', STR_PAD_LEFT),
            'password' => bcrypt('password'),
        ], $attributes));

        if ($balance > 0) {
            DB::table('users')->where('id', $user->id)->update(['balance' => $balance]);
            $user->refresh();
        }

        return $user;
    }

    /**
     * An order row in the given state, without going through checkout.
     */
    protected function makeOrder(OrderStatus $status, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->customer->id,
            'status' => $status,
            'order_type' => 'delivery',
            'payment_method' => 'cash',
            'total_price' => 50000,
            'commission_amount' => 5000,
            'shipping_address' => ['address' => 'Amir Temur 1', 'latitude' => 41.311081, 'longitude' => 69.240562],
        ], $attributes));
    }

    /**
     * A real checkout (stock, keshbek and payment rows exactly as production creates them).
     */
    protected function checkout(User $customer, int $quantity = 2, string $paymentMethod = 'cash', string $type = 'pickup', ?string $promoCode = null): Order
    {
        $payload = [
            'store_id' => $this->store->id,
            'type' => $type,
            'payment_method' => $paymentMethod,
            'promo_code' => $promoCode,
        ];
        $payload += $type === 'pickup'
            ? ['branch_id' => $this->branch->id]
            : ['address' => 'Amir Temur 1', 'latitude' => 41.311081, 'longitude' => 69.240562];

        return app(CreateOrderAction::class)->execute(
            $customer,
            [['variant_id' => $this->variant->uuid, 'quantity' => $quantity]],
            $payload
        );
    }

    protected function setStatus(Order $order, User $actor, string $status, array $extra = [])
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/status", ['status' => $status] + $extra);
    }
}
