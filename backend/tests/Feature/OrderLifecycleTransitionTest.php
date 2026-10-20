<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\StoreTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTransitionTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_delivery_order_walks_the_manager_and_courier_flow_and_settles_once(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->setStatus($order, $this->manager, 'preparing')->assertOk();
        $this->setStatus($order, $this->manager, 'ready')->assertOk();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'courier_assigned')
            ->assertJsonPath('data.courier_id', $this->courier->id);

        $this->setStatus($order, $this->courier, 'on_the_way')->assertOk();
        $this->actingAs($this->courier, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        // A repeated "delivered" (double tap) changes nothing and charges nothing again
        $this->actingAs($this->courier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/complete")->assertOk();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)
            ->where('type', StoreTransactionType::COMMISSION->value)->count());
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        $this->assertSame(
            ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way'],
            OrderStatusHistory::where('order_id', $order->id)->orderBy('id')->pluck('from_status')->map(fn ($status) => $status?->value)->all()
        );
        $history = OrderStatusHistory::where('order_id', $order->id)->where('status', 'courier_assigned')->first();
        $this->assertSame($this->manager->id, $history->user_id);
        $this->assertStringContainsString($this->courier->name, $history->notes);
        $this->assertTrue(OrderAssignment::where('order_id', $order->id)->where('courier_id', $this->courier->id)->where('is_current', true)->exists());
    }

    public function test_pickup_order_is_handed_over_from_ready(): void
    {
        $order = $this->makeOrder(OrderStatus::READY, ['order_type' => 'pickup']);

        $this->setStatus($order, $this->manager, 'courier_assigned')->assertStatus(422);
        $this->setStatus($order, $this->manager, 'delivered')->assertOk();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)->count());
    }

    public function test_illegal_transitions_are_rejected_with_422(): void
    {
        $delivered = $this->makeOrder(OrderStatus::DELIVERED);
        $canceled = $this->makeOrder(OrderStatus::CANCELED);
        $preparing = $this->makeOrder(OrderStatus::PREPARING);

        $this->setStatus($delivered, $this->manager, 'preparing')
            ->assertStatus(422)
            ->assertJsonPath('message', "Buyurtmani «Yetkazildi» holatidan «Tayyorlanmoqda» holatiga o'tkazib bo'lmaydi.");
        $this->setStatus($canceled, $this->owner, 'accepted')->assertStatus(422);
        // A delivery order must go through a courier
        $this->setStatus($preparing, $this->manager, 'delivered')->assertStatus(422);
        $this->setStatus($delivered, $this->owner, 'canceled')->assertStatus(422);

        $this->assertSame(OrderStatus::DELIVERED, $delivered->fresh()->status);
        $this->assertSame(OrderStatus::CANCELED, $canceled->fresh()->status);
        $this->assertSame(OrderStatus::PREPARING, $preparing->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::count());
        $this->assertSame(0, StoreTransaction::count());
    }

    public function test_courier_cannot_set_kitchen_statuses(): void
    {
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);

        $this->setStatus($order, $this->courier, 'preparing')->assertForbidden();
        $this->setStatus($order, $this->courier, 'canceled')->assertForbidden();
        $this->setStatus($order, $this->courier, 'courier_assigned', ['courier_id' => $this->manager->id])->assertForbidden();
        $this->actingAs($this->courier, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])
            ->assertForbidden();

        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status);

        // ...but moves their own delivery along
        $this->setStatus($order, $this->courier, 'on_the_way')->assertOk();
    }

    public function test_courier_cannot_act_on_another_couriers_order(): void
    {
        $otherCourier = $this->makeStaff(UserRole::COURIER, $this->branch);
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $otherCourier->id]);
        $unassigned = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->courier, 'delivered')->assertNotFound();
        $this->actingAs($this->courier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/complete")->assertNotFound();
        $this->setStatus($unassigned, $this->courier, 'accepted')->assertNotFound();

        $this->assertSame(OrderStatus::ON_THE_WAY, $order->fresh()->status);
        $this->assertSame(OrderStatus::NEW, $unassigned->fresh()->status);
        $this->assertSame(0, StoreTransaction::count());
    }

    public function test_manager_of_another_store_or_branch_cannot_touch_the_order(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $otherStore = $this->makeStore('Other Store');
        $foreignManager = $this->makeStaff(UserRole::MANAGER, $this->makeBranch($otherStore, 'Foreign'), $otherStore);
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $otherStore);
        $otherBranchManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);

        $this->setStatus($order, $foreignManager, 'accepted')->assertNotFound();
        $this->setStatus($order, $foreignOwner, 'rejected')->assertNotFound();
        $this->setStatus($order, $otherBranchManager, 'accepted')->assertNotFound();
        $this->actingAs($foreignManager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $foreignManager->id])
            ->assertNotFound();

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);

        // A store-wide manager (no branch) and the owner do reach it
        $storeManager = $this->makeStaff(UserRole::MANAGER);
        $this->setStatus($order, $storeManager, 'accepted')->assertOk();
        $this->setStatus($order, $this->owner, 'preparing')->assertOk();
    }

    public function test_customers_cannot_change_status(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->customer, 'canceled')->assertForbidden();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/complete")->assertForbidden();

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
    }

    public function test_repeating_the_current_status_is_a_harmless_no_op(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->setStatus($order, $this->manager, 'accepted')
            ->assertOk()
            ->assertJsonPath('message', 'Buyurtma allaqachon shu holatda.');

        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_store_can_cancel_an_accepted_order_but_cannot_reject_it(): void
    {
        $order = $this->makeOrder(OrderStatus::READY, ['order_type' => 'pickup']);

        $this->setStatus($order, $this->manager, 'rejected')->assertStatus(422);
        $this->setStatus($order, $this->manager, 'canceled')->assertOk();

        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
    }

    public function test_reassigning_a_waiting_courier_moves_the_current_assignment(): void
    {
        $order = $this->makeOrder(OrderStatus::READY);
        $secondCourier = $this->makeStaff(UserRole::COURIER, $this->branch);

        $assign = fn ($courierId) => $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $courierId]);

        $assign($this->courier->id)->assertOk();
        $assign($secondCourier->id)->assertOk()->assertJsonPath('data.courier_id', $secondCourier->id);

        $this->assertSame([$secondCourier->id], OrderAssignment::where('order_id', $order->id)->where('is_current', true)->pluck('courier_id')->all());
        $this->assertSame(2, OrderAssignment::where('order_id', $order->id)->count());

        // Once on the way the courier can no longer be swapped
        $this->setStatus($order, $this->manager, 'on_the_way')->assertOk();
        $assign($this->courier->id)->assertStatus(422);
        $this->assertSame($secondCourier->id, $order->fresh()->courier_id);
    }

    public function test_paid_callback_accepts_a_new_order_through_the_service_and_never_revives_one(): void
    {
        $paid = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'click']);
        $canceled = $this->makeOrder(OrderStatus::CANCELED, ['payment_method' => 'click']);
        $paymentFor = fn (Order $order) => Payment::create([
            'order_id' => $order->id,
            'provider' => 'click',
            'amount' => 50000,
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);

        event(new PaymentPaid($paymentFor($paid)));
        event(new PaymentPaid($paymentFor($canceled)));

        $this->assertSame(OrderStatus::ACCEPTED, $paid->fresh()->status);
        $history = OrderStatusHistory::where('order_id', $paid->id)->sole();
        $this->assertSame([OrderStatus::NEW, OrderStatus::ACCEPTED, null], [$history->from_status, $history->status, $history->user_id]);
        $this->assertStringContainsString('Payment verified via Webhook (click)', OrderStatusHistory::where('order_id', $paid->id)->value('notes'));

        $this->assertSame(OrderStatus::CANCELED, $canceled->fresh()->status);
        $this->assertSame(PaymentStatus::PAID, $canceled->fresh()->payment_status);
        $this->assertSame(0, OrderStatusHistory::where('order_id', $canceled->id)->count());
    }

    public function test_checkout_history_note_is_persisted(): void
    {
        $order = $this->checkout($this->customer, 1);

        $this->assertSame('Order created via Mini App', OrderStatusHistory::where('order_id', $order->id)->where('status', 'new')->value('notes'));
    }

    public function test_courier_id_from_another_store_is_rejected_on_both_endpoints(): void
    {
        $order = $this->makeOrder(OrderStatus::READY);
        $otherStore = $this->makeStore('Other Store');
        $foreignCourier = $this->makeStaff(UserRole::COURIER, null, $otherStore);
        $otherBranchCourier = $this->makeStaff(UserRole::COURIER, $this->otherBranch);

        $this->setStatus($order, $this->manager, 'courier_assigned', ['courier_id' => $foreignCourier->id])
            ->assertStatus(422);
        $this->setStatus($order, $this->manager, 'on_the_way', ['courier_id' => $this->customer->id])
            ->assertStatus(422);
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $foreignCourier->id])
            ->assertStatus(422);
        // A branch manager only hands orders to their branch (or store-wide staff)
        $this->setStatus($order, $this->manager, 'courier_assigned', ['courier_id' => $otherBranchCourier->id])
            ->assertStatus(422);
        // courier_id only makes sense when the order goes to a courier
        $this->setStatus($this->makeOrder(OrderStatus::NEW), $this->manager, 'accepted', ['courier_id' => $this->courier->id])
            ->assertStatus(422);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::READY, $fresh->status);
        $this->assertNull($fresh->courier_id);
        $this->assertSame(0, OrderAssignment::count());

        // The same field with a valid courier goes through the shared validation
        $this->setStatus($order, $this->manager, 'courier_assigned', ['courier_id' => $this->courier->id])
            ->assertOk()
            ->assertJsonPath('data.courier_id', $this->courier->id);
    }
}
