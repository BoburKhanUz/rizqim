<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3-37: a discount at the till (POST /pos/orders/{uuid}/discount): who may give it (orders.discount, a cashier
 * within the branch's limit, a manager's approval above it), a percent worked out again as lines change, an amount
 * capped by the dishes, value 0 taking it off, the bill lock, paid bills, closed orders, the audit and the owner's
 * alert.
 */
class PosDiscountTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected FakeTelegramMessenger $telegram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);
    }

    /**
     * @return list<string>
     */
    private function ownerAlerts(): array
    {
        return array_values(array_map(fn (array $sent) => $sent['text'], array_filter($this->telegram->sent, fn (array $sent) => $sent['chat'] === (string) $this->owner->telegram_id)));
    }

    /**
     * A table order of 100 000 so'm of dishes: 5 shashlik (90 000) and 2 tea (10 000), version 2.
     */
    private function order(): Order
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [
            $this->line($this->shashlik, 5), $this->line($this->tea, 2),
        ]])->assertOk()->assertJsonPath('data.subtotal', '100000.00');

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function discount(mixed $actor, Order $order, string $kind, string $value, array $extra = []): TestResponse
    {
        return $this->pos($actor, 'POST', "/pos/orders/{$order->uuid}/discount", array_merge([
            'version' => (int) $order->refresh()->version,
            'kind' => $kind,
            'value' => $value,
            'reason' => 'Doimiy mijoz',
        ], $extra));
    }

    private function setLimit(?string $percent): void
    {
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->fresh()->pos_settings ?? [], ['cashier_discount_max_percent' => $percent])])->save();
    }

    public function test_a_manager_gives_any_discount_and_the_order_shows_it(): void
    {
        Event::fake([OrderItemsChangedBroadcast::class]);
        $this->serviceFee('10');
        $order = $this->order();

        $this->discount($this->manager, $order, 'percent', '30')
            ->assertOk()
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.discount_kind', 'percent')
            ->assertJsonPath('data.discount_value', '30.00')
            ->assertJsonPath('data.discount_reason', 'Doimiy mijoz')
            ->assertJsonPath('data.discount_by', $this->manager->id)
            ->assertJsonPath('data.subtotal', '100000.00')
            ->assertJsonPath('data.discount', '30000.00')
            ->assertJsonPath('data.charges.discount', '30000.00')
            // The service charge is on the dishes after the discount: 10% of 70 000
            ->assertJsonPath('data.service_charge_amount', '7000.00')
            ->assertJsonPath('data.total_price', '77000.00');

        Event::assertDispatched(OrderItemsChangedBroadcast::class, fn (OrderItemsChangedBroadcast $event) => $event->change === 'charges_changed' && $event->version === 3);

        $audit = AuditLog::where('action', 'pos.order.discount')->firstOrFail();
        $this->assertSame($this->manager->id, (int) $audit->user_id);
        $this->assertSame(['kind' => 'percent', 'value' => '30.00', 'amount' => '30000.00', 'reason' => 'Doimiy mijoz'], $audit->after);
        $this->assertSame(['kind' => null, 'value' => null, 'amount' => '0.00', 'reason' => null], $audit->before);
        $this->assertSame('100000.00', $audit->payload_json['items_subtotal']);
        $this->assertArrayNotHasKey('approved_by', $audit->payload_json);

        // An amount, given over the percent: it replaces it
        $this->discount($this->owner, $order, 'amount', '25000.50')
            ->assertOk()
            ->assertJsonPath('data.discount_kind', 'amount')
            ->assertJsonPath('data.discount_value', '25000.50')
            // Whole so'm, half-up
            ->assertJsonPath('data.discount', '25001.00');
    }

    public function test_a_cashier_discounts_alone_up_to_the_branch_limit_and_needs_a_manager_above_it(): void
    {
        $order = $this->order();

        // The default limit is 10 %: 10 % and 10 000 so'm of 100 000 are the cashier's own
        $this->discount($this->cashier, $order, 'percent', '10')->assertOk()->assertJsonPath('data.discount', '10000.00');
        $this->discount($this->cashier, $order, 'amount', '10000')->assertOk()->assertJsonPath('data.discount', '10000.00');
        $this->assertSame(2, AuditLog::where('action', 'pos.order.discount')->where('user_id', $this->cashier->id)->count());

        // Above it: a manager's PIN for orders.discount
        $this->discount($this->cashier, $order, 'percent', '10.01')
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'orders.discount');
        $this->discount($this->cashier, $order, 'amount', '10001')
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->discount($this->cashier, $order, 'percent', '20', ['approval_token' => $this->approval('orders.manage', $this->manager)])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_invalid');
        $this->assertSame('10000.00', $order->refresh()->discount);

        $this->discount($this->cashier, $order, 'percent', '20', ['approval_token' => $this->approval('orders.discount', $this->manager)])
            ->assertOk()->assertJsonPath('data.discount', '20000.00')->assertJsonPath('data.discount_by', $this->cashier->id);
        $audit = AuditLog::where('action', 'pos.order.discount')->latest('id')->firstOrFail();
        $this->assertSame($this->manager->id, $audit->payload_json['approved_by']);
        $this->assertSame('20.00', $audit->payload_json['percent']);

        // The owner raised the limit for this branch: 25 % is the cashier's own now
        $this->setLimit('25.00');
        $this->discount($this->cashier, $order, 'percent', '25')->assertOk()->assertJsonPath('data.discount', '25000.00');
    }

    public function test_without_a_limit_or_without_taking_payment_every_discount_needs_a_manager(): void
    {
        $order = $this->order();

        foreach ([null, '0.00'] as $limit) {
            $this->setLimit($limit);
            $this->discount($this->cashier, $order, 'percent', '1')->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        }

        // A waiter does not take payment: no limit of their own
        $this->setLimit('50.00');
        $mine = $this->openedOrder($this->openPos($this->waiter, $this->tapchan, 2));
        $this->pos($this->waiter, 'POST', "/pos/orders/{$mine->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 2)]])->assertOk();
        $this->discount($this->waiter, $mine, 'percent', '5')->assertForbidden()->assertJsonPath('data.permission', 'orders.discount');
        $this->discount($this->waiter, $mine, 'percent', '5', ['approval_token' => $this->approval('orders.discount', $this->manager)])
            ->assertOk()->assertJsonPath('data.discount', '500.00');

        // A cook has neither orders.manage nor orders.discount: the route refuses
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->discount($cook, $order, 'percent', '5')->assertForbidden();
    }

    public function test_a_percent_is_worked_out_again_when_lines_are_added_or_voided(): void
    {
        $order = $this->order();
        $this->discount($this->cashier, $order, 'percent', '10')->assertOk()->assertJsonPath('data.discount', '10000.00');

        // 1 more shashlik: 118 000 → 11 800
        $response = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [$this->line($this->shashlik, 1)]])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '118000.00')
            ->assertJsonPath('data.discount', '11800.00')
            ->assertJsonPath('data.discount_kind', 'percent')
            ->assertJsonPath('data.total_price', '106200.00');

        // The tea voided (not sent): 108 000 → 10 800
        $tea = collect($response->json('data.items'))->firstWhere('product_variant_id', $this->tea->id);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/items/{$tea['id']}/void", ['version' => (int) $order->refresh()->version])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '108000.00')
            ->assertJsonPath('data.discount', '10800.00')
            ->assertJsonPath('data.total_price', '97200.00');

        // Rounding: 12.35 % of 108 000 = 13 338, whole so'm half-up
        $this->discount($this->manager, $order, 'percent', '12.35')->assertOk()->assertJsonPath('data.discount', '13338.00');
    }

    public function test_an_amount_never_exceeds_the_dishes(): void
    {
        $order = $this->order();

        $this->discount($this->manager, $order, 'amount', '100000.01')->assertStatus(422)->assertJsonValidationErrors('value');
        $this->discount($this->manager, $order, 'amount', '95000')->assertOk()->assertJsonPath('data.discount', '95000.00');

        // The shashlik voided: 10 000 of dishes left, the discount is capped at them
        $shashlik = OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->shashlik->id)->firstOrFail();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/items/{$shashlik->id}/void", ['version' => (int) $order->refresh()->version])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '10000.00')
            ->assertJsonPath('data.discount', '10000.00')
            ->assertJsonPath('data.discount_value', '95000.00')
            ->assertJsonPath('data.total_price', '0.00');

        // A round brings dishes back: the amount applies in full again
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [$this->line($this->shashlik, 6)]])
            ->assertOk()->assertJsonPath('data.subtotal', '118000.00')->assertJsonPath('data.discount', '95000.00');
    }

    public function test_value_zero_takes_the_discount_off_and_the_input_is_checked(): void
    {
        $order = $this->order();
        $this->discount($this->manager, $order, 'percent', '40')->assertOk();

        // Taking it off raises the bill: the cashier needs nobody, and no reason
        $this->discount($this->cashier, $order, 'percent', '0', ['reason' => null])
            ->assertOk()
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.discount_kind', null)
            ->assertJsonPath('data.discount_value', null)
            ->assertJsonPath('data.discount_reason', null)
            ->assertJsonPath('data.discount_by', null)
            ->assertJsonPath('data.total_price', '100000.00');
        $audit = AuditLog::where('action', 'pos.order.discount')->latest('id')->firstOrFail();
        $this->assertTrue($audit->payload_json['removed']);
        $this->assertSame('40000.00', $audit->before['amount']);

        // And it stays off when lines change
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [$this->line($this->tea, 1)]])
            ->assertOk()->assertJsonPath('data.discount', '0.00');

        $this->discount($this->manager, $order, 'percent', '5', ['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->discount($this->manager, $order, 'percent', '100.01')->assertStatus(422)->assertJsonValidationErrors('value');
        $this->discount($this->manager, $order, 'percent', '-5')->assertStatus(422)->assertJsonValidationErrors('value');
        $this->discount($this->manager, $order, 'percent', '5.555')->assertStatus(422)->assertJsonValidationErrors('value');
        $this->discount($this->manager, $order, 'fixed', '5')->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->discount($this->manager, $order, 'percent', '5', ['reason' => str_repeat('a', 256)])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->discount($this->manager, $order, 'percent', '5', ['version' => 1])->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');
        $this->discount($this->manager, $order, 'percent', '100')->assertOk()->assertJsonPath('data.total_price', '0.00');

        // Every change needs an Idempotency-Key, and a repeat of one is replayed, not applied twice
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/pos/orders/{$order->uuid}/discount", ['version' => (int) $order->refresh()->version, 'kind' => 'percent', 'value' => '5', 'reason' => 'x'])
            ->assertStatus(428);
        $version = (int) $order->refresh()->version;
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->discount($this->manager, $order, 'percent', '5')->assertOk();
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/discount", ['version' => $version + 1, 'kind' => 'percent', 'value' => '6', 'reason' => 'x'], $key)->assertOk();
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/discount", ['version' => $version + 1, 'kind' => 'percent', 'value' => '6', 'reason' => 'x'], $key)->assertOk();
        $this->assertSame($version + 2, (int) $order->refresh()->version);
    }

    public function test_a_bill_being_paid_or_a_closed_order_refuses_a_discount(): void
    {
        $order = $this->order();
        $bill = OrderBill::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $order->id, 'number' => 1, 'total' => '100000', 'basis' => 'x', 'status' => 'printed']);
        Payment::create(['order_id' => $order->id, 'bill_id' => $bill->id, 'provider' => 'cash', 'amount' => '50000', 'status' => PaymentStatus::PAID, 'paid_at' => now()]);

        $this->discount($this->manager, $order, 'percent', '10')->assertStatus(409)->assertJsonPath('data.reason', 'paid_bill_affected');
        $this->discount($this->manager, $order, 'percent', '0')->assertStatus(409)->assertJsonPath('data.reason', 'paid_bill_affected');
        $this->assertSame('0.00', $order->refresh()->discount);
        $this->assertSame(0, AuditLog::where('action', 'pos.order.discount')->count());

        $other = $this->order();
        $this->pos($this->owner, 'POST', "/pos/orders/{$other->uuid}/cancel", ['version' => (int) $other->version, 'reason' => 'Xato'])->assertOk();
        $this->assertSame(OrderStatus::CANCELED, $other->refresh()->status);
        $this->discount($this->manager, $other, 'percent', '10')->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');
    }

    public function test_after_the_bill_is_printed_a_discount_needs_a_manager_and_the_bill_is_made_again(): void
    {
        $order = $this->order();
        $bill = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => (int) $order->refresh()->version, 'mode' => 'single'])
            ->assertCreated()->json('data.bills.0.uuid');
        $this->pos($this->cashier, 'POST', "/pos/bills/{$bill}/print", [])->assertOk();
        $this->assertNotNull($order->refresh()->bill_locked_at);

        // The cashier's own limit no longer applies once the guests have the bill (POS_SPEC 2.9)
        $this->discount($this->cashier, $order, 'percent', '5')->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->discount($this->cashier, $order, 'percent', '5', ['approval_token' => $this->approval('orders.discount', $this->manager)])
            ->assertOk()->assertJsonPath('data.discount', '5000.00')->assertJsonPath('data.bill_locked', true);

        // The printed bill no longer matches the order: printing it (even a copy) is refused, billing again works
        $this->pos($this->cashier, 'POST', "/pos/bills/{$bill}/print", [])->assertStatus(409)->assertJsonPath('data.reason', 'order_changed');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => (int) $order->refresh()->version, 'mode' => 'single'])
            ->assertCreated()
            ->assertJsonPath('data.bills.0.subtotal', '100000.00')
            ->assertJsonPath('data.bills.0.discount', '5000.00')
            ->assertJsonPath('data.bills.0.total', '95000.00');

        // A manager discounts a locked order alone
        $this->discount($this->manager, $order, 'percent', '15')->assertOk()->assertJsonPath('data.discount', '15000.00');
    }

    public function test_the_owner_hears_about_a_discount_at_the_branchs_alert_percent(): void
    {
        $order = $this->order();

        // The default alert is 10 %: 5 % is quiet
        $this->discount($this->cashier, $order, 'percent', '5')->assertOk();
        $this->assertSame([], $this->ownerAlerts());

        $this->discount($this->cashier, $order, 'percent', '10')->assertOk();
        $this->assertCount(1, $this->ownerAlerts());
        $this->assertStringContainsString('Chegirma berildi: 10%', $this->ownerAlerts()[0]);
        $this->assertStringContainsString("10 000 so'm", $this->ownerAlerts()[0]);
        $this->assertStringContainsString('Sabab: Doimiy mijoz', $this->ownerAlerts()[0]);
        $this->assertStringContainsString("Kim: {$this->cashier->name}", $this->ownerAlerts()[0]);

        // An amount by its share of the dishes, with the approving manager
        $this->discount($this->cashier, $order, 'amount', '30000', ['approval_token' => $this->approval('orders.discount', $this->manager)])->assertOk();
        $this->assertCount(2, $this->ownerAlerts());
        $this->assertStringContainsString("Chegirma berildi: 30 000 so'm (30%)", $this->ownerAlerts()[1]);
        $this->assertStringContainsString("Tasdiqladi: {$this->manager->name}", $this->ownerAlerts()[1]);

        // Switched off: quiet; taking a discount off never alerts
        $this->branch->forceFill(['pos_settings' => ['alerts' => ['discount_percent' => null]]])->save();
        $this->discount($this->manager, $order, 'percent', '50')->assertOk();
        $this->discount($this->manager, $order, 'percent', '0')->assertOk();
        $this->assertCount(2, $this->ownerAlerts());
    }
}
