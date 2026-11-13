<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Billing\BillStatus;
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
 * P6 review fixes (C3, C4): the waiter's pre-check names one till when the branch has several and none is set to print
 * (the LAN hub prints it, never every till); a copy asked of a bill the order outgrew prints the current bill, never
 * the old total (a split the till made is refused: 409 `bill_stale`); and once the order changed after the waiter asked,
 * asking again prints again.
 */
class PosBillPrintRoutingTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    /** @var array{device: \App\Models\Device, token: string} */
    private array $phone;

    private string $phoneToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
        $this->phone = $this->pairTerminal($this->owner, $this->branch);
        $this->phoneToken = $this->pinSession($this->phone, $this->waiter);
        Event::fake([BillPrintRequestedBroadcast::class]);
    }

    private function asWaiter(string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer($this->phoneToken)->withHeader('Idempotency-Key', (string) Str::uuid());

        return $this->json($method, '/api/v1' . $uri, $data);
    }

    private function openRoom(): Order
    {
        $uuid = $this->asWaiter('POST', '/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => '1']], 'send' => true,
        ])->assertSuccessful()->json('data.uuid');

        return Order::where('uuid', $uuid)->sole();
    }

    private function round(Order $order): void
    {
        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/rounds", [
            'version' => (int) $order->refresh()->version, 'items' => [['variant_id' => $this->tea->uuid, 'quantity' => '1']], 'send' => true,
        ])->assertSuccessful();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function printed(): array
    {
        return Event::dispatched(BillPrintRequestedBroadcast::class)->map(fn (array $event) => $event[0]->broadcastWith())->values()->all();
    }

    public function test_with_two_tills_and_the_defaults_the_hub_is_named_to_print(): void
    {
        $this->pairTerminal($this->owner, $this->branch); // the second till (bar), no printing till, no bill printer
        $order = $this->openRoom();

        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk();

        $this->assertCount(1, $this->printed());
        $this->assertSame((string) $this->till['device']->uuid, $this->printed()[0]['target_device'], 'every till of the branch would print the pre-check');
    }

    public function test_a_reprint_of_a_bill_the_order_outgrew_prints_the_current_bill(): void
    {
        $order = $this->openRoom();
        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk();
        $old = OrderBill::where('order_id', $order->id)->sole();
        $this->round($order);

        // The copy is asked (the cashier holds pos.reprint_bill; a waiter brings a manager's PIN)
        $this->tillAs('POST', "/pos/bills/{$old->uuid}/print-request")->assertOk();

        $this->assertSame(BillStatus::VOID, $old->refresh()->status);
        $current = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->sole();
        $this->assertGreaterThan((float) $old->total, (float) $current->total, 'the current bill has the new round');
        $last = $this->printed()[1];
        $this->assertSame($current->uuid, $last['bill']['uuid'], 'the old total is printed');
        $this->assertFalse($last['reprint'], 'the current bill was never printed: not a copy');

        // A current bill is copied as before
        $this->tillAs('POST', "/pos/bills/{$current->uuid}/print-request")->assertOk();
        $this->assertSame([$current->uuid, true], [$this->printed()[2]['bill']['uuid'], $this->printed()[2]['reprint']]);
    }

    public function test_a_reprint_of_an_outgrown_split_is_refused_with_bill_stale(): void
    {
        $order = $this->openRoom();
        $this->tillAs('POST', "/pos/orders/{$order->uuid}/bills", ['version' => (int) $order->refresh()->version, 'mode' => 'equal', 'parts' => 2])->assertSuccessful();
        $bills = OrderBill::where('order_id', $order->id)->orderBy('number')->get();
        $this->assertCount(2, $bills);
        $this->round($order);

        $this->tillAs('POST', "/pos/bills/{$bills[0]->uuid}/print-request")
            ->assertStatus(409)->assertJsonPath('data.reason', 'bill_stale');
        $this->assertSame([], $this->printed());
        // The till's split is left as it was
        $this->assertSame(2, OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->count());
    }

    public function test_after_another_round_asking_for_the_bill_again_prints_the_current_bill(): void
    {
        $order = $this->openRoom();
        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk();
        $first = OrderBill::where('order_id', $order->id)->sole();
        // Asked again with nothing changed: nothing new
        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk();
        $this->assertCount(1, $this->printed());

        $this->round($order);
        $version = (int) $order->refresh()->version;
        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk()->assertJsonPath('data.version', $version + 1);

        $this->assertCount(2, $this->printed());
        $current = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->sole();
        $this->assertNotSame($first->uuid, $current->uuid);
        $this->assertSame($current->uuid, $this->printed()[1]['bill']['uuid']);
        // And again with nothing changed since: nothing new
        $this->asWaiter('POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk();
        $this->assertCount(2, $this->printed());
    }
}
