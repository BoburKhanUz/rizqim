<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Pos\Booking\AdvanceLedgerException;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-17: "Keldi" opens the order through PosOrderOpener with the pre-ordered dishes on hold and the deposits linked;
 * AdvanceLedger offsets them against the bills (P3-18's PosTender::PREPAYMENT).
 */
class PosBookingArriveTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
    }

    public function test_arrive_opens_the_order_with_held_items_and_due_times_and_links_the_deposits(): void
    {
        $category = Category::create(['name' => 'Osh', 'slug' => 'osh-' . Str::random(5), 'is_active' => true]);
        $product = Product::create(['store_id' => $this->store->id, 'category_id' => $category->id, 'name' => 'Osh', 'slug' => 'osh-' . Str::random(5), 'status' => 'active']);
        $osh = ProductVariant::create(['product_id' => $product->id, 'name' => 'Choyxona', 'price' => 110000, 'stock' => 50, 'is_active' => true, 'uuid' => (string) Str::uuid(), 'unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '1']);
        $group = ModifierGroup::create(['store_id' => $this->store->id, 'name' => "Qo'shimcha", 'is_active' => true]);
        $kazi = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Qazi', 'price_delta' => 25000, 'max_quantity' => 2, 'is_active' => true]);
        DB::table('product_modifier_groups')->insert(['product_id' => $product->id, 'modifier_group_id' => $group->id, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $uuid = $this->booked(['guests_planned' => 12]);
        $items = "/api/v1/pos/bookings/{$uuid}/items";
        $this->api($this->cashier, 'POST', $items, ['product_variant_id' => $osh->id, 'quantity' => '4.5', 'modifiers' => [['modifier_id' => $kazi->id]], 'note' => 'Sergo\'sht', 'due_at' => '2026-11-05T20:00:00+05:00'])->assertCreated();
        $this->api($this->cashier, 'POST', $items, ['product_variant_id' => $this->variant->id, 'quantity' => 6])->assertCreated();
        $this->takeDeposit($uuid, '1000000', 'qr')->assertCreated();
        $this->takeDeposit($uuid, '500000', 'cash')->assertCreated();

        $terminal = $this->terminalSession($this->cashier, $this->branch);
        $response = $this->apiToken($terminal['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/arrive", ['guests' => 10], ['Idempotency-Key' => 'arrive-1'])
            ->assertOk()
            ->assertJsonPath('data.booking.status', 'arrived')
            ->assertJsonPath('data.booking.deposit_available', '1500000.00');

        $this->assertCount(1, $this->opener->calls);
        $call = $this->opener->calls[0];
        $booking = Booking::where('uuid', $uuid)->firstOrFail();
        $this->assertSame((int) $this->branch->id, $call['branchId']);
        $this->assertSame('pos_hall', $call['channel']);
        $this->assertSame($this->roomA, $call['tableId']);
        $this->assertSame(10, $call['guestsCount']);
        $this->assertNull($call['waiterId']);
        $this->assertSame($terminal['device_id'], $call['deviceId']);
        $this->assertSame((int) $this->cashier->id, $call['openedByUserId']);
        $this->assertSame((int) $booking->id, $call['bookingId']);
        $this->assertSame([
            [
                'product_variant_id' => (int) $this->variant->id,
                'quantity' => '6.000',
                'due_at' => '2026-11-05T14:00:00.000000Z',
            ],
            [
                'product_variant_id' => (int) $osh->id,
                'quantity' => '4.500',
                'modifiers' => [['modifier_id' => (int) $kazi->id, 'quantity' => 1]],
                'note' => 'Sergo\'sht',
                'due_at' => '2026-11-05T15:00:00.000000Z',
            ],
        ], $call['heldItems']);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        $this->assertSame((int) $booking->id, (int) $order->booking_id);
        $this->assertSame((int) $order->id, (int) $booking->order_id);
        $this->assertSame($order->uuid, $response->json('data.booking.order_uuid'));
        $this->assertSame(2, BookingPayment::where('order_id', $order->id)->count());
        $this->assertSame('1500000.00', app(AdvanceLedger::class)->available($order)->toString());

        // Twice: the order is already open
        $this->apiToken($terminal['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/arrive", ['guests' => 11])
            ->assertStatus(409)->assertJsonPath('data.reason', 'booking_already_arrived')->assertJsonPath('data.order_uuid', $order->uuid);
        $this->assertCount(1, $this->opener->calls);
    }

    public function test_arrive_in_another_room_and_with_the_planned_guests(): void
    {
        $uuid = $this->booked(['guests_planned' => 7]);

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/arrive", ['dining_table_id' => $this->otherBranchRoom])->assertStatus(422)
            ->assertJsonPath('data.reason', 'table_not_found');
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/arrive", ['dining_table_id' => $this->roomC])->assertOk()
            ->assertJsonPath('data.booking.dining_table_id', $this->roomC);

        $this->assertSame($this->roomC, $this->opener->calls[0]['tableId']);
        $this->assertSame(7, $this->opener->calls[0]['guestsCount']);
        $this->assertSame([], $this->opener->calls[0]['heldItems']);
    }

    public function test_the_ledger_offsets_oldest_deposits_first_and_refuses_more_than_is_left(): void
    {
        [$order] = $this->arrivedWithDeposits(['300000', '500000']);
        $ledger = app(AdvanceLedger::class);
        $bill1 = (string) Str::uuid();
        $bill2 = (string) Str::uuid();

        $ledger->apply($order, Money::of('400000'), $bill1);
        $this->assertSame('400000.00', $ledger->available($order)->toString());

        $payments = BookingPayment::orderBy('id')->get();
        $this->assertSame(['applied', 'taken'], $payments->map(fn ($payment) => $payment->status->value)->all());
        $this->assertSame(['300000.00', '100000.00'], $payments->pluck('applied_amount')->all());
        $this->assertSame([[['bill_uuid' => $bill1, 'amount' => '300000.00']], [['bill_uuid' => $bill1, 'amount' => '100000.00']]],
            $payments->map(fn ($payment) => array_map(fn (array $a) => ['bill_uuid' => $a['bill_uuid'], 'amount' => $a['amount']], $payment->applications))->all());

        // The same bill and amount again (a retried payment) changes nothing; another amount is refused
        $ledger->apply($order, Money::of('400000'), $bill1);
        $this->assertSame('400000.00', $ledger->available($order)->toString());
        try {
            $ledger->apply($order, Money::of('100000'), $bill1);
            $this->fail('A second, different offset for one bill was accepted.');
        } catch (AdvanceLedgerException $e) {
            $this->assertSame('already_applied', $e->reason);
        }

        try {
            $ledger->apply($order, Money::of('400000.01'), $bill2);
            $this->fail('More than is left was applied.');
        } catch (AdvanceLedgerException $e) {
            $this->assertSame('insufficient_advance', $e->reason);
            $this->assertSame("Avans yetarli emas: mavjud 400000.00 so'm, so'ralgan 400000.01 so'm.", $e->getMessage());
        }

        $ledger->apply($order, Money::of('400000'), $bill2);
        $this->assertTrue($ledger->available($order)->isZero());
        $this->assertSame(['applied', 'applied'], BookingPayment::orderBy('id')->get()->map(fn ($payment) => $payment->status->value)->all());
    }

    public function test_the_ledger_has_nothing_for_an_order_without_a_booking(): void
    {
        $ledger = app(AdvanceLedger::class);
        $plain = $this->makeOrder(\App\Enums\OrderStatus::NEW);
        $this->assertTrue($ledger->available($plain)->isZero());
        $this->expectException(AdvanceLedgerException::class);
        $ledger->apply($plain, Money::of('1'), (string) Str::uuid());
    }

    public function test_what_is_left_after_the_bill_can_be_refunded_once_the_order_is_closed(): void
    {
        [$order, $uuid] = $this->arrivedWithDeposits(['1000000']);
        app(AdvanceLedger::class)->apply($order, Money::of('800000'), (string) Str::uuid());

        $order->forceFill(['status' => \App\Enums\OrderStatus::DELIVERED])->save();
        event(new \App\Events\OrderStatusUpdated($order->refresh()));

        $payment = BookingPayment::firstOrFail();
        $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits/{$payment->uuid}/refund", ['reason' => 'Qoldiq qaytarildi'])->assertOk()
            ->assertJsonPath('data.payment.status', 'refunded')
            ->assertJsonPath('data.payment.applied_amount', '800000.00')
            ->assertJsonPath('data.payment.refunded_amount', '200000.00');
        $this->assertTrue(app(AdvanceLedger::class)->available($order)->isZero());
    }

    /**
     * @param  list<string>  $amounts
     * @return array{0: Order, 1: string}
     */
    private function arrivedWithDeposits(array $amounts): array
    {
        $uuid = $this->booked();
        foreach ($amounts as $amount) {
            $this->takeDeposit($uuid, $amount, 'cash')->assertCreated();
        }
        $orderUuid = $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/arrive")->assertOk()->json('data.order.uuid');

        return [Order::where('uuid', $orderUuid)->firstOrFail(), $uuid];
    }
}
