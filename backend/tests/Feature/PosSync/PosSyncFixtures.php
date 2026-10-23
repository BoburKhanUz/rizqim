<?php

namespace Tests\Feature\PosSync;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\MenuFiscalLineBuilder;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\MenuBatch;
use App\Models\PosShift;
use App\Models\ProductVariant;
use App\Models\RoomTariff;
use App\Models\ServiceFeeSetting;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\MenuFixtures;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\Feature\StaffOrderFixtures;

/**
 * P3-31 helpers: a choyxona branch (a hall with a room and a table, a 10% service charge, a weekday room fee) on the
 * real POS and fiscal implementations (FiscalService with the sandbox driver), a paired till with its cashier signed in
 * by PIN and an open shift, and the offline operations a till would queue (each with a new op_id and the next seq).
 */
trait PosSyncFixtures
{
    use MenuFixtures;
    use StaffOrderFixtures;

    protected User $cashier;

    protected User $waiter;

    /** @var array{device: \App\Models\Device, token: string} */
    protected array $till;

    protected string $cashierToken;

    protected PosShift $shift;

    protected Hall $hall;

    protected DiningTable $room;

    protected ProductVariant $tea;

    protected ProductVariant $plov;

    protected ProductVariant $shashlik;

    protected MenuBatch $kazan;

    protected int $seq = 0;

    protected const MANAGER_PIN = '5926';

    protected function setUpPosSync(): void
    {
        // Thursday 22.10.2026, 18:00 in Tashkent: a weekday
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());

        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $this->givePin($this->manager, self::MANAGER_PIN);

        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => ['room_fee' => ['mxik' => '10202002001000001'], 'service_charge' => ['mxik' => '10202002005000001']],
            ],
        ])->save();

        $this->hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $this->room = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => '1-xona', 'kind' => 'room', 'seats' => 10]);
        RoomTariff::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => '300000']);
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_person']);

        $this->tea = $this->makeVariant($this->makeProduct('Choy', ['mxik_code' => '10202001005000001']), '5000', ['name' => 'Choynak', 'stock' => 1000]);
        $this->plov = $this->makeVariant($this->makeProduct('Osh', ['batch_tracked' => true, 'mxik_code' => '10202001001000001']), '120000', [
            'name' => 'Choyxona oshi', 'unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '0.5', 'stock' => 1000,
        ]);
        $this->shashlik = $this->makeVariant($this->makeProduct('Shashlik', ['mxik_code' => '10202001007000001']), '18000', ['name' => 'Six', 'stock' => 1000]);
        $this->kazan = MenuBatch::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $this->plov->product_id,
            'quantity_produced' => '10', 'quantity_left' => '10', 'started_at' => now(),
        ]);

        $this->till = $this->pairTerminal($this->owner, $this->branch);
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        // The shift was opened before the outage: an offline payment lands in it, never before it opened (review P3-35)
        Carbon::setTestNow(now()->subHours(2));
        $this->shift = app(ShiftService::class)->open($this->cashier, $this->till['device'], Money::of('100000'));
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));

        $this->assertInstanceOf(FiscalService::class, app(FiscalIssuer::class));
        $this->assertInstanceOf(MenuFiscalLineBuilder::class, app(FiscalLineBuilder::class));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * One queued operation: a new op_id, the next seq, done by $staff (the cashier) $minutesAgo minutes ago.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function op(string $type, array $payload, ?User $staff = null, int $minutesAgo = 10): array
    {
        return [
            'op_id' => (string) Str::uuid(),
            'seq' => ++$this->seq,
            'type' => $type,
            'occurred_at' => now()->subMinutes($minutesAgo)->toJSON(),
            'staff_id' => ($staff ?? $this->cashier)->id,
            'payload' => $payload,
        ];
    }

    /**
     * A line as the till prices it from its menu snapshot.
     *
     * @return array<string, mixed>
     */
    protected function line(ProductVariant $variant, string $quantity, ?string $unitPrice = null, ?string $lineId = null): array
    {
        return [
            'line_id' => $lineId ?? (string) Str::uuid(),
            'variant_id' => $variant->uuid,
            'quantity' => $quantity,
            'unit_price' => $unitPrice ?? (string) $variant->price,
        ];
    }

    /**
     * POST /pos/sync with the till's own token.
     *
     * @param  list<array<string, mixed>>  $operations
     * @param  array<string, mixed>  $extra
     */
    protected function sync(array $operations, array $extra = [], ?string $token = null): TestResponse
    {
        $this->withBearer($token ?? $this->till['token']);

        return $this->json('POST', '/api/v1/pos/sync', $extra + ['sent_at' => now()->toJSON(), 'operations' => $operations]);
    }

    /**
     * A request from the till's cashier PIN session (an Idempotency-Key on changes).
     *
     * @param  array<string, mixed>  $data
     */
    protected function tillAs(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->withBearer($token ?? $this->cashierToken);
        if ($method !== 'GET') {
            $this->withHeader('Idempotency-Key', (string) Str::uuid());
        }

        return $this->json($method, '/api/v1' . $uri, $data);
    }

    /**
     * A walk-in takeaway sold offline: open with lines and send, one bill, cash, close.
     *
     * @return array{order: string, bill: string, ops: list<array<string, mixed>>}
     */
    protected function walkInSale(string $localNumber = 'K1-001', string $cash = '41000'): array
    {
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();

        return ['order' => $order, 'bill' => $bill, 'ops' => [
            $this->op('order.open', [
                'order' => $order,
                'local_number' => $localNumber,
                'channel' => 'pos_takeaway',
                'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')],
                'send' => true,
                'menu_taken_at' => now()->subHour()->toJSON(),
            ], null, 30),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '41000'], null, 20),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [
                ['tender' => 'cash', 'amount' => $cash, 'tendered_amount' => '50000'],
            ]], null, 19),
            $this->op('order.close', ['order' => $order], null, 19),
        ]];
    }
}
