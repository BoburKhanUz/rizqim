<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\FiscalReceipt;
use App\Models\Hall;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\ProductVariant;
use App\Models\RoomTariff;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3-38 follow-up: the order opened from a booking (arrive, or the order a booking moved to on a merge) charges the
 * minimum spend the booking confirmation told the guest — the booking's own bookings.min_spend when set (above or below
 * the room's), else the room's (× guests when per person) — and the fiscal receipt's top-up line is that difference.
 * Real implementations of every POS contract (PosOrderService opens the order).
 */
class PosBookingMinSpendTest extends TestCase
{
    use MenuFixtures;
    use RefreshDatabase;
    use StaffOrderFixtures;

    private User $cashier;

    private string $cashierToken;

    private Hall $hall;

    private DiningTable $room;

    private DiningTable $vipRoom;

    private ProductVariant $shashlik;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 22.10.2026, 18:00 in Tashkent: a weekday
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());

        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        $this->hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        // The room's own minimum: 500 000 for the room
        $this->room = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => '1-xona', 'kind' => 'room', 'seats' => 10, 'min_spend' => '500000', 'min_spend_mode' => 'per_table']);
        $this->vipRoom = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => 'VIP', 'kind' => 'room', 'seats' => 20]);
        RoomTariff::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => '300000']);

        $this->shashlik = $this->makeVariant($this->makeProduct('Shashlik', ['mxik_code' => '10202001007000001']), '18000', ['name' => 'Six', 'stock' => 1000]);

        $till = $this->pairTerminal($this->owner, $this->branch);
        $this->cashierToken = $this->pinSession($till, $this->cashier);
        $this->till('POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function till(string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer($this->cashierToken);
        if ($method !== 'GET') {
            $this->withHeader('Idempotency-Key', (string) Str::uuid());
        }

        return $this->json($method, '/api/v1' . $uri, $data);
    }

    /**
     * A confirmed booking for tonight 19:00, its confirmation's "Minimal buyurtma" value, and the order opened when the
     * guests arrive with 10 shashlik (180 000) on it. Returns [booking uuid, confirmation min spend value, order JSON].
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: string, 1: string|null, 2: array<string, mixed>}
     */
    private function arrived(array $overrides = [], int $guests = 4): array
    {
        $booking = $this->till('POST', '/pos/bookings', array_merge([
            'dining_table_id' => $this->room->id, 'starts_at' => '2026-10-22T19:00:00+05:00', 'guests_planned' => $guests,
            'contact_name' => 'Jamshid', 'confirm' => true,
        ], $overrides))->assertCreated()->json('data.uuid');
        $lines = collect($this->till('GET', "/pos/bookings/{$booking}/confirmation")->assertOk()->json('data.lines'));
        $announced = $lines->firstWhere('kind', 'min_spend')['value'] ?? null;

        Carbon::setTestNow(now()->addMinutes(65));
        $uuid = $this->till('POST', "/pos/bookings/{$booking}/arrive", ['guests' => $guests])->assertOk()->json('data.order.uuid');
        $order = $this->till('GET', "/pos/orders/{$uuid}")->assertOk()->json('data');
        $order = $this->till('POST', "/pos/orders/{$uuid}/rounds", ['version' => $order['version'], 'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 10]]])
            ->assertOk()->json('data');

        return [$booking, $announced, $order];
    }

    public function test_the_booking_s_minimum_above_the_room_s_is_what_the_order_charges(): void
    {
        [, $announced, $order] = $this->arrived(['min_spend' => '1500000']);

        $this->assertSame("1 500 000 so'm", $announced);
        $this->assertSame('1500000.00', $order['charges']['min_spend']);
        $this->assertSame('180000.00', $order['subtotal']);
        $this->assertSame('1320000.00', $order['min_spend_topup_amount']); // 1 500 000 − 180 000
        $this->assertSame('300000.00', $order['room_fee_amount']);
        $this->assertSame('1800000.00', $order['total_price']);
    }

    public function test_the_booking_s_minimum_below_the_room_s_is_what_the_order_charges(): void
    {
        [, $announced, $order] = $this->arrived(['min_spend' => '200000']);

        $this->assertSame("200 000 so'm", $announced);
        $this->assertSame('200000.00', $order['charges']['min_spend']);
        $this->assertSame('20000.00', $order['min_spend_topup_amount']); // not the room's 500 000 − 180 000
        $this->assertSame('500000.00', $order['total_price']);
    }

    public function test_without_the_booking_s_own_minimum_the_room_s_rule_holds(): void
    {
        [, $announced, $order] = $this->arrived();
        $this->assertSame("500 000 so'm", $announced);
        $this->assertSame('500000.00', $order['charges']['min_spend']);
        $this->assertSame('320000.00', $order['min_spend_topup_amount']);

        // Per guest: 150 000 × 4 guests, as the confirmation says
        $this->room->forceFill(['min_spend' => '150000', 'min_spend_mode' => 'per_person'])->save();
        $this->vipRoom->forceFill(['min_spend' => '150000', 'min_spend_mode' => 'per_person'])->save();
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
        [, $announced, $order] = $this->arrived(['dining_table_id' => $this->vipRoom->id]);
        $this->assertSame("150 000 so'm kishi boshiga (jami 600 000 so'm)", $announced);
        $this->assertSame('600000.00', $order['charges']['min_spend']);
        $this->assertSame('420000.00', $order['min_spend_topup_amount']);

        // A booking's 0 is "not set": the room's rule
        Booking::query()->update(['min_spend' => '0']);
        $order = $this->till('POST', "/pos/orders/{$order['uuid']}/guests", ['version' => $order['version'], 'guests_count' => 5])->assertOk()->json('data');
        $this->assertSame('750000.00', $order['charges']['min_spend']);
    }

    public function test_the_booking_s_minimum_holds_for_an_order_without_a_room(): void
    {
        [, $announced, $order] = $this->arrived(['dining_table_id' => null, 'min_spend' => '400000']);

        $this->assertSame("400 000 so'm", $announced);
        $this->assertNull($order['table']);
        $this->assertSame('400000.00', $order['charges']['min_spend']);
        $this->assertSame('220000.00', $order['min_spend_topup_amount']);
        $this->assertSame('0.00', $order['room_fee_amount']);
    }

    public function test_after_a_merge_the_order_the_booking_moved_to_charges_the_booking_s_minimum(): void
    {
        [$booking, , $source] = $this->arrived(['min_spend' => '1000000']);

        // Another party in the VIP room (no minimum of its own); the booked guests join it
        $target = $this->till('POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->vipRoom->id, 'guests_count' => 2])->assertCreated()->json('data');
        $this->assertSame('0.00', $target['charges']['min_spend']);
        $merged = $this->till('POST', "/pos/orders/{$target['uuid']}/merge", ['version' => $target['version'], 'source_uuid' => $source['uuid'], 'source_version' => $source['version']])
            ->assertOk()->json('data');

        $this->assertSame((int) Booking::where('uuid', $booking)->value('id'), (int) Order::where('uuid', $target['uuid'])->value('booking_id'));
        $this->assertSame('1000000.00', $merged['charges']['min_spend']);
        $this->assertSame('820000.00', $merged['min_spend_topup_amount']); // 1 000 000 − 180 000
        // The merged-away order lost the booking, so its minimum is no longer the booking's
        $this->assertNull(Order::where('uuid', $source['uuid'])->value('booking_id'));
        $this->till('GET', "/pos/orders/{$source['uuid']}")->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.charges.min_spend', '500000.00'); // the room's, not the booking's 1 000 000
    }

    public function test_the_fiscal_receipt_s_top_up_line_is_the_booking_s_minimum_difference(): void
    {
        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => [
                    'room_fee' => ['mxik' => '10202002001000001'],
                    'min_spend_topup' => ['mxik' => '10202002002000001'],
                ],
            ],
        ])->save();
        [, , $order] = $this->arrived(['min_spend' => '1500000']);

        $bill = $this->till('POST', "/pos/orders/{$order['uuid']}/bills", ['version' => $order['version'], 'mode' => 'single'])->assertCreated()->json('data.bills.0');
        $this->assertSame('1320000.00', $bill['min_spend_topup_amount']);
        $this->till('POST', "/pos/bills/{$bill['uuid']}/payments", ['tenders' => [['tender' => 'cash', 'amount' => $bill['total']]]])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');

        $receipt = FiscalReceipt::where('subject_type', 'order_bill')->where('subject_id', OrderBill::where('uuid', $bill['uuid'])->value('id'))->firstOrFail();
        $topup = collect($receipt->lines)->firstWhere('mxik_code', '10202002002000001');
        $this->assertNotNull($topup, 'the receipt has the top-up line');
        $this->assertSame("Minimal summaga qo'shimcha", $topup['name']);
        $this->assertSame('1320000.00', $topup['amount']);
        $this->assertSame('1800000.00', (string) $receipt->total);
    }
}
