<?php

namespace Tests\Feature\PosLan;

use App\Enums\DeviceType;
use App\Events\Broadcast\BillPrintRequestedBroadcast;
use App\Models\Order;
use App\Models\OrderBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * Review P6 hardware/LAN (C2, C6): the waiter's "Schyot so'rash" is kept for the till (App\Domain\Pos\Billing\BillPrintInbox):
 * a till that missed the broadcast (offline, restarted, its screen locked: no staff session) reads the outstanding
 * requests with its own token, with the bill and the order to print, and claims each before printing — once per
 * request across the branch's tills. A relayed LAN request keeps its op's key as the request id.
 */
class PosBillPrintInboxTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    private function asDevice(string $token, string $method, string $uri): TestResponse
    {
        $this->withBearer($token);

        return $this->json($method, '/api/v1' . $uri);
    }

    /** A waiter's hall order with a line, the bill asked for online. */
    private function askedOrder(): Order
    {
        $phone = $this->pairTerminal($this->owner, $this->branch);
        $token = $this->pinSession($phone, $this->waiter, '7391');
        $this->withBearer($token)->withHeader('Idempotency-Key', (string) Str::uuid());
        $uuid = $this->json('POST', '/api/v1/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => '2']], 'send' => true,
        ])->assertSuccessful()->json('data.uuid');
        $this->withBearer($token)->withHeader('Idempotency-Key', (string) Str::uuid());
        $this->json('POST', "/api/v1/pos/orders/{$uuid}/request-bill")->assertOk();

        return Order::where('uuid', $uuid)->sole();
    }

    public function test_a_till_without_a_staff_session_reads_the_waiters_request_and_claims_it_once(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $order = $this->askedOrder();
        $bill = OrderBill::where('order_id', $order->id)->sole();
        $requestId = null;
        Event::assertDispatched(BillPrintRequestedBroadcast::class, function (BillPrintRequestedBroadcast $e) use (&$requestId) {
            $requestId = $e->requestId;

            return true;
        });

        // The till's screen is locked: its own token, no PIN session
        $requests = $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->assertOk()->json('data.requests');
        $this->assertCount(1, $requests);
        $this->assertSame($requestId, $requests[0]['request_id']);
        $this->assertSame((string) $bill->uuid, $requests[0]['bill']['uuid']);
        $this->assertSame((string) $order->uuid, $requests[0]['order']['uuid']);
        $this->assertFalse($requests[0]['reprint']);
        $this->assertSame((int) $this->waiter->id, $requests[0]['requested_by']);
        $this->assertNotEmpty($requests[0]['order']['items']);
        $this->assertSame([(string) $bill->uuid], array_column($requests[0]['billing']['bills'], 'uuid'));
        $this->assertSame((string) $order->uuid, $requests[0]['billing']['order']['uuid']);

        // Another till of the branch loses the race
        $other = $this->pairTerminal($this->owner, $this->branch);
        $this->asDevice($this->till['token'], 'POST', "/pos/bill-print-requests/{$requestId}/printed")->assertOk()->assertJsonPath('data.claimed', true);
        $this->asDevice($this->till['token'], 'POST', "/pos/bill-print-requests/{$requestId}/printed")->assertOk()->assertJsonPath('data.claimed', true);
        $this->asDevice($other['token'], 'POST', "/pos/bill-print-requests/{$requestId}/printed")->assertOk()->assertJsonPath('data.claimed', false);

        // Printed: no longer outstanding for anyone
        $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);
        $this->asDevice($other['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);
        $this->asDevice($this->till['token'], 'POST', '/pos/bill-print-requests/' . Str::uuid() . '/printed')->assertNotFound()->assertJsonPath('data.reason', 'request_not_found');
    }

    public function test_only_the_named_till_open_orders_and_unpaid_bills_of_the_last_hour_are_outstanding(): void
    {
        $order = $this->askedOrder();
        $other = $this->pairTerminal($this->owner, $this->branch);
        $row = fn () => \Illuminate\Support\Facades\DB::table('pos_bill_print_requests')->where('order_id', $order->id);

        // Named to the other till: not this one's
        $row()->update(['target_device' => (string) $other['device']->uuid]);
        $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);
        $this->assertCount(1, $this->asDevice($other['token'], 'GET', '/pos/bill-print-requests')->json('data.requests'));
        $row()->update(['target_device' => null]);

        // Over an hour old: the cashier prints it by hand
        $row()->update(['created_at' => now()->subMinutes(61)]);
        $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);
        $row()->update(['created_at' => now()]);

        // The bill voided (billed again): nothing to print
        OrderBill::where('order_id', $order->id)->update(['status' => 'void']);
        $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);
        OrderBill::where('order_id', $order->id)->update(['status' => 'open']);
        $this->assertCount(1, $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->json('data.requests'));

        // The order closed
        $order->forceFill(['status' => 'delivered'])->save();
        $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);

        // A kitchen screen does not print bills; a till of another branch sees nothing of this one
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $this->asDevice($kds['token'], 'GET', '/pos/bill-print-requests')->assertForbidden()->assertJsonPath('data.reason', 'till_required');
        $order->forceFill(['status' => 'preparing'])->save();
        $elsewhere = $this->pairTerminal($this->owner, $this->otherBranch);
        $this->asDevice($elsewhere['token'], 'GET', '/pos/bill-print-requests')->assertOk()->assertJsonPath('data.requests', []);
        $requestId = (string) $row()->value('request_id');
        $this->asDevice($elsewhere['token'], 'POST', "/pos/bill-print-requests/{$requestId}/printed")->assertNotFound();

        // A staff session token is not a till's own token
        $this->withBearer($this->cashierToken);
        $this->json('GET', '/api/v1/pos/bill-print-requests')->assertUnauthorized();
    }

    public function test_a_relayed_bill_request_keeps_its_key_as_the_print_request_id(): void
    {
        Event::fake([BillPrintRequestedBroadcast::class]);
        $phone = $this->pairTerminal($this->owner, $this->branch);
        $this->pinSession($phone, $this->waiter, '7391');
        $origin = (string) $phone['device']->uuid;
        $order = (string) Str::uuid();
        $key = (string) Str::uuid();

        $results = $this->sync([
            $this->op('order.open', [
                'order' => $order, 'local_number' => 'K1-501', 'channel' => 'pos_hall', 'table_id' => null, 'guests_count' => 2,
                'items' => [$this->line($this->shashlik, '2')], 'send' => true, 'menu_taken_at' => now()->subHour()->toJSON(),
            ], $this->waiter, 5) + ['origin_device' => $origin],
            // Relayed while the hub still thought it was online: the server prints, under the op's key
            ['op_id' => $key] + $this->op('bill.request', ['order' => $order, 'idempotency_key' => $key], $this->waiter, 1) + ['origin_device' => $origin],
        ])->assertOk()->json('data.results');
        $this->assertSame(['applied', 'applied'], array_column($results, 'status'), (string) json_encode($results));

        Event::assertDispatched(BillPrintRequestedBroadcast::class, fn (BillPrintRequestedBroadcast $e) => $e->requestId === $key);
        $this->assertSame($key, $this->asDevice($this->till['token'], 'GET', '/pos/bill-print-requests')->json('data.requests.0.request_id'));
    }
}
