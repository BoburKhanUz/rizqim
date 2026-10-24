<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\MenuFiscalLineBuilder;
use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Domain\Pos\Booking\BookingAdvanceLedger;
use App\Domain\Pos\Accounts\CustomerAccountService;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Domain\Pos\Orders\PosOrderService;
use App\Domain\Pos\Setup\KitchenStationRouter;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AutoGuestItem;
use App\Models\Booking;
use App\Models\DiningTable;
use App\Models\FiscalReceipt;
use App\Models\Hall;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\MenuBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\RoomTariff;
use App\Models\ServiceFeeSetting;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * Review P3-35 (review A #2, #8) on the real implementations of every POS contract: a booking's order merged into
 * another hands its booking and deposit over (the target's bill takes the deposit, the booking completes when the
 * target ends; two different bookings never merge), and the deposit and nasiya parts of a paid bill are refunded back
 * to the deposit and off the debt, so the order can be cancelled and the deposit refunded.
 */
class PosMergeBookingAndReversalsTest extends TestCase
{
    use MenuFixtures;
    use RefreshDatabase;
    use StaffOrderFixtures;

    private const MANAGER_PIN = '5926';

    private User $cashier;

    private string $cashierToken;

    private Hall $hall;

    private DiningTable $room;

    private DiningTable $vipRoom;

    private ProductVariant $tea;

    private ProductVariant $bread;

    private ProductVariant $plov;

    private ProductVariant $shashlik;

    private MenuBatch $kazan;

    private KitchenStation $oshStation;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 22.10.2026, 18:00 in Tashkent: a weekday
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());

        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->givePin($this->manager, self::MANAGER_PIN);

        $this->hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $this->room = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => '1-xona', 'kind' => 'room', 'seats' => 10]);
        $this->vipRoom = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => 'VIP', 'kind' => 'room', 'seats' => 20]);
        RoomTariff::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => '300000']);
        RoomTariff::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'weekend', 'price' => '400000']);
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_person']);

        $this->tea = $this->makeVariant($this->makeProduct('Choy', ['mxik_code' => '10202001005000001']), '5000', ['name' => 'Choynak', 'stock' => 1000]);
        $this->bread = $this->makeVariant($this->makeProduct('Non', ['mxik_code' => '10202001006000001']), '4000', ['name' => 'Dona', 'stock' => 1000]);
        $this->plov = $this->makeVariant($this->makeProduct('Osh', ['batch_tracked' => true, 'mxik_code' => '10202001001000001']), '120000', [
            'name' => 'Choyxona oshi', 'unit' => 'kg', 'unit_label' => 'kg (guruch)', 'quantity_step' => '0.5', 'min_quantity' => '0.5', 'stock' => 1000,
        ]);
        $this->shashlik = $this->makeVariant($this->makeProduct('Shashlik', ['mxik_code' => '10202001007000001']), '18000', ['name' => 'Six', 'stock' => 1000]);
        foreach ([[$this->tea, '0.5'], [$this->bread, '1']] as [$variant, $perGuest]) {
            AutoGuestItem::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_variant_id' => $variant->id, 'quantity_per_guest' => $perGuest]);
        }
        $this->kazan = MenuBatch::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $this->plov->product_id,
            'quantity_produced' => '10', 'quantity_left' => '10', 'started_at' => now(),
        ]);
        $this->oshStation = KitchenStation::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Osh', 'kind' => 'kitchen']);
        KitchenStationRoute::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'station_id' => $this->oshStation->id, 'product_id' => $this->plov->product_id]);

        $till = $this->pairTerminal($this->owner, $this->branch);
        $this->cashierToken = $this->pinSession($till, $this->cashier);

        // No fakes: every contract is its real implementation
        foreach ([
            FiscalIssuer::class => FiscalService::class,
            FiscalLineBuilder::class => MenuFiscalLineBuilder::class,
            KitchenStationResolver::class => KitchenStationRouter::class,
            PosOrderOpener::class => PosOrderService::class,
            AdvanceLedger::class => BookingAdvanceLedger::class,
            CustomerAccountLedger::class => CustomerAccountService::class,
            ManagerApprovals::class => ManagerApprovalService::class,
        ] as $contract => $implementation) {
            $this->assertInstanceOf($implementation, app($contract), $contract);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fiscalStore(): void
    {
        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => ['room_fee' => ['mxik' => '10202002001000001'], 'service_charge' => ['mxik' => '10202002005000001']],
            ],
        ])->save();
    }

    private function nonFiscalStore(): void
    {
        $this->store->forceFill(['tin' => null, 'tax_regime' => null, 'fiscal_settings' => ['enabled' => false]])->save();
        \App\Models\Product::where('store_id', $this->store->id)->update(['mxik_code' => null]);
        // Not even the deposit and credit lines have a code
        config(['fiscal.charges.deposit.mxik' => null, 'fiscal.charges.credit.mxik' => null, 'fiscal.charges.booking_fee.mxik' => null]);
    }

    /**
     * A request from the till: the cashier's PIN session, an Idempotency-Key on every change.
     *
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
     * The manager types their PIN on the till: a one-time approval for $permission.
     */
    private function managerApproves(string $permission): string
    {
        return (string) $this->till('POST', '/pos/approvals', ['permission' => $permission, 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertCreated()->json('data.token');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function asOwner(string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer(null);

        return $this->actingAs($this->owner, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())->json($method, '/api/v1' . $uri, $data);
    }

    private function sum(iterable $amounts): string
    {
        $sum = Money::zero();
        foreach ($amounts as $amount) {
            $sum = $sum->plus((string) $amount);
        }

        return $sum->toString();
    }

    public function test_a_merged_booking_follows_its_guests_and_its_deposit_parts_are_refunded(): void
    {
        $this->fiscalStore();
        $this->till('POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated();
        $booking = $this->till('POST', '/pos/bookings', [
            'dining_table_id' => $this->room->id, 'starts_at' => '2026-10-22T19:00:00+05:00', 'guests_planned' => 2,
            'contact_name' => 'Jamshid', 'confirm' => true,
        ])->assertCreated()->json('data.uuid');
        $deposit = $this->till('POST', "/pos/bookings/{$booking}/deposits", ['amount' => '300000', 'tender' => 'cash'])->assertCreated()->json('data.payment.uuid');
        Carbon::setTestNow(now()->addMinutes(65));
        $source = $this->till('POST', "/pos/bookings/{$booking}/arrive", ['guests' => 2])->assertOk()->json('data.order.uuid');

        // Another party in the VIP room; the booked guests join it
        $target = $this->till('POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->vipRoom->id, 'guests_count' => 2])->assertCreated()->json('data');
        $src = $this->till('GET', "/pos/orders/{$source}")->json('data');
        $this->till('POST', "/pos/orders/{$target['uuid']}/merge", ['version' => $target['version'], 'source_uuid' => $source, 'source_version' => $src['version']])->assertOk();

        $targetOrder = Order::where('uuid', $target['uuid'])->firstOrFail();
        $bookingRow = Booking::where('uuid', $booking)->firstOrFail();
        $this->assertSame((int) $bookingRow->id, (int) $targetOrder->booking_id);
        $this->assertNull(Order::where('uuid', $source)->value('booking_id'));
        $this->assertSame('arrived', $bookingRow->status->value);
        $this->assertSame((int) $targetOrder->id, (int) $bookingRow->order_id);
        $this->assertSame((int) $targetOrder->id, (int) \App\Models\BookingPayment::where('uuid', $deposit)->value('order_id'));
        $this->till('GET', "/pos/orders/{$target['uuid']}")->assertJsonPath('data.deposit_available', '300000.00');

        // One bill: 100 000 from the deposit, 50 000 on nasiya, the rest in cash
        $order = $this->till('GET', "/pos/orders/{$target['uuid']}")->json('data');
        $bill = $this->till('POST', "/pos/orders/{$target['uuid']}/bills", ['version' => $order['version'], 'mode' => 'single'])->assertCreated()->json('data.bills.0');
        $account = $this->asOwner('POST', '/pos/customer-accounts', ['name' => 'Jamshid aka', 'credit_limit' => '1000000'])->assertCreated()->json('data.uuid');
        $cash = Money::of($bill['total'])->minus('150000')->toString();
        $this->till('POST', "/pos/bills/{$bill['uuid']}/payments", ['tenders' => [
            ['tender' => 'prepayment', 'amount' => '100000'],
            ['tender' => 'account', 'amount' => '50000', 'account_uuid' => $account],
            ['tender' => 'cash', 'amount' => $cash],
        ], 'approval_token' => $this->managerApproves('customer_accounts.manage')])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame('200000.00', \App\Models\BookingPayment::where('uuid', $deposit)->firstOrFail()->remaining()->toString());
        $this->assertSame('50000.00', (string) \App\Models\CustomerAccount::where('uuid', $account)->value('balance'));

        // The target closed: its booking completes (the source's quiet cancellation completed nothing)
        $this->assertSame('delivered', $targetOrder->refresh()->status->value);
        $this->assertSame('completed', $bookingRow->refresh()->status->value);
    }

    public function test_the_deposit_and_nasiya_parts_go_back_and_the_booking_s_deposit_is_refunded_after_cancelling(): void
    {
        $this->fiscalStore();
        $this->till('POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated();
        $booking = $this->till('POST', '/pos/bookings', [
            'dining_table_id' => $this->room->id, 'starts_at' => '2026-10-22T19:00:00+05:00', 'guests_planned' => 2,
            'contact_name' => 'Jamshid', 'confirm' => true,
        ])->assertCreated()->json('data.uuid');
        $deposit = $this->till('POST', "/pos/bookings/{$booking}/deposits", ['amount' => '300000', 'tender' => 'cash'])->assertCreated()->json('data.payment.uuid');
        Carbon::setTestNow(now()->addMinutes(65));
        $uuid = $this->till('POST', "/pos/bookings/{$booking}/arrive", ['guests' => 2])->assertOk()->json('data.order.uuid');

        // Two bills; the first is paid from the deposit and on nasiya
        $order = $this->till('GET', "/pos/orders/{$uuid}")->json('data');
        $bills = $this->till('POST', "/pos/orders/{$uuid}/bills", ['version' => $order['version'], 'mode' => 'equal', 'parts' => 2])->assertCreated()->json('data.bills');
        $account = $this->asOwner('POST', '/pos/customer-accounts', ['name' => 'Jamshid aka', 'credit_limit' => '1000000'])->assertCreated()->json('data.uuid');
        $first = Money::of($bills[0]['total']);
        $this->till('POST', "/pos/bills/{$bills[0]['uuid']}/payments", ['tenders' => [
            ['tender' => 'prepayment', 'amount' => '100000'],
            ['tender' => 'account', 'amount' => $first->minus('100000')->toString(), 'account_uuid' => $account],
        ], 'approval_token' => $this->managerApproves('customer_accounts.manage')])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        // The party leaves without the second bill: refund the parts (no cash leaves), then cancel
        $drawer = \App\Models\CashMovement::count();
        foreach (\App\Models\Payment::where('bill_id', \App\Models\OrderBill::where('uuid', $bills[0]['uuid'])->value('id'))->orderBy('id')->get() as $payment) {
            $this->till('POST', '/pos/refunds', ['payment_uuid' => $payment->uuid, 'amount' => $payment->amount, 'reason' => 'Mehmonlar ketdi', 'approval_token' => $this->managerApproves('pos.refund')])
                ->assertCreated()->assertJsonPath('data.fiscal.status', 'issued');
        }
        $this->assertSame($drawer, \App\Models\CashMovement::count());
        $this->assertSame('300000.00', \App\Models\BookingPayment::where('uuid', $deposit)->firstOrFail()->remaining()->toString());
        $this->assertSame('taken', \App\Models\BookingPayment::where('uuid', $deposit)->firstOrFail()->status->value);
        $this->assertSame('0.00', (string) \App\Models\CustomerAccount::where('uuid', $account)->value('balance'));
        $this->assertSame(2, FiscalReceipt::where('type', FiscalReceiptType::REFUND->value)->where('status', FiscalReceiptStatus::ISSUED->value)->count());

        $version = (int) Order::where('uuid', $uuid)->value('version');
        $this->till('POST', "/pos/orders/{$uuid}/cancel", ['version' => $version, 'reason' => 'Mehmonlar ketdi', 'approval_token' => $this->managerApproves('orders.cancel')])->assertOk();
        $this->assertSame('completed', Booking::where('uuid', $booking)->firstOrFail()->status->value);
        $this->till('POST', "/pos/bookings/{$booking}/deposits/{$deposit}/refund", ['reason' => 'Qaytarildi', 'approval_token' => $this->managerApproves('pos.refund')])
            ->assertOk()->assertJsonPath('data.payment.status', 'refunded');
    }

    public function test_orders_of_two_different_bookings_never_merge(): void
    {
        $this->till('POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated();
        $orders = [];
        foreach ([$this->room, $this->vipRoom] as $i => $room) {
            $booking = $this->till('POST', '/pos/bookings', [
                'dining_table_id' => $room->id, 'starts_at' => '2026-10-22T19:00:00+05:00', 'guests_planned' => 2,
                'contact_name' => "Mehmon {$i}", 'confirm' => true,
            ])->assertCreated()->json('data.uuid');
            $orders[] = ['booking' => $booking];
        }
        Carbon::setTestNow(now()->addMinutes(65));
        foreach ($orders as $i => $row) {
            $orders[$i]['order'] = $this->till('GET', '/pos/orders/' . $this->till('POST', "/pos/bookings/{$row['booking']}/arrive", ['guests' => 2])->assertOk()->json('data.order.uuid'))->json('data');
        }

        $this->till('POST', "/pos/orders/{$orders[1]['order']['uuid']}/merge", [
            'version' => $orders[1]['order']['version'], 'source_uuid' => $orders[0]['order']['uuid'], 'source_version' => $orders[0]['order']['version'],
        ])->assertStatus(422)->assertJsonPath('data.reason', 'merge_bookings_conflict');
        $this->assertSame('accepted', Order::where('uuid', $orders[0]['order']['uuid'])->firstOrFail()->status->value);
    }
}
