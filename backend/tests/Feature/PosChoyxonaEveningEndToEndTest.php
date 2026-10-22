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
 * A choyxona Thursday evening end to end on the real implementations of every POS contract (FiscalService with the
 * sandbox driver, MenuFiscalLineBuilder, KitchenStationRouter, PosOrderService as the opener, BookingAdvanceLedger,
 * CustomerAccountService, ManagerApprovalService with a manager's PIN on the till); only Telegram is recorded instead
 * of sent. Booking with a pre-order and a cash deposit (ADVANCE) → "Keldi" → two rounds (a kg dish, the choy-non
 * lines) → send → room fee (weekday) and the hall's service charge → split in two → bill 1 by the deposit and a card,
 * bill 2 part cash part nasiya (manager's approval) → SALE receipts with the prepayment and credit parts → the order
 * closed, the room free, the booking completed → the nasiya repaid (CREDIT receipt) → a second booking's cash deposit
 * refunded from the drawer → X → Z. And the same evening in a store whose fiscal mode is off and whose menu has no
 * MXIK codes: every receipt non_fiscal, nothing refused.
 */
class PosChoyxonaEveningEndToEndTest extends TestCase
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

    public function test_a_thursday_evening_in_fiscal_mode(): void
    {
        $this->fiscalStore();
        $this->evening(fiscal: true);
    }

    public function test_the_same_evening_with_fiscal_mode_off_and_no_mxik_codes(): void
    {
        $this->nonFiscalStore();
        $this->evening(fiscal: false);
    }

    public function test_a_deposit_refunded_from_the_panel_or_by_card_leaves_the_drawer_alone(): void
    {
        $this->fiscalStore();
        $shift = (string) $this->till('POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated()->json('data.uuid');
        $booking = fn (string $at) => $this->till('POST', '/pos/bookings', [
            'dining_table_id' => $this->vipRoom->id, 'starts_at' => $at, 'contact_name' => 'Botir', 'confirm' => true,
        ])->assertCreated()->json('data.uuid');

        // Cash, refunded by the owner from the web panel: no drawer
        $first = $booking('2026-10-24T19:00:00+05:00');
        $cash = $this->till('POST', "/pos/bookings/{$first}/deposits", ['amount' => '50000', 'tender' => 'cash'])->assertCreated()->json('data.payment.uuid');
        $this->till('POST', "/pos/bookings/{$first}/cancel", ['reason' => 'Bekor'])->assertOk();
        $this->asOwner('POST', "/pos/bookings/{$first}/deposits/{$cash}/refund", ['reason' => 'Qaytarildi'])->assertOk();
        $this->assertNull(\App\Models\BookingPayment::where('uuid', $cash)->value('refunded_shift_id'));

        // By card, refunded on the till: the shift's report shows it, the drawer does not pay it
        $second = $booking('2026-10-25T19:00:00+05:00');
        $card = $this->till('POST', "/pos/bookings/{$second}/deposits", ['amount' => '70000', 'tender' => 'card', 'card_type' => 'humo', 'transaction_id' => 'T-1'])->assertCreated()->json('data.payment.uuid');
        $this->till('POST', "/pos/bookings/{$second}/cancel", ['reason' => 'Bekor'])->assertOk();
        $this->till('POST', "/pos/bookings/{$second}/deposits/{$card}/refund", ['reason' => 'Qaytarildi', 'approval_token' => $this->managerApproves('pos.refund')])->assertOk();

        $report = $this->till('GET', "/pos/shifts/{$shift}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 1, 'amount' => '70000.00', 'cash' => '0.00'], $report['deposits']['refunded']);
        // 100 000 opening + 50 000 cash deposit; the panel's refund came from elsewhere
        $this->assertSame('150000.00', $report['cash']['expected']);
    }

    private function evening(bool $fiscal): void
    {
        // 18:00: the till opens its shift with 500 000 in the drawer
        $shift = (string) $this->till('POST', '/pos/shifts', ['opening_cash' => '500000'])->assertCreated()->json('data.uuid');

        // A booking for 19:00 in room 1, 4 guests, 2 kg of plov pre-ordered for 19:30, 300 000 deposit in cash
        $booking = $this->till('POST', '/pos/bookings', [
            'dining_table_id' => $this->room->id, 'starts_at' => '2026-10-22T19:00:00+05:00', 'guests_planned' => 4,
            'contact_name' => 'Jamshid aka', 'contact_phone' => '+998901112233', 'confirm' => true,
        ])->assertCreated()->json('data.uuid');
        $this->till('POST', "/pos/bookings/{$booking}/items", ['product_variant_id' => $this->plov->id, 'quantity' => '2', 'due_at' => '2026-10-22T19:30:00+05:00'])->assertCreated();
        $deposit = $this->till('POST', "/pos/bookings/{$booking}/deposits", ['amount' => '300000', 'tender' => 'cash'])->assertCreated()->json('data.payment');
        $advance = FiscalReceipt::where('uuid', $deposit['fiscal_receipt_uuid'])->firstOrFail();
        $this->assertSame(FiscalReceiptType::ADVANCE, $advance->type);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $advance->status);
        $this->assertSame('300000.00', $advance->total);
        $this->assertCount(1, $advance->lines);

        // 19:05 "Keldi": the order opens in the room with the pre-order on hold and the choy-non lines (round 1)
        Carbon::setTestNow(now()->addMinutes(65));
        $orderUuid = $this->till('POST', "/pos/bookings/{$booking}/arrive", ['guests' => 4])->assertOk()->json('data.order.uuid');
        $order = $this->till('GET', "/pos/orders/{$orderUuid}")->assertOk()->json('data');
        $this->assertSame('1-xona', $order['table']['name']);
        $this->assertSame(4, $order['guests_count']);
        $this->assertSame('300000.00', $order['room_fee_amount']);
        $this->assertSame('weekday', $order['charges']['day_type']);
        $lines = collect($order['items'])->keyBy('product_variant_id');
        $this->assertSame('hold', $lines[$this->plov->id]['fire_mode']);
        $this->assertSame('2 kg', $lines[$this->plov->id]['quantity_label']);
        $this->assertSame([1], collect($order['items'])->pluck('round_no')->unique()->values()->all());
        $this->assertSame(['2', '4'], [(string) $lines[$this->tea->id]['quantity'], (string) $lines[$this->bread->id]['quantity']]);
        $this->assertSame('8.000', $this->kazan->refresh()->quantity_left);

        // Round 2: shashlik and more tea, sent at once; then the rest and the plov by name
        $order = $this->till('POST', "/pos/orders/{$orderUuid}/rounds", ['version' => $order['version'], 'send' => true, 'items' => [
            ['variant_id' => $this->shashlik->uuid, 'quantity' => 10], ['variant_id' => $this->tea->uuid, 'quantity' => 2],
        ]])->assertOk()->json('data');
        $this->assertSame(2, $order['rounds']);
        $order = $this->till('POST', "/pos/orders/{$orderUuid}/send", ['version' => $order['version']])->assertOk()->json('data');
        $plovLine = collect($order['items'])->firstWhere('product_variant_id', $this->plov->id);
        $order = $this->till('POST', "/pos/orders/{$orderUuid}/send", ['version' => $order['version'], 'item_ids' => [$plovLine['id']]])->assertOk()->json('data');
        $this->assertSame('preparing', $order['status']);
        $this->assertSame([], collect($order['items'])->whereNull('sent_at')->all());
        $this->assertSame($this->oshStation->id, collect($order['items'])->firstWhere('product_variant_id', $this->plov->id)['station_id']);

        // Food 240 000 + 10 000 + 16 000 + 180 000 + 10 000 = 456 000; room fee 300 000; service charge 10% 45 600
        $this->assertSame('456000.00', $order['subtotal']);
        $this->assertSame('45600.00', $order['service_charge_amount']);
        $this->assertSame('801600.00', $order['total_price']);

        // The open orders queue shows it
        $this->assertSame([$orderUuid], array_column($this->till('GET', '/pos/orders')->assertOk()->json('data'), 'uuid'));

        // The bill split equally in two
        $bills = $this->till('POST', "/pos/orders/{$orderUuid}/bills", ['version' => $order['version'], 'mode' => 'equal', 'parts' => 2])->assertCreated()->json('data.bills');
        $this->assertSame(['400800.00', '400800.00'], array_column($bills, 'total'));

        // Bill 1: the deposit and a card
        $this->till('POST', "/pos/bills/{$bills[0]['uuid']}/payments", ['tenders' => [
            ['tender' => 'prepayment', 'amount' => '300000'],
            ['tender' => 'card', 'amount' => '100800', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-771'],
        ]])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        // Bill 2: 150 800 cash, 250 000 on nasiya with the manager's approval
        $account = $this->asOwner('POST', '/pos/customer-accounts', ['name' => 'Jamshid aka', 'phone' => '+998901112233', 'credit_limit' => '1000000'])->assertCreated()->json('data.uuid');
        $tenders = ['tenders' => [
            ['tender' => 'cash', 'amount' => '150800', 'tendered_amount' => '200000'],
            ['tender' => 'account', 'amount' => '250000', 'account_uuid' => $account],
        ]];
        $this->till('POST', "/pos/bills/{$bills[1]['uuid']}/payments", $tenders)->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->till('POST', "/pos/bills/{$bills[1]['uuid']}/payments", $tenders + ['approval_token' => $this->managerApproves('customer_accounts.manage')])
            ->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.order.status', 'delivered');

        // Two SALE receipts for the full amounts: the deposit part prepaid, the nasiya part on credit
        $sales = FiscalReceipt::where('type', 'sale')->orderBy('id')->get();
        $this->assertCount(2, $sales);
        $this->assertSame(['400800.00', '400800.00'], $sales->pluck('total')->all());
        $this->assertSame('801600.00', $this->sum($sales->pluck('total')));
        foreach ($sales as $sale) {
            $this->assertSame(FiscalReceiptStatus::ISSUED, $sale->status);
            $this->assertSame($sale->total, $this->sum(array_column($sale->lines, 'amount')));
            $this->assertSame($sale->total, $this->sum(array_column($sale->payments, 'amount')));
            $this->assertSame($sale->vat_total, $this->sum(array_column($sale->lines, 'vat_amount')));
            $this->assertSame($fiscal ? 'sandbox' : 'null', $sale->driver);
            $this->assertSame($fiscal, $sale->fiscal_sign !== null);
        }
        $this->assertSame([['prepayment', true, false, '300000.00'], ['card', false, false, '100800.00']], array_map(
            fn (array $payment) => [$payment['form'] === 'card' ? 'card' : 'prepayment', $payment['prepayment'], $payment['credit'], $payment['amount']],
            $sales[0]->payments,
        ));
        $this->assertSame('cash', $sales[0]->payments[0]['form']); // the deposit was taken in cash
        $this->assertSame('card', $sales[0]->payment_form->value);
        $this->assertSame([[false, false, '150800.00'], [false, true, '250000.00']], array_map(
            fn (array $payment) => [$payment['prepayment'], $payment['credit'], $payment['amount']],
            $sales[1]->payments,
        ));
        $this->assertSame('cash', $sales[1]->payment_form->value);
        // Every line of the evening is on the receipts, the room fee and service charge as their own lines
        $names = collect([...$sales[0]->lines, ...$sales[1]->lines])->pluck('name')->unique()->values()->all();
        $this->assertContains('Xona puli', $names);
        $this->assertContains('Xizmat haqi', $names);
        $this->assertSame(
            $fiscal ? ['10202002001000001', '10202002005000001'] : [''],
            collect($sales[0]->lines)->whereIn('name', ['Xona puli', 'Xizmat haqi'])->pluck('mxik_code')->unique()->values()->all(),
        );
        $kg = collect($sales[0]->lines)->first(fn (array $line) => str_starts_with($line['name'], 'Osh'));
        $this->assertSame('kg (guruch)', $kg['unit']);
        $this->assertSame('1.000', $kg['quantity']);

        // The order is closed, the room free, the booking completed with its deposit applied
        $closed = Order::where('uuid', $orderUuid)->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame([], $this->till('GET', '/pos/orders')->assertOk()->json('data'));
        $this->assertFalse(Order::where('table_id', $this->room->id)->whereIn('status', ['accepted', 'preparing'])->exists());
        $this->assertSame('completed', Booking::where('uuid', $booking)->value('status')->value);
        $this->assertSame('applied', \App\Models\BookingPayment::where('uuid', $deposit['uuid'])->value('status')->value);

        // A week later in the same shift (for the test): the nasiya is repaid in cash, a CREDIT receipt
        $repayment = $this->till('POST', "/pos/customer-accounts/{$account}/repayments", ['amount' => '250000', 'tender' => 'cash'])
            ->assertCreated()->assertJsonPath('data.account.balance', '0.00')->json('data.entry');
        $credit = FiscalReceipt::where('uuid', $repayment['fiscal_receipt_uuid'])->firstOrFail();
        $this->assertSame(FiscalReceiptType::CREDIT, $credit->type);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $credit->status);
        $this->assertSame('250000.00', $credit->total);
        $this->assertNull($credit->fiscal_sign);

        // Another party cancels its booking: its cash deposit goes back out of this drawer (pos.refund: the manager)
        $other = $this->till('POST', '/pos/bookings', [
            'dining_table_id' => $this->vipRoom->id, 'starts_at' => '2026-10-23T19:00:00+05:00', 'guests_planned' => 10, 'contact_name' => 'Botir', 'confirm' => true,
        ])->assertCreated()->json('data.uuid');
        $otherDeposit = $this->till('POST', "/pos/bookings/{$other}/deposits", ['amount' => '200000', 'tender' => 'cash'])->assertCreated()->json('data.payment.uuid');
        $this->till('POST', "/pos/bookings/{$other}/cancel", ['reason' => 'Rejalar o\'zgardi'])->assertOk();
        $this->till('POST', "/pos/bookings/{$other}/deposits/{$otherDeposit}/refund", ['reason' => 'Mijoz so\'radi', 'approval_token' => $this->managerApproves('pos.refund')])
            ->assertOk()->assertJsonPath('data.payment.status', 'refunded');

        $refunded = \App\Models\BookingPayment::where('uuid', $otherDeposit)->firstOrFail();
        $this->assertSame(\App\Models\PosShift::where('uuid', $shift)->value('id'), $refunded->refunded_shift_id);
        $this->assertNotNull($refunded->refunded_device_id);

        // X: money by tender, deposits and nasiya apart, the cash the drawer should hold
        $report = $this->till('GET', "/pos/shifts/{$shift}/x-report")->assertOk()->json('data.report');
        $byTender = collect($report['by_tender'])->keyBy('tender');
        $this->assertSame('150800.00', $byTender['cash']['amount']);
        $this->assertSame('100800.00', $byTender['card']['amount']);
        $this->assertSame('300000.00', $byTender['prepayment']['amount']);
        $this->assertSame('250000.00', $byTender['account']['amount']);
        $this->assertSame('801600.00', $report['sales']['received']);
        $this->assertSame('300000.00', $report['sales']['room_fee']);
        $this->assertSame('45600.00', $report['sales']['service_charge']);
        $this->assertSame(['count' => 2, 'amount' => '500000.00', 'cash' => '500000.00'], $report['deposits']['taken']);
        $this->assertSame('300000.00', $report['deposits']['applied']['amount']);
        $this->assertSame(['count' => 1, 'amount' => '200000.00', 'cash' => '200000.00'], $report['deposits']['refunded']);
        $this->assertSame('250000.00', $report['credit']['sales']['amount']);
        $this->assertSame(['count' => 1, 'amount' => '250000.00', 'cash' => '250000.00'], $report['credit']['repayments']);
        // 500 000 opening + 150 800 cash sale + 500 000 deposits + 250 000 repayment − 200 000 deposit refund
        $this->assertSame('200000.00', $report['cash']['deposit_refunds']);
        $this->assertSame('1200800.00', $report['cash']['expected']);

        // Z: 1 200 800 counted, no difference
        $z = $this->till('POST', "/pos/shifts/{$shift}/close", ['counted_breakdown' => ['200000' => 6, '500' => 1, '100' => 3]])
            ->assertOk()->json('data');
        $this->assertSame('closed', $z['status']);
        $this->assertSame('1200800.00', $z['expected_cash']);
        $this->assertSame('1200800.00', $z['counted_cash']);
        $this->assertSame('0.00', $z['difference']);
        $this->assertSame(1, $z['z_number']);

        // Every receipt of the evening, as the store's mode says
        $receipts = FiscalReceipt::orderBy('id')->get();
        $this->assertSame(['advance' => 2, 'credit' => 1, 'sale' => 2], $receipts->countBy(fn (FiscalReceipt $receipt) => $receipt->type->value)->sortKeys()->all());
        $this->assertTrue($receipts->every(fn (FiscalReceipt $receipt) => $receipt->status === FiscalReceiptStatus::ISSUED));
        $this->assertTrue($receipts->every(fn (FiscalReceipt $receipt) => $receipt->driver === ($fiscal ? 'sandbox' : 'null') && $receipt->non_fiscal));
    }
}
