<?php

namespace Tests\Feature;

use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RoomTariff;
use App\Models\RoomTariffCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P3-12: the charges engine (PosChargesCalculator): the room fee by the room tariff and the business date's day type
 * (weekday, weekend, holiday, the night after midnight), the most specific tariff, the per-guest place fee, the
 * minimum spend and its top-up, the service charge of the hall, waiving and restoring a charge, and no charge ever on
 * a Telegram order.
 */
class PosChargesTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        // Tashkent (UTC+5): 2026-10-21 is a Wednesday, 2026-10-24 a Saturday
        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tariff(string $dayType, string $price, array $attributes = []): RoomTariff
    {
        return RoomTariff::create(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'table_kind' => 'room',
            'day_type' => $dayType,
            'price' => $price,
        ], $attributes));
    }

    private function open(?\App\Models\DiningTable $table = null, int $guests = 4, array $items = []): Order
    {
        return $this->openedOrder($this->openPos($this->cashier, $table ?? $this->room, $guests, $items ? ['items' => $items] : []));
    }

    public function test_the_room_fee_follows_the_day_type_of_the_business_date(): void
    {
        $this->tariff('weekday', '100000');
        $this->tariff('weekend', '200000');

        $wednesday = $this->open();
        $this->assertSame('100000.00', $wednesday->room_fee_amount);
        $this->assertSame('100000.00', $wednesday->total_price);

        Carbon::setTestNow('2026-10-24 10:00:00'); // Saturday 15:00 in Tashkent
        $saturday = $this->openedOrder($this->openPos($this->cashier, $this->vipRoom, 4));
        $this->pos($this->cashier, 'GET', "/pos/orders/{$saturday->uuid}")
            ->assertOk()
            ->assertJsonPath('data.room_fee_amount', '200000.00')
            ->assertJsonPath('data.charges.day_type', 'weekend')
            ->assertJsonPath('data.charges.business_date', '2026-10-24');

        // Monday 03:30 in Tashkent is still Sunday's business day (the day starts at 05:00): weekend
        Carbon::setTestNow('2026-10-25 22:30:00');
        $night = $this->openedOrder($this->openPos($this->cashier, $this->place($this->hall, '2-xona', 'room'), 4));
        $this->assertSame('200000.00', $night->room_fee_amount);
        $this->assertSame('2026-10-25', app(PosChargesCalculator::class)->compute($night)->businessDate);
    }

    public function test_holidays_and_weekend_days_come_from_the_branch_calendar_with_fallbacks(): void
    {
        $this->tariff('weekday', '100000');
        $this->tariff('weekend', '150000');
        RoomTariffCalendar::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'weekend_days' => [3]]);
        // P3-26: the branch's one holiday list is its POS settings' (the calendar keeps the weekend days)
        $this->branch->forceFill(['pos_settings' => [...($this->branch->pos_settings ?? []), 'holidays' => ['2026-10-22']]])->save();

        // Wednesday is this branch's day off
        $this->assertSame('150000.00', $this->open()->room_fee_amount);

        // A holiday without its own price is priced as a weekend; with one, its own
        Carbon::setTestNow('2026-10-22 12:00:00');
        $this->assertSame('150000.00', $this->openedOrder($this->openPos($this->cashier, $this->vipRoom, 2))->room_fee_amount);
        $this->tariff('holiday', '300000');
        $this->assertSame('300000.00', $this->openedOrder($this->openPos($this->cashier, $this->place($this->hall, '3-xona', 'room'), 2))->room_fee_amount);

        // A weekend without its own price falls back to the weekday's
        RoomTariff::where('day_type', 'weekend')->delete();
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->assertSame('100000.00', $this->openedOrder($this->openPos($this->cashier, $this->place($this->hall, '4-xona', 'room'), 2))->room_fee_amount);
    }

    public function test_the_most_specific_tariff_wins_and_dates_limit_it(): void
    {
        $this->tariff('weekday', '100000');
        $this->tariff('weekday', '250000', ['hall_id' => $this->vipHall->id]);
        $this->tariff('weekday', '400000', ['table_kind' => null, 'dining_table_id' => $this->vipRoom->id, 'valid_from' => '2026-11-01']);
        $this->tariff('weekday', '20000', ['table_kind' => 'tapchan']);

        $this->assertSame('100000.00', $this->open($this->room)->room_fee_amount);
        // The VIP room's own tariff starts next month: the VIP hall's room price until then
        $vip = $this->open($this->vipRoom);
        $this->assertSame('250000.00', $vip->room_fee_amount);
        $this->assertSame('20000.00', $this->open($this->tapchan)->room_fee_amount);
        // A plain table has no tariff: no room fee
        $this->assertSame('0.00', $this->open($this->table, 2)->room_fee_amount);

        // From its start date the VIP room's own price (the next party, a Wednesday in November)
        $vip->forceFill(['status' => 'delivered'])->save();
        Carbon::setTestNow('2026-11-04 12:00:00');
        $this->assertSame('400000.00', $this->open($this->vipRoom, 2)->room_fee_amount);
        // Other VIP rooms keep the hall's price
        $this->assertSame('250000.00', $this->open($this->place($this->vipHall, 'VIP-2', 'room'), 2)->room_fee_amount);
    }

    public function test_minimum_spend_per_room_and_per_guest_with_the_top_up_line(): void
    {
        $this->room->forceFill(['min_spend' => '500000', 'min_spend_mode' => 'per_table'])->save();
        $order = $this->open($this->room, 4, [$this->line($this->shashlik, 10)]);

        // 500 000 − 180 000
        $this->assertSame('320000.00', $order->min_spend_topup_amount);
        $this->assertSame('500000.00', $order->total_price);

        // More food: a smaller top-up; past the minimum: none
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->shashlik, 15)]])
            ->assertOk()->assertJsonPath('data.min_spend_topup_amount', '50000.00')->assertJsonPath('data.charges.min_spend', '500000.00');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'items' => [$this->line($this->shashlik, 5)]])
            ->assertOk()->assertJsonPath('data.min_spend_topup_amount', '0.00')->assertJsonPath('data.total_price', '540000.00');

        // Per guest: 150 000 × 6 guests = 900 000, less 540 000
        $this->room->forceFill(['min_spend' => '150000', 'min_spend_mode' => 'per_person'])->save();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/guests", ['version' => 3, 'guests_count' => 6])
            ->assertOk()->assertJsonPath('data.min_spend_topup_amount', '360000.00')->assertJsonPath('data.total_price', '900000.00');
    }

    public function test_the_service_charge_of_the_hall_else_the_branch_else_the_store(): void
    {
        $this->serviceFee('5')->forceFill(['branch_id' => null])->save(); // the store's
        $items = [$this->line($this->shashlik, 10)]; // 180 000

        $this->assertSame('9000.00', $this->open($this->table, 2, $items)->service_charge_amount);

        $this->serviceFee('10'); // the branch's
        $this->assertSame('18000.00', $this->open($this->tapchan, 2, $items)->service_charge_amount);

        $this->serviceFee('15', $this->vipHall);
        $vip = $this->open($this->vipRoom, 2, $items);
        $this->assertSame('27000.00', $vip->service_charge_amount);
        $this->assertSame('207000.00', $vip->total_price);

        // A self-service zone: 0%
        $yard = \App\Models\Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Hovli']);
        $this->serviceFee('0', $yard);
        $this->assertSame('0.00', $this->open($this->place($yard, 'Hovli-1', 'table'), 2, $items)->service_charge_amount);

        // The hall's row never applies outside it (DiningFeeCalculator for other channels and halls)
        $this->assertSame('10.00', app(\App\Services\Order\DiningFeeCalculator::class)->settingsFor($this->store->id, $this->branch->id)?->service_charge_percent);
    }

    public function test_room_fee_service_charge_place_fee_and_top_up_add_up(): void
    {
        $this->tariff('weekday', '150000');
        $this->serviceFee('10', null, '5000', 'per_person');
        $this->room->forceFill(['min_spend' => '400000'])->save();
        $order = $this->open($this->room, 8, [$this->line($this->shashlik, 10)]);

        $this->assertSame('180000.00', $order->subtotal);
        $this->assertSame('18000.00', $order->service_charge_amount); // 10% of the food only
        $this->assertSame('40000.00', $order->place_fee_amount); // 5 000 × 8 guests
        $this->assertSame('150000.00', $order->room_fee_amount);
        $this->assertSame('220000.00', $order->min_spend_topup_amount); // 400 000 − 180 000
        $this->assertSame('608000.00', $order->total_price);
    }

    public function test_waiving_a_charge_needs_orders_discount_or_an_approval_and_can_be_restored(): void
    {
        $this->tariff('weekday', '150000');
        $this->serviceFee('10');
        $order = $this->open($this->room, 4, [$this->line($this->shashlik, 10)]);
        $this->assertSame('348000.00', $order->total_price); // 180 000 + 18 000 + 150 000

        $uri = "/pos/orders/{$order->uuid}/charges/room_fee/waive";
        // A waiter has no orders.discount
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1, 'reason' => "Do'stlar"])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1])->assertStatus(422)->assertJsonValidationErrors('reason');

        $token = $this->approval('orders.discount', $this->manager);
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1, 'reason' => "Do'stlar", 'approval_token' => $token])
            ->assertOk()
            ->assertJsonPath('data.room_fee_amount', '0.00')
            ->assertJsonPath('data.total_price', '198000.00')
            ->assertJsonPath('data.charges.waived', ['room_fee'])
            ->assertJsonPath('data.charges.waivers.0.approved_by', $this->manager->id);
        $log = AuditLog::where('action', 'pos.charge_waived')->firstOrFail();
        $this->assertSame('150000.00', $log->payload_json['amount']);
        $this->assertSame((int) $this->manager->id, $log->payload_json['approved_by']);

        // The manager waives the service charge with their own orders.discount
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/charges/service_charge/waive", ['version' => 2, 'reason' => "Mehmon e'tirozi"])
            ->assertOk()->assertJsonPath('data.service_charge_amount', '0.00')->assertJsonPath('data.total_price', '180000.00');

        // Restored: the room fee is back
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/charges/room_fee/restore", ['version' => 3])
            ->assertOk()->assertJsonPath('data.room_fee_amount', '150000.00')->assertJsonPath('data.total_price', '330000.00');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/charges/room_fee/restore", ['version' => 4])
            ->assertStatus(422)->assertJsonPath('data.reason', 'not_waived');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/charges/delivery/waive", ['version' => 4, 'reason' => 'x y'])
            ->assertNotFound();
    }

    public function test_a_telegram_order_never_gets_these_charges(): void
    {
        $this->tariff('weekday', '150000');
        $this->serviceFee('10');
        $online = $this->checkout($this->customer);

        $charges = app(PosChargesCalculator::class)->compute($online);
        $this->assertSame('0.00', $charges->serviceCharge->toString());
        $this->assertSame('0.00', $charges->roomFee->toString());
        $this->assertSame('0.00', $online->refresh()->service_charge_amount);
        $this->assertSame('0.00', $online->room_fee_amount);
    }

    public function test_takeaway_has_no_room_fee_and_the_hall_service_charge_only_by_its_channels(): void
    {
        $this->tariff('weekday', '150000');
        $this->serviceFee('10');
        $takeaway = $this->pos($this->cashier, 'POST', '/pos/orders', [
            'branch_id' => $this->branch->id, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, 10)],
        ])->assertCreated();

        $takeaway->assertJsonPath('data.room_fee_amount', '0.00')
            ->assertJsonPath('data.service_charge_amount', '0.00') // service charge channels default to pos_hall
            ->assertJsonPath('data.total_price', '180000.00');
    }
}
