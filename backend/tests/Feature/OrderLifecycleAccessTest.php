<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderLifecycleAccessTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_courier_order_list_only_holds_their_own_deliveries(): void
    {
        $mine = $this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $this->courier->id]);
        $unassigned = $this->makeOrder(OrderStatus::NEW);
        $othersCourier = $this->makeStaff(UserRole::COURIER, $this->branch);
        $others = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $othersCourier->id]);
        // Assigned across branches by the owner: still theirs
        $otherBranch = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id, 'branch_id' => $this->otherBranch->id]);

        $uuids = collect($this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/orders')->assertOk()->json('data'))->pluck('uuid');

        $this->assertEqualsCanonicalizing([$mine->uuid, $otherBranch->uuid], $uuids->all());
        $this->assertNotContains($unassigned->uuid, $uuids);
        $this->assertNotContains($others->uuid, $uuids);
    }

    public function test_manager_order_list_follows_branch_scope(): void
    {
        $here = $this->makeOrder(OrderStatus::NEW);
        $there = $this->makeOrder(OrderStatus::NEW, ['branch_id' => $this->otherBranch->id]);
        $storeWideManager = $this->makeStaff(UserRole::MANAGER);

        $branchList = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/orders')->json('data'))->pluck('uuid')->all();
        $storeList = collect($this->actingAs($storeWideManager, 'sanctum')->getJson('/api/v1/orders')->json('data'))->pluck('uuid')->all();

        $this->assertSame([$here->uuid], $branchList);
        $this->assertEqualsCanonicalizing([$here->uuid, $there->uuid], $storeList);
    }

    public function test_courier_list_is_staff_only_and_scoped_to_the_store(): void
    {
        $otherStore = $this->makeStore('Other Store');
        $foreignCourier = $this->makeStaff(UserRole::COURIER, null, $otherStore);
        $otherBranchCourier = $this->makeStaff(UserRole::COURIER, $this->otherBranch);

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/couriers')->assertForbidden();
        $this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/couriers')->assertForbidden();

        $ownerList = collect($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/couriers')->assertOk()->json('data'))->pluck('id');
        $managerList = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/couriers')->assertOk()->json('data'))->pluck('id');

        $this->assertNotContains($foreignCourier->id, $ownerList);
        $this->assertContains($otherBranchCourier->id, $ownerList);
        $this->assertEqualsCanonicalizing([$this->owner->id, $this->manager->id, $this->courier->id], $managerList->all());
    }

    public function test_report_export_is_for_the_stores_owner_and_managers(): void
    {
        config(['services.telegram.bot_token' => '123456:fake-token']);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->owner->update(['telegram_id' => '5003']);
        $this->makeOrder(OrderStatus::DELIVERED, ['short_code' => 'HERE01']);
        $this->makeOrder(OrderStatus::DELIVERED, ['short_code' => 'THERE01', 'branch_id' => $this->otherBranch->id]);
        $this->manager->update(['telegram_id' => '5001']);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'today'])->assertForbidden();
        $this->actingAs($this->courier, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'today'])->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'today'])->assertOk();
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'today'])->assertOk();

        $sent = Http::recorded()->map(fn ($pair) => $pair[0])
            ->filter(fn ($request) => str_contains($request->url(), 'sendDocument'))
            ->mapWithKeys(fn ($request) => [collect($request->data())->firstWhere('name', 'chat_id')['contents'] => $request->body()]);

        $this->assertStringContainsString('THERE01', $sent['5003']);
        $this->assertStringContainsString('HERE01', $sent['5001']);
        $this->assertStringNotContainsString('THERE01', $sent['5001']);
    }

    public function test_order_creation_returns_the_human_order_number(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::where('uuid', $response->json('data.order_uuid'))->sole();
        $response->assertJsonPath('data.order_number', $order->short_code);
    }

    public function test_cash_is_offered_in_the_ui_exactly_when_checkout_accepts_it(): void
    {
        $storeEnablesCash = fn ($user) => (bool) data_get(
            collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/stores')->assertOk()->json('data'))->firstWhere('id', $this->store->id),
            'payment_settings.cash.enabled'
        );
        $catalogEnablesCash = fn ($user) => (bool) $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk()->json('data.store.payment_settings.cash.enabled');

        // Refused by the store while still new: not the customer's fault, cash stays available (old UI hid it forever)
        $refused = $this->makeOrder(OrderStatus::REJECTED, ['order_type' => 'pickup']);
        OrderStatusHistory::create(['order_id' => $refused->id, 'user_id' => $this->manager->id, 'status' => 'rejected']);

        $this->assertTrue($storeEnablesCash($this->customer));
        $this->assertTrue($catalogEnablesCash($this->customer));
        $this->checkout($this->customer, 1);

        // Cancelled by the customer: cash is banned, in the UI and at checkout alike
        $banned = $this->makeCustomer();
        $cancelled = $this->makeOrder(OrderStatus::CANCELED, ['order_type' => 'pickup', 'user_id' => $banned->id]);
        OrderStatusHistory::create(['order_id' => $cancelled->id, 'user_id' => $banned->id, 'status' => 'canceled']);

        $this->assertFalse($storeEnablesCash($banned));
        $this->assertFalse($catalogEnablesCash($banned));
        $this->expectExceptionMessage('naqd pulda buyurtma berish taqiqlangan');
        $this->checkout($banned, 1);
    }

    public function test_catalog_hides_a_store_that_must_pay_its_debt(): void
    {
        $this->store->forceFill(['status' => 'payment_required'])->save();

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/catalog?store_id={$this->store->uuid}")
            ->assertForbidden();
    }
}
