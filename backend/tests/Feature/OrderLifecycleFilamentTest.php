<?php

namespace Tests\Feature;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\OrderStatusHistory;
use App\Models\StoreTransaction;
use App\Services\Order\OrderStatusService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderLifecycleFilamentTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();

        Filament::setCurrentPanel('admin');
    }

    protected function signIn($user): void
    {
        $this->actingAs($user);
        Filament::setTenant($this->store);
    }

    public function test_admin_panel_complete_charges_commission_once(): void
    {
        // Sent out by the panel's own "Send to Delivery"
        $order = $this->makeOrder(OrderStatus::DELIVERY, ['courier_id' => $this->courier->id]);
        $this->signIn($this->owner);

        Livewire::test(ListOrders::class)
            ->callTableAction('complete', $order)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)
            ->where('type', StoreTransactionType::COMMISSION->value)->count());
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        // The action is gone for a delivered order, and a stale tab's late click (same path) charges nothing more
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'completed')
            ->assertTableActionHidden('complete', $order);
        $this->assertFalse(app(OrderStatusService::class)->transition($order, OrderStatus::DELIVERED, $this->owner)->changed);

        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)->count());
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame($this->owner->id, OrderStatusHistory::where('order_id', $order->id)->where('status', 'delivered')->value('user_id'));
    }

    public function test_admin_panel_follows_the_transition_map(): void
    {
        $preparingDelivery = $this->makeOrder(OrderStatus::PREPARING);
        $preparingPickup = $this->makeOrder(OrderStatus::PREPARING, ['order_type' => 'pickup']);
        $delivered = $this->makeOrder(OrderStatus::DELIVERED);
        $this->signIn($this->owner);

        Livewire::test(ListOrders::class)
            // A delivery order can't be completed from the kitchen; a pickup order is handed over
            ->assertTableActionHidden('complete', $preparingDelivery)
            ->assertTableActionVisible('complete', $preparingPickup)
            ->assertTableActionVisible('deliver', $preparingDelivery)
            ->set('activeTab', 'completed')
            ->assertTableActionHidden('cancel', $delivered);
    }

    public function test_admin_panel_cancel_restores_stock_and_send_to_delivery_assigns_a_courier(): void
    {
        $canceled = $this->checkout($this->customer, 2);
        $dispatched = $this->makeOrder(OrderStatus::PREPARING);
        $this->signIn($this->owner);

        Livewire::test(ListOrders::class)
            ->callTableAction('cancel', $canceled, data: ['reason' => 'out_of_stock'])
            ->callTableAction('deliver', $dispatched);

        $this->assertSame(OrderStatus::CANCELED, $canceled->fresh()->status);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);

        $dispatched->refresh();
        $this->assertSame(OrderStatus::DELIVERY, $dispatched->status);
        $this->assertSame($this->courier->id, $dispatched->courier_id);
    }

    public function test_cancelled_paid_online_order_shows_the_manual_refund_in_the_panel(): void
    {
        $order = $this->makeOrder(OrderStatus::ACCEPTED, ['payment_method' => 'payme', 'payment_status' => PaymentStatus::PAID->value]);
        $untouched = $this->makeOrder(OrderStatus::ACCEPTED, ['payment_method' => 'payme']);
        $this->signIn($this->owner);

        Livewire::test(ListOrders::class)
            ->callTableAction('cancel', $order, data: ['reason' => 'store_closed'])
            ->callTableAction('cancel', $untouched, data: ['reason' => 'store_closed'])
            ->assertNotified("Buyurtma holati: Bekor qilindi");

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee("mijozga qo'lda qaytarilishi kerak");
        Livewire::test(ViewOrder::class, ['record' => $untouched->getRouteKey()])
            ->assertDontSee("mijozga qo'lda qaytarilishi kerak");

        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_admin_panel_cancel_and_reject_require_a_reason_and_record_it(): void
    {
        $canceled = $this->makeOrder(OrderStatus::PREPARING, ['order_type' => 'pickup']);
        $rejected = $this->makeOrder(OrderStatus::NEW);
        $this->signIn($this->owner);

        Livewire::test(ListOrders::class)
            ->callTableAction('cancel', $canceled, data: ['reason' => null])
            ->assertHasTableActionErrors(['reason' => 'required']);
        $this->assertSame(OrderStatus::PREPARING, $canceled->fresh()->status);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('reject', $canceled)
            ->callTableAction('cancel', $canceled, data: ['reason' => 'no_show', 'note' => 'Kelmadi'])
            ->callTableAction('reject', $rejected, data: ['reason' => 'out_of_stock']);

        $canceled->refresh();
        $this->assertSame(OrderStatus::CANCELED, $canceled->status);
        $this->assertSame(CancelReason::NO_SHOW, $canceled->cancel_reason);
        $this->assertSame('Kelmadi', $canceled->cancel_note);
        $this->assertSame($this->owner->id, $canceled->cancelled_by);

        $rejected->refresh();
        $this->assertSame(OrderStatus::REJECTED, $rejected->status);
        $this->assertSame(CancelReason::OUT_OF_STOCK, $rejected->cancel_reason);
        $this->assertNull($rejected->cancel_note);

        Livewire::test(ViewOrder::class, ['record' => $canceled->getRouteKey()])
            ->assertSee('Mijoz kelmadi');
    }

    public function test_branch_manager_cannot_act_on_another_branchs_order_in_the_panel(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW, ['branch_id' => $this->otherBranch->id]);
        $this->signIn($this->manager);

        Livewire::test(ListOrders::class)
            ->assertCanNotSeeTableRecords([$order]);

        $this->signIn($this->makeStaff(UserRole::MANAGER, $this->otherBranch));
        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->callTableAction('accept', $order);

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
    }
}
