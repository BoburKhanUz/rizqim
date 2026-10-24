<?php

namespace Tests\Feature\PosBilling;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosShift;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3-37 (the owner's decisions of 24.10.2026) on the till: cash in and out by the cashier alone with the owner told
 * about a large cash-out, and a closed order refunded in full cancelled (with a manager's PIN, as any cancel).
 */
class PosCashierPermissionsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    private PosShift $shift;

    private FakeTelegramMessenger $telegram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);
        $this->shift = $this->openShift('2000000');
    }

    /**
     * @return list<string>
     */
    private function ownerAlerts(): array
    {
        return array_values(array_map(fn (array $sent) => $sent['text'], array_filter($this->telegram->sent, fn (array $sent) => $sent['chat'] === (string) $this->owner->telegram_id)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function movement(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->pos('POST', "/shifts/{$this->shift->uuid}/cash-movements", $data);
    }

    public function test_the_owner_hears_about_one_cash_out_of_at_least_the_branchs_amount(): void
    {
        // Below the default 500 000: quiet; cash in never alerts
        $this->movement(['type' => 'cash_out', 'category' => 'collection', 'amount' => '499999.99'])->assertCreated()->assertJsonPath('data.movement.approved_by', null);
        $this->movement(['type' => 'cash_in', 'category' => 'owner', 'amount' => '900000'])->assertCreated();
        $this->assertSame([], $this->ownerAlerts());

        // At it: told, with the reason and who took it
        $this->movement(['type' => 'cash_out', 'category' => 'other', 'amount' => '500000', 'note' => 'Generator tuzatildi'])->assertCreated();
        $this->assertCount(1, $this->ownerAlerts());
        $this->assertStringContainsString("Kassadan pul chiqarildi: 500 000 so'm", $this->ownerAlerts()[0]);
        $this->assertStringContainsString('Sabab: boshqa — Generator tuzatildi', $this->ownerAlerts()[0]);
        $this->assertStringContainsString("Kim: {$this->cashier->name}", $this->ownerAlerts()[0]);

        // A wage and a purchase take money out too
        $this->movement(['type' => 'wage', 'category' => 'advance', 'amount' => '600000', 'employee_id' => $this->waiter->id])->assertCreated();
        $this->movement(['type' => 'purchase', 'category' => 'meat', 'amount' => '700000'])->assertCreated();
        $this->assertCount(3, $this->ownerAlerts());
        $this->assertStringContainsString("Kassadan ish haqi berildi: 600 000 so'm", $this->ownerAlerts()[1]);
        $this->assertStringContainsString("Xodim: {$this->waiter->name}", $this->ownerAlerts()[1]);
        $this->assertStringContainsString("Kassadan xarid qilindi: 700 000 so'm", $this->ownerAlerts()[2]);

        // The branch's own amount; null switches it off
        $this->branch->forceFill(['pos_settings' => ['alerts' => ['cash_out_amount' => '100000']]])->save();
        $this->movement(['type' => 'cash_out', 'category' => 'supplies', 'amount' => '100000'])->assertCreated();
        $this->assertCount(4, $this->ownerAlerts());
        $this->branch->forceFill(['pos_settings' => ['alerts' => ['cash_out_amount' => null]]])->save();
        $this->movement(['type' => 'cash_out', 'category' => 'collection', 'amount' => '200000'])->assertCreated();
        $this->assertCount(4, $this->ownerAlerts());

        // A cash out refused (more than the drawer holds) tells nobody
        $this->branch->forceFill(['pos_settings' => null])->save();
        $this->movement(['type' => 'cash_out', 'category' => 'collection', 'amount' => '9000000'])->assertStatus(409)->assertJsonPath('data.reason', 'not_enough_cash');
        $this->assertCount(4, $this->ownerAlerts());
    }

    /**
     * A paid, closed order: 130 000 in cash on one bill, its sale receipt issued.
     */
    private function closedOrder(): Order
    {
        $order = $this->posOrder([['Osh', '1.5', '120000'], ['Choy', '2', '10000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '130000']])->assertOk()->assertJsonPath('data.order.status', 'delivered');

        return $order->refresh();
    }

    private function refund(string $amount): void
    {
        $payment = Payment::where('status', PaymentStatus::PAID->value)->firstOrFail();
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => $amount, 'reason' => 'Mijoz norozi', 'shift_uuid' => $this->shift->uuid])
            ->assertCreated();
    }

    public function test_a_closed_order_refunded_in_full_is_cancelled_and_nothing_else_closed_is(): void
    {
        $order = $this->closedOrder();
        $closedAt = $order->closed_at;
        $cancel = fn (array $extra = []) => $this->pos('POST', "/orders/{$order->uuid}/cancel", array_merge(['version' => (int) $order->refresh()->version, 'reason' => 'Mehmon norozi'], $extra));

        // Paid and closed: still 422 order_closed
        $cancel(['approval_token' => $this->approval('orders.cancel')])->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');

        // Refunded in part: still closed
        $this->refund('30000');
        $cancel()->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');

        // Refunded in full: the cashier needs a manager's PIN, as for any cancel
        $this->refund('100000');
        $this->assertSame(PaymentStatus::REFUNDED, $order->refresh()->payment_status);
        $receipts = count($this->fiscal->documents);
        $cancel()->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'orders.cancel');
        $cancel(['version' => 1, 'approval_token' => $this->approval('orders.cancel')])->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');

        $this->pos('POST', "/orders/{$order->uuid}/cancel", ['version' => (int) $order->refresh()->version, 'reason' => 'Mehmon norozi', 'approval_token' => $this->approval('orders.cancel')])
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled');

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertSame($closedAt?->toJSON(), $order->closed_at?->toJSON());
        $this->assertSame($this->cashier->id, (int) $order->cancelled_by);
        $this->assertSame('Mehmon norozi', $order->cancel_note);
        // No new receipt: the refund receipts already reverse the sale
        $this->assertSame($receipts, count($this->fiscal->documents));
        // The served lines and the paid bill stay as they were
        $this->assertSame(0, $order->items()->whereNotNull('voided_at')->count());
        $this->assertSame(\App\Domain\Pos\Billing\BillStatus::PAID, $order->bills()->firstOrFail()->status);

        $audit = AuditLog::where('action', 'pos.order_cancelled')->firstOrFail();
        $this->assertTrue($audit->payload_json['after_refund']);
        $this->assertSame($this->manager->id, $audit->payload_json['approved_by']);
        $this->assertSame(['status' => 'delivered'], $audit->before);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'from_status' => 'delivered', 'status' => 'canceled', 'user_id' => $this->cashier->id]);
        $this->assertStringContainsString('Buyurtma bekor qilindi', collect($this->ownerAlerts())->last());

        // Cancelled once: 422 from now on
        $this->posAs($this->owner, 'POST', "/orders/{$order->uuid}/cancel", ['version' => (int) $order->version, 'reason' => 'Yana'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');
    }

    public function test_the_owner_cancels_a_refunded_order_alone(): void
    {
        $order = $this->closedOrder();
        $this->refund('130000');

        $this->posAs($this->owner, 'POST', "/orders/{$order->uuid}/cancel", ['version' => (int) $order->refresh()->version, 'reason' => 'Xato buyurtma'])
            ->assertOk()->assertJsonPath('data.status', 'canceled');
    }
}
