<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\UserRole;
use App\Events\Broadcast\BillPrintRequestedBroadcast;
use App\Models\Branch;
use App\Models\OrderBill;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6 stage A: the waiter's "Schyot so'rash" makes the bill and asks the till to print the pre-check by itself
 * (`auto_print_bill`, `bill_printer`), and the waiter app's reprint request (pos.reprint_bill or a manager's approval).
 * Never fiscal: the bill is not "printed" (no copy count, no lock) and the order's version does not move.
 */
class PosBillAutoPrintTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    private function printer(array $extra = []): Printer
    {
        return Printer::create($extra + [
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'name' => 'Kassa printeri ' . (Printer::count() + 1),
            'purpose' => 'receipt',
            'connection' => 'lan',
            'address' => '192.168.1.50',
            'port' => 9100,
            'paper_width' => 80,
        ]);
    }

    private function settings(array $changes): void
    {
        $this->branch->forceFill(['pos_settings' => PosSettings::merge($this->branch->pos_settings, $changes)])->save();
    }

    private function waiterOrder(): \App\Models\Order
    {
        return $this->posOrder(attributes: ['waiter_id' => $this->waiter->id, 'user_id' => $this->waiter->id]);
    }

    private function sentTo(BillPrintRequestedBroadcast $event): array
    {
        return array_map(fn ($channel) => $channel->name, $event->broadcastOn());
    }

    public function test_requesting_the_bill_makes_it_and_asks_the_till_to_print_once(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $order = $this->waiterOrder();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill", ['version' => 1])->assertOk()->assertJsonPath('data.version', 2);

        $bill = OrderBill::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(BillStatus::OPEN, $bill->status);
        $this->assertSame(0, (int) $bill->printed_count, 'a pre-check is not the hand-over of the bill');
        $this->assertNull($order->refresh()->bill_locked_at);
        $this->assertSame(2, (int) $order->version, 'the order\'s version moves for the request only');
        Event::assertDispatchedTimes(BillPrintRequestedBroadcast::class, 1);
        Event::assertDispatched(BillPrintRequestedBroadcast::class, function (BillPrintRequestedBroadcast $event) use ($bill) {
            $payload = $event->broadcastWith();

            return $event->broadcastAs() === 'bill.print_requested'
                && $this->sentTo($event) === ["private-pos-branch.{$this->branch->id}"]
                && $payload['bill']['uuid'] === $bill->uuid
                && $payload['reprint'] === false
                && $payload['printer_id'] === null
                && $payload['target_device'] === null
                && $payload['requested_by'] === (int) $this->waiter->id
                && is_string($payload['request_id']);
        });

        // Asked already: nothing is made or announced again
        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk();
        Event::assertDispatchedTimes(BillPrintRequestedBroadcast::class, 1);
        $this->assertSame(1, OrderBill::where('order_id', $order->id)->count());
    }

    public function test_the_setting_off_asks_for_no_bill_and_no_print(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $this->settings(['auto_print_bill' => false]);
        $order = $this->waiterOrder();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk()->assertJsonPath('data.version', 2);

        $this->assertSame(0, OrderBill::where('order_id', $order->id)->count());
        Event::assertNotDispatched(BillPrintRequestedBroadcast::class);
    }

    public function test_an_order_with_nothing_to_bill_still_takes_the_request(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $order = $this->posOrder([]);
        $order->forceFill(['waiter_id' => $this->waiter->id])->save();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk()
            ->assertJsonPath('data.bill_requested_at', fn ($value) => is_string($value));

        Event::assertNotDispatched(BillPrintRequestedBroadcast::class);
    }

    public function test_the_bill_printer_and_the_till_it_belongs_to_are_named(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $printer = $this->printer(['connection' => 'usb', 'address' => null, 'port' => null, 'device_id' => $this->till['device']->id]);
        $this->settings(['bill_printer' => $printer->id]);
        $order = $this->waiterOrder();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk();

        Event::assertDispatched(BillPrintRequestedBroadcast::class, fn (BillPrintRequestedBroadcast $event) => $event->broadcastWith()['printer_id'] === $printer->id
            && $event->broadcastWith()['target_device'] === $this->till['device']->uuid);
    }

    public function test_without_a_bill_printer_the_printing_tills_receipt_printer_prints(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $lan = $this->printer();
        $own = $this->printer(['name' => 'Kassa USB', 'connection' => 'usb', 'address' => null, 'port' => null, 'device_id' => $this->till['device']->id]);
        $this->settings(['printing_device_id' => $this->till['device']->uuid]);
        $order = $this->waiterOrder();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk();

        Event::assertDispatched(BillPrintRequestedBroadcast::class, fn (BillPrintRequestedBroadcast $event) => $event->broadcastWith()['printer_id'] === $own->id
            && $event->broadcastWith()['target_device'] === $this->till['device']->uuid);
        $this->assertNotSame($lan->id, $own->id);
    }

    public function test_a_lan_bill_printer_without_a_printing_till_names_no_till(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $lan = $this->printer();
        $this->settings(['bill_printer' => $lan->id]);
        $order = $this->waiterOrder();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk();

        Event::assertDispatched(BillPrintRequestedBroadcast::class, fn (BillPrintRequestedBroadcast $event) => $event->broadcastWith()['printer_id'] === $lan->id
            && $event->broadcastWith()['target_device'] === null);
    }

    public function test_a_bill_the_order_outgrew_is_made_again_and_a_current_one_is_kept(): void
    {
        $order = $this->waiterOrder();
        $stale = $this->bill($order)->assertCreated()->json('data.bills.0.uuid');
        $this->line($order->refresh(), 'Non', '1', '5000');
        $order->forceFill(['subtotal' => '135000.00', 'total_price' => '135000.00'])->save();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk();

        $this->assertSame(BillStatus::VOID, OrderBill::where('uuid', $stale)->firstOrFail()->status);
        $current = OrderBill::where('order_id', $order->id)->where('status', BillStatus::OPEN->value)->get();
        $this->assertCount(1, $current);
        $this->assertSame('135000.00', $current[0]->total);
    }

    public function test_a_bill_being_paid_is_left_to_the_till(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $this->openShift();
        $order = $this->waiterOrder();
        $bill = $this->bill($order)->assertCreated()->json('data.bills.0');
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => '10000']])->assertOk();

        $this->posAs($this->waiter, 'POST', "/orders/{$order->uuid}/request-bill")->assertOk();

        Event::assertNotDispatched(BillPrintRequestedBroadcast::class);
        $this->assertSame(1, OrderBill::where('order_id', $order->id)->count());
    }

    public function test_a_reprint_needs_the_permission_or_a_managers_approval_and_is_audited(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $order = $this->waiterOrder();
        $bill = $this->bill($order)->assertCreated()->json('data.bills.0.uuid');

        $token = $this->approval('pos.reprint_bill');
        // A waiter has no pos.reprint_bill
        $waiter = $this->pinSession($this->till, $this->waiter);
        $this->pos('POST', "/bills/{$bill}/print-request", [], $waiter)->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        Event::assertNotDispatched(BillPrintRequestedBroadcast::class);

        $response = $this->pos('POST', "/bills/{$bill}/print-request", ['approval_token' => $token], $waiter)->assertOk()
            ->assertJsonPath('data.printer_id', null)
            ->assertJsonPath('data.target_device', null);
        Event::assertDispatched(BillPrintRequestedBroadcast::class, fn (BillPrintRequestedBroadcast $event) => $event->broadcastWith()['reprint'] === true
            && $event->broadcastWith()['request_id'] === $response->json('data.request_id')
            && $event->broadcastWith()['bill']['uuid'] === $bill);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.bill_reprinted', 'user_id' => $this->waiter->id]);
        $this->assertSame(0, (int) OrderBill::where('uuid', $bill)->firstOrFail()->printed_count);

        // The token is single use
        $this->pos('POST', "/bills/{$bill}/print-request", ['approval_token' => $token], $waiter)->assertForbidden();

        // A cashier (and a manager) hold the permission
        $this->posAs($this->cashier, 'POST', "/bills/{$bill}/print-request")->assertOk();
        $this->posAs($this->manager, 'POST', "/bills/{$bill}/print-request")->assertOk();
        Event::assertDispatchedTimes(BillPrintRequestedBroadcast::class, 3);
    }

    public function test_a_voided_bill_another_branch_and_a_missing_key_are_refused(): void
    {
        $order = $this->waiterOrder();
        $bill = $this->bill($order)->assertCreated()->json('data.bills.0.uuid');
        OrderBill::where('uuid', $bill)->update(['status' => BillStatus::VOID->value]);

        $this->posAs($this->cashier, 'POST', "/bills/{$bill}/print-request")->assertStatus(409)->assertJsonPath('data.reason', 'bill_void');
        $this->posAs($this->cashier, 'POST', '/bills/' . \Illuminate\Support\Str::uuid() . '/print-request')->assertNotFound();

        $this->withBearer($this->cashierToken)->postJson("/api/v1/pos/bills/{$bill}/print-request")->assertStatus(428);

        $outsider = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);
        $live = $this->bill($this->waiterOrder())->assertCreated()->json('data.bills.0.uuid');
        $this->posAs($outsider, 'POST', "/bills/{$live}/print-request")->assertNotFound();
    }

    public function test_the_settings_default_to_on_and_the_bill_printer_must_be_a_receipt_printer_of_the_branch(): void
    {
        $url = "/api/v1/owner/branches/{$this->branch->id}/pos-settings";
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'sanctum')->getJson($url)->assertOk()
            ->assertJsonPath('data.pos_settings.auto_print_bill', true)
            ->assertJsonPath('data.pos_settings.bill_printer', null);

        $kitchen = $this->printer(['purpose' => 'kitchen']);
        $foreign = Printer::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'Boshqa', 'purpose' => 'receipt', 'connection' => 'lan', 'address' => '10.0.0.5', 'port' => 9100, 'paper_width' => 80]);
        $off = $this->printer(['is_active' => false]);
        foreach ([$kitchen, $foreign, $off] as $bad) {
            $this->actingAs($this->owner, 'sanctum')->putJson($url, ['pos_settings' => ['bill_printer' => $bad->id]])
                ->assertUnprocessable()->assertJsonValidationErrors('pos_settings.bill_printer');
        }

        $good = $this->printer();
        $this->actingAs($this->owner, 'sanctum')->putJson($url, ['pos_settings' => ['auto_print_bill' => false, 'bill_printer' => $good->id]])->assertOk()
            ->assertJsonPath('data.pos_settings.auto_print_bill', false)
            ->assertJsonPath('data.pos_settings.bill_printer', $good->id);
        $this->actingAs($this->owner, 'sanctum')->putJson($url, ['pos_settings' => ['bill_printer' => null]])->assertOk()
            ->assertJsonPath('data.pos_settings.auto_print_bill', false)
            ->assertJsonPath('data.pos_settings.bill_printer', null);
        $this->assertFalse(PosSettings::resolve(Branch::find($this->branch->id)->pos_settings)['auto_print_bill']);
    }

    public function test_the_terminals_read_the_setting_in_the_bootstrap(): void
    {
        $good = $this->printer();
        $this->settings(['bill_printer' => $good->id]);

        $this->pos('GET', '/bootstrap')->assertOk()
            ->assertJsonPath('data.branch.pos_settings.auto_print_bill', true)
            ->assertJsonPath('data.branch.pos_settings.bill_printer', $good->id);
    }
}
