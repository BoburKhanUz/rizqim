<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Billing\BillStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-18: making and splitting bills (single, equal N with the remainder tiyin on the first bill, by items with
 * fractional kg shares), the discount and charges shared in proportion with exact sums, printing and the "NUSXA"
 * copy, billing again after a new round, version, Idempotency-Key, permissions and tenant isolation.
 */
class PosBillsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    public function test_a_single_bill_carries_every_line_the_discount_and_the_charges(): void
    {
        $order = $this->posOrder([['Osh', '1.5', '120000'], ['Choy', '2', '10000']], [
            'discount' => '13000', 'service_charge_amount' => '11700', 'room_fee_amount' => '50000',
        ]);

        $response = $this->bill($order)->assertCreated();

        $bill = $response->json('data.bills.0');
        $this->assertSame('130000.00', $bill['subtotal']);
        $this->assertSame('13000.00', $bill['discount']);
        $this->assertSame('11700.00', $bill['service_charge_amount']);
        $this->assertSame('50000.00', $bill['room_fee_amount']);
        $this->assertSame('178700.00', $bill['total']);
        $this->assertSame('178700.00', $order->refresh()->total_price);
        $this->assertSame(4, $bill['guests_share']);
        $this->assertSame('44675.00', $bill['per_guest_amount']);
        $this->assertSame([1.5, 2], array_column($bill['items'], 'quantity'));
        $this->assertSame('13000.00', $this->sumOf(array_column($bill['items'], 'discount_amount')));
        $this->assertSame('0.00', $response->json('data.order.unbilled_amount'));
        $this->assertSame(2, $order->refresh()->version);
    }

    public function test_an_equal_split_gives_equal_totals_with_the_remainder_tiyin_on_the_first_bill(): void
    {
        $order = $this->posOrder([['Osh', '1', '100000'], ['Non', '3', '9000.01'], ['Salat', '1', '25000']], [
            'discount' => '1000', 'service_charge_amount' => '13300', 'place_fee_amount' => '7000',
        ]);
        $total = '153300.01';

        $bills = $this->bill($order, 'equal', ['parts' => 3])->assertCreated()->json('data.bills');

        $this->assertSame(['51100.01', '51100.00', '51100.00'], array_column($bills, 'total'));
        $this->assertSame($total, $this->sumOf(array_column($bills, 'total')));

        // Every line and charge is fully shared out, and each bill adds up by itself
        foreach (['subtotal', 'discount', 'service_charge_amount', 'place_fee_amount', 'room_fee_amount'] as $column) {
            $this->assertSame(\App\Support\Money::of($order->getAttribute($column) ?? $order->items()->sum('total_price'))->toString(), $this->sumOf(array_column($bills, $column)), $column);
        }
        foreach ($bills as $bill) {
            $lines = \App\Support\Money::of($this->sumOf(array_column($bill['items'], 'amount')))->minus($this->sumOf(array_column($bill['items'], 'discount_amount')));
            $this->assertSame($bill['total'], $lines->plus($bill['service_charge_amount'])->plus($bill['place_fee_amount'])->toString());
        }
        foreach ($order->items as $item) {
            $shares = OrderBillItem::where('order_item_id', $item->id)->get();
            $this->assertSame((string) $item->getRawOriginal('total_price'), $this->sumOf($shares->pluck('amount')));
            $this->assertEqualsWithDelta((float) $item->quantity, (float) $shares->sum('quantity'), 0.0001);
        }
        // Three bills, three receipts: the bread's 3 pieces are one each
        $this->assertSame([1, 1, 1], array_map(fn ($bill) => $bill['items'][1]['quantity'], $bills));
    }

    public function test_a_split_by_items_shares_a_kilogram_dish_and_the_charges_in_proportion(): void
    {
        $order = $this->posOrder([['Osh', '1.5', '120000'], ['Choy', '2', '10000']], [
            'discount' => '6500', 'service_charge_amount' => '12350', 'room_fee_amount' => '30000',
        ]);
        [$osh, $tea] = $order->items()->orderBy('id')->get()->all();

        $bills = $this->bill($order, 'items', ['bills' => [
            ['items' => [['order_item_id' => $osh->id, 'quantity' => '0.5'], ['order_item_id' => $tea->id, 'quantity' => '1']], 'guests' => 2],
            ['items' => [['order_item_id' => $osh->id, 'quantity' => '1'], ['order_item_id' => $tea->id, 'quantity' => '1']]],
        ]])->assertCreated()->json('data.bills');

        $this->assertSame(['45000.00', '85000.00'], array_column($bills, 'subtotal')); // 40 000 + 5 000; 80 000 + 5 000
        $this->assertSame(['2250.00', '4250.00'], array_column($bills, 'discount'));
        $this->assertSame(['4275.00', '8075.00'], array_column($bills, 'service_charge_amount'));
        $this->assertSame(['10384.62', '19615.38'], array_column($bills, 'room_fee_amount'));
        $this->assertSame($order->refresh()->total_price, $this->sumOf(array_column($bills, 'total')));
        $this->assertSame([0.5, 1], [$bills[0]['items'][0]['quantity'], $bills[1]['items'][0]['quantity']]);
        $this->assertSame(2, $bills[0]['guests_share']);
    }

    public function test_a_split_by_items_must_give_out_every_line_in_full(): void
    {
        $order = $this->posOrder();
        [$osh, $tea] = $order->items()->orderBy('id')->get()->all();

        $this->bill($order, 'items', ['bills' => [
            ['items' => [['order_item_id' => $osh->id, 'quantity' => '1'], ['order_item_id' => $tea->id, 'quantity' => '2']]],
        ]])->assertStatus(422)->assertJsonPath('data.reason', 'items_not_covered');

        $this->bill($order, 'items', ['bills' => [
            ['items' => [['order_item_id' => $osh->id, 'quantity' => '1.5'], ['order_item_id' => $tea->id, 'quantity' => '2']]],
            ['items' => [['order_item_id' => 999999, 'quantity' => '1']]],
        ]])->assertStatus(422)->assertJsonPath('data.reason', 'item_not_billable');

        $this->assertSame(0, OrderBill::count());
    }

    public function test_a_waiter_makes_one_bill_but_only_a_cashier_splits(): void
    {
        $order = $this->posOrder();
        $waiterToken = $this->pinSession($this->till, $this->waiter);

        $this->bill($order, 'equal', ['parts' => 2], $waiterToken)->assertForbidden()->assertJsonPath('data.reason', 'permission_denied');
        $this->bill($order, 'single', [], $waiterToken)->assertCreated();
    }

    public function test_a_stale_version_is_a_conflict(): void
    {
        $order = $this->posOrder();

        $this->pos('POST', "/orders/{$order->uuid}/bills", ['version' => 0, 'mode' => 'single'])
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'version_conflict')
            ->assertJsonPath('data.version', 1);
    }

    public function test_printing_locks_the_order_and_a_copy_needs_pos_reprint_bill(): void
    {
        $order = $this->posOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pos('POST', "/bills/{$bill}/print")->assertOk()->assertJsonPath('data.copy', false)->assertJsonPath('data.bill.status', 'printed');
        $this->assertNotNull($order->refresh()->bill_locked_at);

        // P3-37: a cashier prints a copy ("NUSXA") alone by default
        $this->pos('POST', "/bills/{$bill}/print")->assertOk()->assertJsonPath('data.copy', true)->assertJsonPath('data.bill.printed_count', 2);

        // A store whose owner took pos.reprint_bill away from cashiers: a manager's PIN
        app(\App\Services\Auth\PermissionService::class)->setOverride($this->store, \App\Enums\UserRole::CASHIER, \App\Enums\Permission::POS_REPRINT_BILL, false);
        $this->pos('POST', "/bills/{$bill}/print")->assertForbidden()->assertJsonPath('data.reason', 'approval_required');

        $token = $this->approval('pos.reprint_bill');
        $this->pos('POST', "/bills/{$bill}/print", ['approval_token' => $token])->assertOk()
            ->assertJsonPath('data.copy', true)->assertJsonPath('data.bill.printed_count', 3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.bill_reprinted', 'user_id' => $this->cashier->id]);

        // A manager reprints by themselves
        $this->posAs($this->manager, 'POST', "/bills/{$bill}/print")->assertOk()->assertJsonPath('data.bill.printed_count', 4);
    }

    public function test_a_round_added_after_the_bill_needs_a_new_bill(): void
    {
        $order = $this->posOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pos('POST', "/bills/{$bill}/print")->assertOk();

        $this->line($order, 'Shashlik', '2', '36000');
        $order->forceFill(['subtotal' => '166000', 'total_price' => '166000', 'version' => $order->version + 1])->save();

        // A copy of a bill the order outgrew: refused (P3-37: the cashier may print copies, so this is the real check)
        $this->pos('POST', "/bills/{$bill}/print")->assertStatus(409)->assertJsonPath('data.reason', 'order_changed');
        $this->getJson("/api/v1/pos/orders/{$order->uuid}/bills")->assertOk()->assertJsonPath('data.order.bills_out_of_date', true);

        $fresh = $this->bill($order)->assertCreated()->json('data');
        $this->assertSame('166000.00', $fresh['bills'][0]['total']);
        $this->assertSame(2, $fresh['bills'][0]['number']);
        $this->assertSame(BillStatus::VOID, OrderBill::where('uuid', $bill)->first()?->status);
    }

    public function test_bills_need_an_idempotency_key_and_a_repeat_is_replayed(): void
    {
        $order = $this->posOrder();

        $this->withBearer($this->cashierToken)->postJson("/api/v1/pos/orders/{$order->uuid}/bills", ['version' => 1, 'mode' => 'single'])
            ->assertStatus(428)->assertJsonPath('data.reason', 'idempotency_key_required');

        $first = $this->pos('POST', "/orders/{$order->uuid}/bills", ['version' => 1, 'mode' => 'single'], null, 'bill-key-1')->assertCreated();
        $again = $this->pos('POST', "/orders/{$order->uuid}/bills", ['version' => 1, 'mode' => 'single'], null, 'bill-key-1')->assertCreated();

        $this->assertSame($first->json('data.bills.0.uuid'), $again->json('data.bills.0.uuid'));
        $this->assertSame('true', $again->headers->get('Idempotent-Replayed'));
        $this->assertSame(1, OrderBill::count());
    }

    public function test_another_branch_or_store_never_sees_the_bills(): void
    {
        $order = $this->posOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $otherCashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);
        $otherTill = $this->pairTerminal($this->owner, $this->otherBranch);
        $otherToken = $this->pinSession($otherTill, $otherCashier);
        $this->withBearer($otherToken)->getJson("/api/v1/pos/orders/{$order->uuid}/bills")->assertNotFound();
        $this->pos('POST', "/bills/{$bill}/print", [], $otherToken)->assertNotFound();

        // The owner on the other branch's till acts in that branch only
        $ownerToken = $this->pinSession($otherTill, $this->owner, '7314');
        $this->withBearer($ownerToken)->getJson("/api/v1/pos/orders/{$order->uuid}/bills")->assertNotFound();

        $foreign = $this->makeStore('Boshqa choyxona');
        $foreignManager = $this->makeStaff(UserRole::MANAGER, $this->makeBranch($foreign, 'F'), $foreign);
        $this->posAs($foreignManager, 'GET', "/orders/{$order->uuid}/bills")->assertNotFound();
        $this->posAs($this->owner, 'GET', "/orders/{$order->uuid}/bills")->assertOk();
    }

    public function test_an_order_already_receipted_by_an_online_payment_is_not_billed_again(): void
    {
        $order = $this->posOrder([['Osh', '1', '50000']]);
        \App\Models\FiscalReceipt::create([
            'store_id' => $this->store->id, 'type' => 'sale', 'payment_form' => 'card', 'status' => 'issued', 'driver' => 'external',
            'provider' => 'payme', 'idempotency_key' => 'payme:1', 'subject_type' => 'order', 'subject_id' => $order->id,
            'total' => '50000', 'lines' => [], 'payments' => [],
        ]);

        $this->bill($order)->assertStatus(409)->assertJsonPath('data.reason', 'order_receipted_online');
        $this->assertSame(0, OrderBill::count());
    }

    public function test_an_online_order_is_not_billed_here(): void
    {
        $order = $this->posOrder([['Osh', '1', '50000']], ['channel' => 'telegram']);

        $this->bill($order)->assertStatus(409)->assertJsonPath('data.reason', 'not_pos_order');
        $this->assertNull(Order::find($order->id)->queue_number);
    }
}
