<?php

namespace Tests\Feature\PosHostess;

use App\Domain\Pos\Setup\FormatProfile;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-24 (C4): the guest split. A line carries a guest number (`seat_no`); POST /pos/orders/{uuid}/bills with mode
 * `by_guest` makes one bill per guest (lowest first): a guest's own lines on their bill, a line nobody owns shared
 * equally (the remainder on the first bill), the service charge following each bill's amount; to the tiyin.
 */
class PosGuestSplitTest extends TestCase
{
    use PosHostessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurantFloor();
        $this->serviceFee('10', $this->hall);
    }

    /**
     * Guest 1: 2 shashlik (36 000), guest 2: 1 shashlik + 1 tea (23 000), guest 3: 1 steak (120 000);
     * nobody's: 1 bread (4 000, shared by three: 1 334 / 1 333 / 1 333).
     */
    private function seatedOrder(): Order
    {
        return $this->openedOrder($this->openPos($this->waiter, $this->table, 3, [
            'items' => [
                $this->line($this->shashlik, 2, ['seat_no' => 1]),
                $this->line($this->shashlik, 1, ['seat_no' => 2]),
                $this->line($this->tea, 1, ['seat_no' => 2]),
                $this->line($this->steak, 1, ['seat_no' => 3]),
                $this->line($this->bread, 1),
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function split(Order $order, string $as = 'cashier'): array
    {
        return $this->pos($this->{$as}, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'by_guest'])
            ->assertCreated()->json('data');
    }

    public function test_one_bill_per_guest_with_the_shared_line_split_equally(): void
    {
        $order = $this->seatedOrder();
        $this->assertSame('201300.00', (string) $order->fresh()->total_price); // 183 000 + the hall's 10% service charge
        $state = $this->split($order);

        $bills = $state['bills'];
        $this->assertCount(3, $bills);
        $this->assertSame([1, 2, 3], array_column($bills, 'seat_no'));
        $this->assertSame([1, 1, 1], array_column($bills, 'guests_share'));
        $this->assertSame(['by_guest', 'by_guest', 'by_guest'], array_column($bills, 'split_mode'));

        // The bills add up to the order to the tiyin, charges included
        $total = array_sum(array_map(fn (array $bill) => (float) $bill['total'], $bills));
        $this->assertSame((float) $order->fresh()->total_price, $total);
        $serviceCharge = array_sum(array_map(fn (array $bill) => (float) $bill['service_charge_amount'], $bills));
        $this->assertSame((float) $order->fresh()->service_charge_amount, $serviceCharge);

        // Each bill carries its guest's lines, and a share of the bread
        $guestOne = collect($bills[0]['items'])->groupBy('product_name');
        $this->assertSame(2, (int) $guestOne['Shashlik'][0]['quantity']);
        $bread = fn (array $bill) => collect($bill['items'])->firstWhere('product_name', 'Non');
        $this->assertSame(0.334, (float) $bread($bills[0])['quantity']);
        $this->assertSame(0.333, (float) $bread($bills[1])['quantity']);
        $this->assertSame(0.333, (float) $bread($bills[2])['quantity']);
        $shares = array_map(fn (array $bill) => (float) $bread($bill)['amount'], $bills);
        $this->assertSame(4000.0, round(array_sum($shares), 2));

        // Guest 3 (the steak) pays the most; the service charge follows their amount
        $this->assertGreaterThan((float) $bills[0]['total'], (float) $bills[2]['total']);
        $this->assertSame((float) $bills[2]['total'], round((float) $bills[2]['subtotal'] * 1.1, 2));

        $this->assertDatabaseHas('order_bills', ['order_id' => $order->id, 'seat_no' => 2, 'split_mode' => 'by_guest']);
    }

    public function test_every_bill_can_be_paid_by_its_guest_and_the_order_closes(): void
    {
        $order = $this->seatedOrder();
        $till = $this->openTill();
        $bills = $this->split($order)['bills'];

        $this->assertCount(3, $bills);
        foreach ($bills as $bill) {
            $this->pos($till, 'POST', "/pos/bills/{$bill['uuid']}/payments", [
                'tenders' => [['tender' => 'cash', 'amount' => $bill['total']]],
            ])->assertSuccessful();
        }
        $this->assertSame(3, OrderBill::where('order_id', $order->id)->where('status', 'paid')->count());
        $this->pos($till, 'POST', "/pos/orders/{$order->uuid}/close", ['version' => $this->versionOf($order)])->assertSuccessful();
        $this->assertNotNull($order->fresh()->closed_at);
    }

    public function test_the_remainder_rule_is_documented_and_deterministic(): void
    {
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 3, [
            'items' => [
                $this->line($this->tea, 1, ['seat_no' => 1]),
                $this->line($this->tea, 1, ['seat_no' => 2]),
                $this->line($this->shashlik, 1),
            ],
        ]));
        $first = $this->split($order)['bills'];
        $this->assertCount(2, $first);

        // 18 000 in two: 9 000 each; the shared line is shared in halves, no tiyin lost
        $shared = fn (array $bill) => (float) collect($bill['items'])->firstWhere('product_name', 'Shashlik')['amount'];
        $this->assertSame(9000.0, $shared($first[0]));
        $this->assertSame(9000.0, $shared($first[1]));
        $this->assertSame(0, OrderBillItem::whereIn('bill_id', collect($first)->map(fn ($bill) => OrderBill::where('uuid', $bill['uuid'])->value('id')))->where('quantity', '<', 0)->count());
    }

    public function test_an_order_without_guest_numbers_cannot_be_split_by_guest(): void
    {
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->tea, 2)]]));

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'by_guest'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'no_seat_lines');
    }

    public function test_a_waiter_cannot_split_and_the_split_needs_the_module(): void
    {
        $order = $this->seatedOrder();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'by_guest'])
            ->assertForbidden()->assertJsonPath('data.reason', 'permission_denied');

        $this->profile(FormatProfile::RESTORAN, ['guest_split' => false]);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'by_guest'])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');

        // The other split types never ask
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'equal', 'parts' => 2])->assertCreated();
    }

    public function test_the_choyxona_keeps_its_three_split_types_and_has_no_guest_split(): void
    {
        $this->profile(FormatProfile::CHOYXONA);
        $order = $this->seatedOrder();

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'by_guest'])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => $this->versionOf($order), 'mode' => 'single'])->assertCreated();
    }

    public function test_a_guests_paid_bill_stays_and_the_others_are_split_again(): void
    {
        $order = $this->seatedOrder();
        $till = $this->openTill();
        $bills = $this->split($order)['bills'];

        // Guest 1 pays and leaves; the others ask again: guest 2 and 3 only, guest 1's bread share stays paid
        $this->pos($till, 'POST', "/pos/bills/{$bills[0]['uuid']}/payments", ['tenders' => [['tender' => 'cash', 'amount' => $bills[0]['total']]]])->assertSuccessful();
        $again = $this->split($order->fresh())['bills'];
        $open = array_values(array_filter($again, fn (array $bill) => $bill['status'] !== 'void' && $bill['paid_amount'] === '0.00'));
        $this->assertSame([2, 3], array_column($open, 'seat_no'));
        $sum = array_sum(array_map(fn (array $bill) => (float) $bill['total'], $again));
        $this->assertSame((float) $order->fresh()->total_price, round($sum, 2));
    }

    public function test_a_lines_guest_number_is_set_and_cleared_and_audited(): void
    {
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->tea, 1)]]));
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/seat", ['version' => $this->versionOf($order), 'seat_no' => 2])
            ->assertOk()->assertJsonPath('data.items.0.seat_no', 2);
        $this->assertSame(2, (int) $item->fresh()->seat_no);
        $this->assertSame(1, AuditLog::where('action', 'pos.line_seat_changed')->count());

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/seat", ['version' => $this->versionOf($order), 'seat_no' => null])
            ->assertOk()->assertJsonPath('data.items.0.seat_no', null);

        // A stale version is a 409; the number must be 1-500; another waiter needs a manager
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/seat", ['version' => 1, 'seat_no' => 3])->assertStatus(409);
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/seat", ['version' => $this->versionOf($order), 'seat_no' => 501])->assertStatus(422);
        $this->pos($this->waiter2, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/seat", ['version' => $this->versionOf($order), 'seat_no' => 3])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
    }

    public function test_the_seat_endpoint_needs_the_guest_split_module(): void
    {
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->tea, 1)]]));
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->profile(FormatProfile::CHOYXONA);

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/seat", ['version' => $this->versionOf($order), 'seat_no' => 1])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }
}
