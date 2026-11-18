<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Pos\Setup\PosModule;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use App\Models\BanquetEvent;
use App\Models\BanquetExtra;
use App\Models\BanquetHallRate;
use App\Models\BanquetPackage;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountMember;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\FiscalReceipt;
use App\Models\Hall;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosQrSetting;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\PosWaitlistEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceFeeSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Auth\DevicePairingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Feature\AccessFixtures;

/**
 * M4 stage 1 (docs/scale/M4_DESIGN.md 3.1): two complete stores, A and B, built the same way from the existing fixtures
 * and, where a record only exists at the end of a flow (a paid bill, a refund, a deposit), through the store's own API
 * as its own staff. Every name and label of B starts with `ZZB-TENANT-` so a response that shows B's data is found by text.
 *
 * $this->world['A'|'B'] maps a key (store, branch, owner, order, bill, ...) to the model or value the manifest
 * (routes.php) names; tokens: owner_token, manager_token, cashier_token (PIN session on the till), till_token,
 * kds_token, display_token.
 */
trait TenancyWorld
{
    use AccessFixtures;

    /** The clock of the whole suite: Wednesday 10.06.2026 12:00 in Tashkent (business day since 05:00). */
    public const NOW = '2026-06-10 07:00:00';

    /**
     * B's marker: 11 characters with two hyphens. A random token (base64url, hex) contains it with a chance of about
     * (1/32)^9 * (1/64)^2 per position (letters matched in any case), far below one in a run.
     */
    public const MARK = 'ZZB-TENANT-';

    /** @var array{A: array<string, mixed>, B: array<string, mixed>} */
    protected array $world = ['A' => [], 'B' => []];

    protected Category $sharedCategory;

    protected function buildTenancyWorld(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW, 'UTC'));
        $this->sharedCategory = Category::create(['name' => 'Taomlar', 'slug' => 'taomlar-' . Str::random(5), 'is_active' => true]);
        $this->customer = $this->makeCustomer(0, ['name' => 'Mijoz']);
        $region = (int) DB::table('regions')->insertGetId(['name_uz' => 'Toshkent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $district = (int) DB::table('districts')->insertGetId(['region_id' => $region, 'name_uz' => 'Chilonzor', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->world['A'] += ['region' => $region, 'district' => $district];
        $this->world['B'] += ['region' => $region, 'district' => $district];
        config(['services.telegram.bot_token' => 'tenancy-test-bot']);
        foreach (['A' => 'A-', 'B' => self::MARK] as $side => $prefix) {
            $this->buildSide($side, $prefix);
        }
        foreach (DB::select('SELECT last_value FROM pg_sequences WHERE schemaname = current_schema() AND last_value BETWEEN ? AND ?', self::B_IDS) as $row) {
            $this->bLastId = max($this->bLastId, (int) $row->last_value);
        }
        $this->moveSequences(self::AFTER_IDS);
    }

    /**
     * @return array<string, mixed>
     */
    protected function side(string $side): array
    {
        return $this->world[$side];
    }

    /** The side being built: B's amounts are A's plus 317, so no B amount (and no sum with one) is an A amount. */
    private string $building = 'A';

    private function n(string $amount, bool $whole = false): string
    {
        return $this->building === 'A' ? $amount : bcadd($amount, $whole ? '317' : '317.17', 2);
    }

    private function buildSide(string $side, string $p): void
    {
        $this->building = $side;
        $this->moveSequences($side === 'A' ? self::A_IDS : self::B_IDS[0]);
        $w = &$this->world[$side];
        $this->store = $store = $this->makeStore($p . 'Store');
        $store->forceFill(['timezone' => 'Asia/Tashkent'])->save();
        $this->branch = $branch = $this->makeBranch($store, $p . 'Branch');
        $modules = [];
        foreach (PosModule::cases() as $module) {
            $modules[$module->value] = true;
        }
        Branch::whereKey($branch->id)->update(['pos_settings' => json_encode(['profile' => 'choyxona', 'modules' => $modules])]);
        $branch->refresh();
        app()->forgetScopedInstances();

        $staff = fn (UserRole $role, ?Branch $at) => $this->makeStaff($role, $at, $store, ['name' => $p . ucfirst($role->value)]);
        $this->owner = $w['owner'] = $staff(UserRole::OWNER, null);
        $this->manager = $w['manager'] = $staff(UserRole::MANAGER, $branch);
        $w['cashier'] = $staff(UserRole::CASHIER, $branch);
        $w['waiter'] = $staff(UserRole::WAITER, $branch);
        $w['cook'] = $staff(UserRole::COOK, $branch);
        $w['courier'] = $staff(UserRole::COURIER, $branch);
        $w['hostess'] = $staff(UserRole::HOSTESS, $branch);
        $w['store'] = $store;
        $w['branch'] = $branch;

        // Terminals: the till (POS), a KDS and a customer display following the till
        $pairing = app(DevicePairingService::class);
        $w['till'] = $this->pairTerminal($w['owner'], $branch, DeviceType::POS);
        $w['kds'] = $this->pairTerminal($w['owner'], $branch, DeviceType::KDS);
        $code = $pairing->createCode($store, $branch, DeviceType::DISPLAY, $p . 'Display', $w['owner'], $w['till']['device'])['code'];
        $w['display'] = $pairing->pair($code, ['platform' => 'android']);
        $w['till_token'] = $w['till']['token'];
        $w['kds_token'] = $w['kds']['token'];
        $w['display_token'] = $w['display']['token'];

        $this->givePin($w['manager'], '5926');
        $w['cashier_token'] = $this->startPinSession($w['till'], $w['cashier'], '4831');
        $w['owner_token'] = $w['owner']->createToken('tenancy')->plainTextToken;
        $w['manager_token'] = $w['manager']->createToken('tenancy')->plainTextToken;
        foreach (['waiter', 'cook', 'courier', 'hostess'] as $role) {
            $w[$role . '_token'] = $w[$role]->createToken('tenancy')->plainTextToken;
        }

        $this->buildMenu($w, $p);
        $this->buildHall($w, $p);
        $this->buildTill($w, $p);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function buildMenu(array &$w, string $p): void
    {
        $store = $w['store'];
        $store->forceFill([
            'tin' => $p === 'A-' ? '301234567' : '302345678',
            'tax_regime' => 'vat',
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'charges' => [
                'service_charge' => ['mxik' => '10202002005000001'], 'room_fee' => ['mxik' => '10202002001000001'],
                'deposit' => ['mxik' => '10305001001000000'], 'booking_fee' => ['mxik' => '10305001002000000'],
            ]],
        ])->save();
        $product = fn (string $name, array $extra = []) => Product::create($extra + [
            'store_id' => $store->id,
            'category_id' => $this->sharedCategory->id,
            'name' => $p . $name,
            'slug' => Str::slug($p . $name) . '-' . Str::random(5),
            'status' => 'active',
            'mxik_code' => '10202001001000001',
        ]);
        $variant = fn (Product $of, string $name, string $price, array $extra = []) => ProductVariant::create($extra + [
            'product_id' => $of->id,
            'name' => $p . $name,
            'price' => $price,
            'stock' => 1000,
            'is_active' => true,
        ]);
        $w['product'] = $product('Shashlik');
        $w['variant'] = $variant($w['product'], 'Six', $this->n('18000'));
        $w['tea_product'] = $product('Choy');
        $w['tea'] = $variant($w['tea_product'], 'Choynak', $this->n('5000'));
        $w['plov_product'] = $product('Osh', ['batch_tracked' => true]);
        $w['plov'] = $variant($w['plov_product'], 'Osh kg', $this->n('120000'), ['unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '0.5']);
        $w['meat_product'] = $product('Baliq');
        $w['meat'] = $variant($w['meat_product'], 'Baliq kg', $this->n('90000'), ['unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.1']);
        $w['modifier_group'] = ModifierGroup::create(['store_id' => $store->id, 'name' => $p . 'Sous']);
        $w['modifier'] = $w['modifier_group']->modifiers()->create(['name' => $p . 'Achchiq', 'price_delta' => 2000, 'sort_order' => 0]);
        $w['product']->modifierGroups()->attach($w['modifier_group']->id);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function buildHall(array &$w, string $p): void
    {
        $at = ['store_id' => $w['store']->id, 'branch_id' => $w['branch']->id];
        $w['hall'] = Hall::create($at + ['name' => $p . 'Zal', 'sort_order' => 1]);
        $place = fn (string $name, string $kind, int $seats) => DiningTable::create($at + ['hall_id' => $w['hall']->id, 'name' => $p . $name, 'kind' => $kind, 'seats' => $seats]);
        $w['table'] = $place('Stol 1', 'table', 4);
        $w['table2'] = $place('Stol 2', 'table', 4);
        $w['free_table'] = $place('Stol 3', 'table', 4);
        $w['room'] = $place('Xona 1', 'room', 10);
        $w['service_fee'] = ServiceFeeSetting::create($at + ['hall_id' => $w['hall']->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_person']);
    }

    /**
     * The till's evening: an open shift, hall orders with sent and unsent lines, a bill, a paid takeaway with a tip
     * and its refund, a queue order and a marketplace order waiting for acceptance.
     *
     * @param  array<string, mixed>  $w
     */
    private function buildTill(array &$w, string $p): void
    {
        $c = $w['cashier_token'];
        $w['shift'] = PosShift::where('uuid', $this->own($c, 'POST', '/pos/shifts', ['opening_cash' => $this->n('100000')])->json('data.uuid'))->firstOrFail();

        $open = function (array $body) use ($c, $w): Order {
            return Order::where('uuid', $this->own($c, 'POST', '/pos/orders', $body + ['branch_id' => $w['branch']->id])->json('data.uuid'))->firstOrFail();
        };
        $round = function (Order $order, array $items, bool $send) use ($c): Order {
            $this->own($c, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'send' => $send, 'items' => $items]);

            return $order->refresh();
        };
        $line = fn (string $key, $quantity, array $extra = []) => ['variant_id' => $w[$key]->uuid, 'quantity' => $quantity] + $extra;

        // The hall order at table 1: shashlik sent, tea held, plov waiting for its weight
        $w['order'] = $open(['channel' => 'pos_hall', 'table_id' => $w['table']->id, 'guests_count' => 2]);
        $round($w['order'], [$line('variant', 2)], true);
        $round($w['order'], [$line('tea', 1), $line('meat', 1, ['awaiting_weight' => true])], false);
        $items = OrderItem::where('order_id', $w['order']->id)->orderBy('id')->get();
        $w['item'] = $items[0];
        $w['held_item'] = $items[1];
        $w['weight_item'] = $items[2];

        // A second hall order at table 2 (merged into the first), and one at the room with an open bill
        $w['order2'] = $open(['channel' => 'pos_hall', 'table_id' => $w['table2']->id, 'guests_count' => 2]);
        $round($w['order2'], [$line('tea', 1)], true);
        $w['billed_order'] = $open(['channel' => 'pos_hall', 'table_id' => $w['room']->id, 'guests_count' => 3]);
        $round($w['billed_order'], [$line('variant', 1)], true);
        $bill = $this->own($c, 'POST', "/pos/orders/{$w['billed_order']->uuid}/bills", ['version' => (int) $w['billed_order']->refresh()->version, 'mode' => 'single']);
        $w['bill'] = OrderBill::where('uuid', $bill->json('data.bills.0.uuid'))->firstOrFail();

        // A takeaway paid in cash with a tip, then partly refunded with the manager's approval
        $w['paid_order'] = $open(['channel' => 'pos_takeaway', 'items' => [$line('variant', 1)], 'send' => true]);
        $bill = $this->own($c, 'POST', "/pos/orders/{$w['paid_order']->uuid}/bills", ['version' => (int) $w['paid_order']->refresh()->version, 'mode' => 'single']);
        $w['paid_bill'] = OrderBill::where('uuid', $bill->json('data.bills.0.uuid'))->firstOrFail();
        $this->own($c, 'POST', "/pos/bills/{$w['paid_bill']->uuid}/payments", ['tenders' => [
            ['tender' => 'cash', 'amount' => (string) $w['paid_bill']->refresh()->total, 'tendered_amount' => $this->n('50000'), 'tip_amount' => $this->n('2000')],
        ]]);
        $w['payment'] = Payment::where('order_id', $w['paid_order']->id)->orderBy('id')->firstOrFail();
        $w['fiscal_receipt'] = FiscalReceipt::where('store_id', $w['store']->id)->where('type', 'sale')->orderBy('id')->firstOrFail();
        $w['tip'] = PosTip::where('store_id', $w['store']->id)->orderBy('id')->firstOrFail();
        $approval = $this->own($c, 'POST', '/pos/approvals', ['permission' => 'pos.refund', 'user_id' => $w['manager']->id, 'pin' => '5926'])->json('data.token');
        $this->own($c, 'POST', '/pos/refunds', ['payment_uuid' => $w['payment']->uuid, 'amount' => '1000', 'reason' => $p . 'Qaytarish', 'approval_token' => $approval]);
        $w['refund'] = PosRefund::where('store_id', $w['store']->id)->firstOrFail();

        // A fast-food order in the queue, and a marketplace order the till has not accepted yet
        $w['queue_order'] = $open(['channel' => 'pos_takeaway', 'items' => [$line('tea', 1)], 'send' => true]);
        $w['online_order'] = Order::create([
            'store_id' => $w['store']->id,
            'branch_id' => $w['branch']->id,
            'user_id' => $this->customer->id,
            'status' => OrderStatus::NEW,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => $this->n('18000'),
            'commission_amount' => 1800,
            'notes' => $p . 'Izoh',
        ]);
        OrderItem::create(['order_id' => $w['online_order']->id, 'product_variant_id' => $w['variant']->id, 'product_name' => $p . 'Shashlik', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => $this->n('18000'), 'total_price' => $this->n('18000')]);
        OrderStatusHistory::create(['order_id' => $w['online_order']->id, 'status' => OrderStatus::NEW->value, 'notes' => 'created']);

        // A marketplace delivery ready for a courier
        $w['delivery_order'] = Order::create([
            'store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'user_id' => $this->customer->id, 'status' => OrderStatus::READY,
            'order_type' => 'delivery', 'payment_method' => 'cash', 'total_price' => $this->n('18000'), 'commission_amount' => 1800,
            'shipping_address' => ['address' => $p . 'Manzil', 'latitude' => 41.311081, 'longitude' => 69.240562],
        ]);

        // B sells more: A's and B's counts and sums differ, so an A+B total is never an A total
        for ($i = 1; $this->building === 'B' && $i <= 3; $i++) {
            $extra = Order::create([
                'store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'user_id' => $this->customer->id, 'status' => OrderStatus::DELIVERED,
                'order_type' => 'pickup', 'payment_method' => 'cash', 'total_price' => bcadd('11317', (string) $i, 2), 'commission_amount' => 0,
            ]);
            OrderItem::create(['order_id' => $extra->id, 'product_variant_id' => $w['tea']->id, 'product_name' => $p . 'Choy', 'quantity' => 2, 'unit' => 'piece', 'unit_price' => $this->n('5000'), 'total_price' => bcadd('11317', (string) $i, 2)]);
        }

        $this->buildFrontOfHouse($w, $p);

        // An offline operation the server could not apply (a round on an order it never saw): a sync error to resolve
        $op = (string) Str::uuid();
        $this->own($w['till_token'], 'POST', '/pos/sync', ['operations' => [[
            'op_id' => $op, 'seq' => 1, 'type' => 'order.round', 'staff_id' => $w['cashier']->id, 'occurred_at' => now()->subMinutes(5)->toJSON(),
            'payload' => ['order' => (string) Str::uuid(), 'items' => [['line_id' => (string) Str::uuid(), 'variant_id' => $w['tea']->uuid, 'quantity' => '1', 'unit_price' => $this->n('5000')]]],
        ]]]);
        $w['sync_error'] = $op;

        $this->buildBackOffice($w, $p);

        // The owner's phone (partner app): a user device with a push token
        $phone = $this->own($o = $w['owner_token'], 'POST', '/devices', ['type' => 'partner_mobile', 'name' => $p . 'Telefon', 'platform' => 'android'])->json('data');
        $w['phone'] = \App\Models\Device::where('uuid', $phone['uuid'] ?? $phone['device']['uuid'])->firstOrFail();

        $this->buildStates($w, $p);
        $this->seedEmptyTables($w, $p);
        $range = $this->building === 'A' ? [self::A_IDS, self::B_IDS[0] - 1] : self::B_IDS;
        $w['complaint'] = \App\Models\Complaint::whereBetween('id', $range)->firstOrFail();
        $w['banquet_line'] = \App\Models\BanquetEventLine::whereBetween('id', $range)->orderBy('id')->firstOrFail();
        $w['combo_option'] = \App\Models\ComboSlotOption::whereBetween('id', $range)->orderBy('id')->firstOrFail();

        // The manager's one-time approvals on the till (60 s; the clock stands still and every call is undone)
        $w['approval'] = [];
        foreach (\App\Domain\Pos\Billing\ManagerApprovalService::approvable() as $permission) {
            $this->withBearer($c)->withHeader('Idempotency-Key', (string) Str::uuid());
            $response = $this->json('POST', '/api/v1/pos/approvals', ['permission' => $permission, 'user_id' => $w['manager']->id, 'pin' => '5926']);
            $w['approval'][$permission] = $response->status() === 201 ? (string) $response->json('data.token') : 'none-' . $permission;
        }
    }

    /**
     * The owner's set-up: kitchen, printers, menu modules, zones, fees, staff pay, tips, a bank report. Made through
     * the owner's API where the record has one, so it is exactly what the panel stores.
     *
     * @param  array<string, mixed>  $w
     */
    private function buildBackOffice(array &$w, string $p): void
    {
        $o = $w['owner_token'];
        $store = $w['store'];
        $branch = $w['branch']->id;
        $latest = fn (string $model) => $model::where('store_id', $store->id)->orderByDesc('id')->firstOrFail();

        $this->own($o, 'POST', '/owner/kitchen-stations', ['branch_id' => $branch, 'name' => $p . 'Mangal', 'kind' => 'mangal']);
        $w['station'] = $latest(\App\Models\KitchenStation::class);
        $this->own($o, 'PUT', '/owner/kitchen-station-routes', ['branch_id' => $branch, 'station_id' => $w['station']->id, 'product_ids' => [$w['product']->id]]);
        $w['station_route'] = $latest(\App\Models\KitchenStationRoute::class);
        $this->own($o, 'POST', '/owner/printers', ['branch_id' => $branch, 'name' => $p . 'Printer', 'purpose' => 'receipt', 'connection' => 'lan', 'address' => '192.168.1.50']);
        $w['printer'] = $latest(\App\Models\Printer::class);

        $w['combo_product'] = Product::create(['store_id' => $store->id, 'category_id' => $this->sharedCategory->id, 'name' => $p . 'Kombo', 'slug' => Str::slug($p . 'Kombo') . '-' . Str::random(5), 'status' => 'active']);
        ProductVariant::create(['product_id' => $w['combo_product']->id, 'name' => $p . 'Kombo', 'price' => $this->n('30000'), 'stock' => 100, 'is_active' => true]);
        $this->own($o, 'POST', '/owner/combos', ['product_id' => $w['combo_product']->id, 'slots' => [['name' => $p . 'Ichimlik', 'options' => [['variant_id' => $w['tea']->id, 'is_default' => true]]]]]);
        $w['combo'] = $latest(\App\Models\Combo::class);
        $w['set_product'] = Product::create(['store_id' => $store->id, 'category_id' => $this->sharedCategory->id, 'name' => $p . 'Kompleks', 'slug' => Str::slug($p . 'Kompleks') . '-' . Str::random(5), 'status' => 'active']);
        ProductVariant::create(['product_id' => $w['set_product']->id, 'name' => $p . 'Kompleks', 'price' => $this->n('25000'), 'stock' => 100, 'is_active' => true]);
        $this->own($o, 'POST', '/owner/meal-sets', ['product_id' => $w['set_product']->id, 'parts' => [['variant_id' => $w['tea']->id], ['variant_id' => $w['variant']->id]]]);
        $w['meal_set'] = $latest(\App\Models\DailySet::class);
        $this->own($o, 'PUT', '/owner/daily-menu', ['branch_id' => $branch, 'date' => '2026-06-10', 'product_ids' => [$w['tea_product']->id, $w['product']->id]]);

        $this->own($o, 'POST', '/owner/batches', ['branch_id' => $branch, 'product_id' => $w['plov_product']->id, 'quantity' => '10', 'label' => $p . 'Qozon']);
        $w['batch'] = $latest(\App\Models\MenuBatch::class);
        $this->own($o, 'POST', '/owner/batch-plans', ['branch_id' => $branch, 'product_id' => $w['plov_product']->id, 'time' => '18:00', 'quantity' => '10', 'label' => $p . 'Kechki qozon']);
        $w['batch_plan'] = $latest(\App\Models\MenuBatchPlan::class);
        $this->own($o, 'POST', '/owner/stop-list', ['branch_id' => $branch, 'type' => 'product', 'id' => $w['meat_product']->id, 'reason' => $p . 'Tugadi']);
        $w['stop_entry'] = $latest(\App\Models\StopListEntry::class);
        $this->own($o, 'POST', '/owner/ingredients', ['name' => $p . 'Piyoz', 'unit' => 'kg']);
        $w['ingredient'] = $latest(\App\Models\Ingredient::class);
        $this->own($o, 'PUT', "/owner/ingredients/{$w['ingredient']->id}/products", ['links' => [['product_id' => $w['product']->id]]]);
        $this->own($o, 'POST', '/owner/brands', ['name' => $p . 'Brend', 'short_code' => $p === 'A-' ? 'AB' : 'ZB']);
        $w['brand'] = $latest(\App\Models\Brand::class);
        $this->own($o, 'POST', "/owner/branches/{$branch}/packaging-rules", ['name' => $p . 'Paket', 'basis' => 'order', 'unit_price' => '1000', 'order_types' => ['takeaway']]);
        $w['packaging_rule'] = $latest(\App\Models\BranchPackagingRule::class);
        $this->own($o, 'POST', '/owner/delivery-zones', ['name' => $p . 'Zona', 'branch_id' => $branch, 'delivery_price' => 10000, 'min_order_amount' => 0, 'polygon' => [
            'type' => 'Polygon', 'coordinates' => [[[69.20, 41.28], [69.30, 41.28], [69.30, 41.34], [69.20, 41.34], [69.20, 41.28]]],
        ]]);
        $w['zone'] = $latest(\App\Models\DeliveryZone::class);
        $this->own($o, 'POST', '/owner/room-tariffs', ['branch_id' => $branch, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => $this->n('300000')]);
        $w['room_tariff'] = $latest(\App\Models\RoomTariff::class);
        $this->own($o, 'POST', '/owner/auto-guest-items', ['branch_id' => $branch, 'product_variant_id' => $w['tea']->id, 'quantity_per_guest' => '0.5']);
        $w['auto_guest_item'] = $latest(\App\Models\AutoGuestItem::class);

        $this->own($o, 'POST', '/owner/staff-pay/rules', ['branch_id' => $branch, 'role' => 'waiter', 'effective_from' => '2026-06-01', 'components' => [['type' => 'shift_rate', 'amount' => $this->n('100000')]]]);
        $w['pay_rule'] = $latest(\App\Models\StaffPayRule::class);
        $this->own($o, 'POST', '/owner/staff-pay/work-sessions', ['branch_id' => $branch, 'user_id' => $w['waiter']->id, 'clock_in_at' => '2026-06-09T09:00:00+05:00', 'clock_out_at' => '2026-06-09T18:00:00+05:00', 'reason' => $p . 'Unutdi']);
        $w['work_session'] = $latest(\App\Models\StaffWorkSession::class);
        $this->own($o, 'POST', '/owner/staff-pay/statements', ['branch_id' => $branch, 'date' => '2026-06-09']);
        $w['statement'] = $latest(\App\Models\StaffPayStatement::class);
        $w['statement_line'] = \App\Models\StaffPayStatementLine::where('statement_id', $w['statement']->id)->orderBy('id')->firstOrFail();

        $this->own($o, 'POST', '/owner/pos/tips/distributions', ['branch_id' => $branch, 'business_date' => '2026-06-10']);
        $w['tip_distribution'] = $latest(\App\Models\PosTipDistribution::class);
        $this->own($w['cashier_token'], 'PUT', "/pos/shifts/{$w['shift']->uuid}/bank-report", ['source' => 'manual', 'bank_total' => '0', 'bank_count' => 0, 'note' => $p . 'Hisobot']);
        $w['bank_report'] = $latest(\App\Models\BankTerminalReport::class);
    }

    /**
     * Records in the state a route needs before it can do its work (a second of a kind where one route uses up what
     * another needs): without them A's own call is refused for the record's state and proves nothing.
     *
     * @param  array<string, mixed>  $w
     */
    private function buildStates(array &$w, string $p): void
    {
        $c = $w['cashier_token'];
        $o = $w['owner_token'];
        $at = ['store_id' => $w['store']->id, 'branch_id' => $w['branch']->id];
        $latest = fn (string $model) => $model::where('store_id', $w['store']->id)->orderByDesc('id')->firstOrFail();
        $open = fn (array $body) => Order::where('uuid', $this->own($c, 'POST', '/pos/orders', $body + ['branch_id' => $w['branch']->id])->json('data.uuid'))->firstOrFail();
        $version = fn (Order $order) => (int) $order->fresh()->version;

        // A branch with nothing in it (deleting the main one is refused: it has orders)
        $w['spare_branch'] = $this->makeBranch($w['store'], $p . 'Bosh filial');

        // A second ingredient on the stop-list (linked to no dish), a second batch-tracked dish (the first has its batch)
        $this->own($o, 'POST', '/owner/ingredients', ['name' => $p . 'Sabzi', 'unit' => 'kg']);
        $w['ingredient2'] = $latest(\App\Models\Ingredient::class);
        $this->own($o, 'POST', "/owner/ingredients/{$w['ingredient2']->id}/stop", ['branch_id' => $w['branch']->id]);
        $w['plov2_product'] = Product::create(['store_id' => $w['store']->id, 'category_id' => $this->sharedCategory->id, 'name' => $p . 'Somsa', 'slug' => Str::slug($p . 'Somsa') . '-' . Str::random(5), 'status' => 'active', 'batch_tracked' => true]);
        ProductVariant::create(['product_id' => $w['plov2_product']->id, 'name' => $p . 'Dona', 'price' => $this->n('7000'), 'stock' => 100, 'is_active' => true]);

        // A promo code of the store, and an order it is applied to
        $w['promo_code'] = \App\Models\PromoCode::create(['code' => $p === 'A-' ? 'AKOD10' : 'ZZBKOD10', 'type' => 'percentage', 'value' => 10, 'store_id' => $w['store']->id, 'is_active' => true]);
        $w['table4'] = DiningTable::create($at + ['hall_id' => $w['hall']->id, 'name' => $p . 'Stol 4', 'kind' => 'table', 'seats' => 4]);
        $w['promo_order'] = $open(['channel' => 'pos_hall', 'table_id' => $w['table4']->id, 'guests_count' => 2, 'items' => [['variant_id' => $w['variant']->uuid, 'quantity' => 2]], 'send' => true]);
        $this->own($c, 'POST', "/pos/orders/{$w['promo_order']->uuid}/promo", ['version' => $version($w['promo_order']), 'code' => $w['promo_code']->code]);

        // A takeaway ready to be called, one not yet sent (its pickup time can move), one with packaging
        $w['ready_order'] = $open(['channel' => 'pos_takeaway', 'items' => [['variant_id' => $w['tea']->uuid, 'quantity' => 1]], 'send' => true]);
        $this->own($c, 'POST', "/pos/orders/{$w['ready_order']->uuid}/ready");
        $w['pickup_order'] = $open(['channel' => 'pos_takeaway', 'items' => [['variant_id' => $w['tea']->uuid, 'quantity' => 1]], 'pickup_at' => '2026-06-10T15:00:00+05:00']);
        $w['pack_order'] = $open(['channel' => 'pos_takeaway', 'items' => [['variant_id' => $w['variant']->uuid, 'quantity' => 1]]]);
        $w['unpack_order'] = $open(['channel' => 'pos_takeaway', 'items' => [['variant_id' => $w['variant']->uuid, 'quantity' => 1]]]);
        $this->own($c, 'POST', "/pos/orders/{$w['unpack_order']->uuid}/packaging/{$w['packaging_rule']->id}/remove", ['version' => $version($w['unpack_order']), 'reason' => $p . 'Kerak emas']);

        // The second hall order's service charge waived (to be restored); a held second course on the first one
        $this->own($o, 'POST', "/pos/orders/{$w['order2']->uuid}/charges/service_charge/waive", ['version' => $version($w['order2']), 'reason' => $p . 'Norozi']);
        $this->own($c, 'POST', "/pos/orders/{$w['order']->uuid}/rounds", ['version' => $version($w['order']), 'items' => [['variant_id' => $w['tea']->uuid, 'quantity' => 1, 'course_no' => 2, 'fire_mode' => 'hold']]]);

        // Bookings: one not confirmed, one that started two hours ago, one cancelled with its deposit
        $book = fn (array $body) => \App\Models\Booking::where('uuid', $this->own($c, 'POST', '/pos/bookings', $body + ['contact_name' => $p . 'Mehmon', 'guests_planned' => 2])->json('data.uuid'))->firstOrFail();
        $w['booking_new'] = $book(['dining_table_id' => $w['table4']->id, 'starts_at' => '2026-06-22T19:00:00+05:00']);
        $w['booking_late'] = $book(['dining_table_id' => $w['table2']->id, 'starts_at' => '2026-06-10T10:00:00+05:00', 'confirm' => true, 'skip_lead_time' => true]);
        $w['booking_cancelled'] = $book(['dining_table_id' => $w['table4']->id, 'starts_at' => '2026-06-23T19:00:00+05:00', 'confirm' => true]);
        $deposit = $this->own($c, 'POST', "/pos/bookings/{$w['booking_cancelled']->uuid}/deposits", ['amount' => $this->n('20000'), 'tender' => 'cash'])->json('data.payment.uuid');
        $w['cancelled_deposit'] = BookingPayment::where('uuid', $deposit)->firstOrFail();
        $this->own($c, 'POST', "/pos/bookings/{$w['booking_cancelled']->uuid}/cancel", ['reason' => $p . 'Bekor']);

        // Nasiya with a debt to repay; a blocked voucher; a fiscal receipt that failed (to retry)
        $this->own($o, 'POST', "/pos/customer-accounts/{$w['account']->uuid}/adjustments", ['amount' => $this->n('3000'), 'note' => $p . 'Qarz']);
        $blocked = $this->own($c, 'POST', '/pos/vouchers/sell', ['face_value' => $this->n('20000', true), 'tender' => 'cash'])->json('data');
        $w['blocked_voucher'] = Voucher::where('uuid', $blocked['voucher']['uuid'] ?? $blocked['uuid'])->firstOrFail();
        $this->own($o, 'POST', "/owner/pos/vouchers/{$w['blocked_voucher']->uuid}/block", ['reason' => $p . 'Yo\'qoldi']);
        $w['failed_receipt'] = FiscalReceipt::where('store_id', $w['store']->id)->where('type', 'advance')->orderBy('id')->firstOrFail();
        $w['failed_receipt']->forceFill(['status' => 'failed', 'error' => 'tenancy: provider down'])->save();

        // Banquets: one confirmed (its deposit paid), one under way today, one frozen
        $event = fn (string $date) => BanquetEvent::where('uuid', $this->own($o, 'POST', '/pos/banquets', [
            'branch_id' => $w['branch']->id, 'event_date' => $date, 'slot' => 'evening', 'hall_ids' => [$w['banquet_hall']->id],
            'event_type' => 'toy', 'customer_name' => $p . 'Mijoz ' . $date, 'guests_guaranteed' => 50, 'banquet_package_id' => $w['banquet_package']->id,
        ])->json('data.uuid'))->firstOrFail();
        $confirm = function (BanquetEvent $banquet) use ($c) {
            $row = BanquetPayment::where('banquet_event_id', $banquet->id)->orderBy('sort_order')->orderBy('id')->firstOrFail();
            $this->own($c, 'POST', "/pos/banquets/{$banquet->uuid}/payments/{$row->uuid}/pay", ['tender' => 'cash']);
        };
        $w['banquet_live'] = $event('2026-06-10');
        $confirm($w['banquet_live']);
        $this->own($o, 'POST', "/pos/banquets/{$w['banquet_live']->uuid}/start", ['version' => (int) $w['banquet_live']->fresh()->version]);
        $w['banquet_conf'] = $event('2026-06-11');
        $confirm($w['banquet_conf']);
        $w['banquet_frozen'] = $event('2026-06-12');
        $this->own($o, 'POST', "/pos/banquets/{$w['banquet_frozen']->uuid}/freeze", ['version' => (int) $w['banquet_frozen']->fresh()->version]);

        // The banquet's fiscal lines have their codes (settling issues the final receipt)
        $this->own($o, 'PUT', '/owner/pos/banquets/ops-settings', ['reserve_percent' => 5, 'osh' => ['persons_per_kg' => 8, 'ingredients' => [['name' => 'Guruch', 'unit' => 'kg', 'per_kg' => 1]], 'kazans' => [50], 'product_ids' => [$w['plov_product']->id]],
            'package_mxik' => '10305001001000000', 'hall_mxik' => '10305001001000000', 'extras_mxik' => '10305001001000000']);
        // A kitchen station nothing was sent to (one in use cannot be deleted); the cashier clocked in
        $this->own($o, 'POST', '/owner/kitchen-stations', ['branch_id' => $w['branch']->id, 'name' => $p . 'Salat', 'kind' => 'salad_bar']);
        $w['station2'] = $latest(\App\Models\KitchenStation::class);
        $this->own($c, 'POST', '/pos/clock/in');

        // A delivery on its way with the store's courier (the courier completes it)
        $w['courier_order'] = Order::create([
            'store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'user_id' => $this->customer->id, 'status' => OrderStatus::READY,
            'order_type' => 'delivery', 'payment_method' => 'cash', 'total_price' => $this->n('18000'), 'commission_amount' => 0,
            'shipping_address' => ['address' => $p . 'Manzil 2', 'latitude' => 41.311081, 'longitude' => 69.240562],
        ]);
        $this->own($o, 'POST', "/orders/{$w['courier_order']->uuid}/assign-courier", ['courier_id' => $w['courier']->id]);
        $this->own($w['courier_token'], 'POST', "/orders/{$w['courier_order']->uuid}/status", ['status' => 'on_the_way']);

        // A closed staff-pay statement (reopen, pay a line); the store's wallet has money for a payout
        $this->own($o, 'POST', '/owner/staff-pay/rules', ['branch_id' => $w['branch']->id, 'user_id' => $w['hostess']->id, 'effective_from' => '2026-05-01', 'components' => [['type' => 'shift_rate', 'amount' => $this->n('80000')]]]);
        $this->own($o, 'POST', '/owner/staff-pay/work-sessions', ['branch_id' => $w['branch']->id, 'user_id' => $w['hostess']->id, 'clock_in_at' => '2026-05-20T09:00:00+05:00', 'clock_out_at' => '2026-05-20T18:00:00+05:00', 'reason' => $p . 'Unutdi']);
        $this->own($o, 'POST', '/owner/staff-pay/statements', ['branch_id' => $w['branch']->id, 'date' => '2026-05-20']);
        $w['closed_statement'] = $latest(\App\Models\StaffPayStatement::class);
        $this->own($o, 'POST', "/owner/staff-pay/statements/{$w['closed_statement']->uuid}/close");
        $w['closed_line'] = \App\Models\StaffPayStatementLine::where('statement_id', $w['closed_statement']->id)->orderBy('id')->firstOrFail();
        DB::table('stores')->where('id', $w['store']->id)->update(['wallet_balance' => $this->n('500000')]);
    }

    /**
     * Tables the flows above leave empty, with what a row needs that the generic filler cannot guess.
     *
     * @return array<string, callable(array<string, mixed>, string): array<string, mixed>>
     */
    private function seedOverrides(): array
    {
        // The generic rows of a banquet's cancellation and settlement go to a second banquet: on the first they would
        // close it for the routes that need it open
        $second = fn (array $w, string $p) => ['banquet_event_id' => $w['banquet2']->id];

        return [
            'banquet_cancellations' => $second,
            'banquet_settlements' => $second,
        ];
    }

    /**
     * Every tenant table gets a row of each store: what the flows did not create is filled generically (each NOT NULL
     * column without a default: a foreign key points at this side's row of the parent, a checked enum takes its first
     * value, the rest a plain value of its type; texts carry the side's marker).
     *
     * @param  array<string, mixed>  $w
     */
    private function seedEmptyTables(array &$w, string $p): void
    {
        [$from, $to] = $this->building === 'A' ? [self::A_IDS, self::B_IDS[0] - 1] : self::B_IDS;
        $tables = collect(DB::select("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p') AND NOT c.relispartition ORDER BY c.relname"))->pluck('relname')->all();
        $columns = [];
        foreach (DB::select('SELECT table_name, column_name, data_type, is_nullable, column_default, character_maximum_length AS length, numeric_precision AS precision, numeric_scale AS scale
            FROM information_schema.columns WHERE table_schema = current_schema() ORDER BY ordinal_position') as $row) {
            $columns[$row->table_name][$row->column_name] = $row;
        }
        $foreign = [];
        foreach (DB::select("SELECT tc.relname AS child, a.attname AS col, pc.relname AS parent FROM pg_constraint c
            JOIN pg_class tc ON tc.oid = c.conrelid JOIN pg_class pc ON pc.oid = c.confrelid
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            WHERE c.contype = 'f' AND array_length(c.conkey, 1) = 1 AND NOT tc.relispartition") as $row) {
            $foreign[$row->child][$row->col] = $row->parent;
        }
        $enums = [];
        foreach (DB::select("SELECT conrelid::regclass::text AS t, pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE contype = 'c' AND connamespace = current_schema()::regnamespace") as $row) {
            if (preg_match("/\\(\\(?(\\w+)\\)?::text = ANY \\(\\(ARRAY\\['([^']+)'/", $row->def, $m)) {
                $enums[$row->t][$m[1]] = $m[2];
            }
        }
        // A string column an Eloquent model casts to an enum takes the enum's first value
        foreach (glob(app_path('Models') . '/*.php') as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');
            if (!is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $model = new $class();
            foreach ($model->getCasts() as $column => $cast) {
                $cast = explode(':', (string) $cast)[0];
                if (enum_exists($cast) && !isset($enums[$model->getTable()][$column])) {
                    $case = $cast::cases()[0];
                    $enums[$model->getTable()][$column] = $case instanceof \BackedEnum ? $case->value : $case->name;
                }
            }
        }
        $overrides = $this->seedOverrides();
        $parentRow = function (string $parent) use ($from, $to, $columns, $w): ?int {
            if ($parent === 'users') {
                return (int) $w['owner']->id;
            }
            if (!isset($columns[$parent]['id'])) {
                return null;
            }
            $id = DB::table($parent)->whereBetween('id', [$from, $to])->orderBy('id')->value('id') ?? DB::table($parent)->orderBy('id')->value('id');

            return $id !== null ? (int) $id : null;
        };

        $pending = $tables;
        for ($pass = 0; $pass < 3 && $pending !== []; $pass++) {
            $failed = [];
            foreach ($pending as $table) {
                if (!$this->seedTable($table, $columns[$table], $foreign[$table] ?? [], $enums[$table] ?? [], $overrides, $parentRow, $w, $p, [$from, $to])) {
                    $failed[] = $table;
                }
            }
            $pending = $failed;
        }
    }

    /**
     * @param  array<string, object>  $columns
     * @param  array<string, string>  $foreign
     * @param  array<string, string>  $enums
     * @param  array<string, mixed>  $w
     * @param  array{0: int, 1: int}  $range
     * @return bool false: the insert failed (a parent is seeded in a later pass)
     */
    private function seedTable(string $table, array $columns, array $foreign, array $enums, array $overrides, callable $parentRow, array $w, string $p, array $range): bool
    {
        [$from, $to] = $range;
        $mine = isset($columns['id'])
            ? DB::table($table)->whereBetween('id', [$from, $to])
            : (isset($columns['store_id']) ? DB::table($table)->where('store_id', $w['store']->id) : null);
        if (isset(self::GLOBAL_TABLES[$table]) || $mine === null || $mine->exists()) {
            return true;
        }
        $row = isset($overrides[$table]) ? ($overrides[$table])($w, $p) : [];
        foreach ($columns as $column => $info) {
            if (array_key_exists($column, $row) || $column === 'id' || $info->column_default !== null) {
                continue;
            }
            $required = $info->is_nullable === 'NO';
            if (isset($foreign[$column])) {
                $id = $parentRow($foreign[$column]);
                if ($id !== null) {
                    $row[$column] = $id;
                }

                continue;
            }
            if (in_array($column, ['store_id', 'branch_id'], true)) {
                $row[$column] = $column === 'store_id' ? $w['store']->id : $w['branch']->id;

                continue;
            }
            if (!$required) {
                continue;
            }
            $row[$column] = $enums[$column] ?? match ($info->data_type) {
                'bigint', 'integer', 'smallint' => 1,
                'numeric' => ($info->precision !== null && $info->precision - $info->scale < 6) ? '1' : $this->n('1000'),
                'boolean' => false,
                'date' => '2026-06-10',
                'timestamp without time zone', 'timestamp with time zone' => now(),
                'time without time zone' => '12:00:00',
                'json', 'jsonb' => '[]',
                'uuid' => (string) Str::uuid(),
                default => substr($p . $column, 0, $info->length ?? 40),
            };
        }
        try {
            DB::transaction(fn () => DB::table($table)->insert($row));
        } catch (\Illuminate\Database\QueryException $e) {
            $this->seedErrors[$table] = "{$table}: " . Str::limit($e->getMessage(), 220);

            return false;
        }
        unset($this->seedErrors[$table]);

        return true;
    }

    /**
     * Bookings, banquets, nasiya and corporate accounts, gift vouchers, the waitlist, a QR payment and a bill print
     * request: each as the till or the owner creates it.
     *
     * @param  array<string, mixed>  $w
     */
    private function buildFrontOfHouse(array &$w, string $p): void
    {
        $c = $w['cashier_token'];
        $o = $w['owner_token'];

        $booking = $this->own($c, 'POST', '/pos/bookings', [
            'dining_table_id' => $w['room']->id, 'starts_at' => '2026-06-20T19:00:00+05:00', 'guests_planned' => 6,
            'contact_name' => $p . 'Mehmon', 'contact_phone' => '+998901112233', 'confirm' => true,
        ])->json('data.uuid');
        $w['booking'] = Booking::where('uuid', $booking)->firstOrFail();
        $this->own($c, 'POST', "/pos/bookings/{$booking}/items", ['product_variant_id' => $w['tea']->id, 'quantity' => '2']);
        $w['booking_item'] = BookingItem::where('booking_id', $w['booking']->id)->firstOrFail();
        $deposit = $this->own($c, 'POST', "/pos/bookings/{$booking}/deposits", ['amount' => $this->n('50000'), 'tender' => 'cash'])->json('data.payment.uuid');
        $w['deposit'] = BookingPayment::where('uuid', $deposit)->firstOrFail();

        $w['banquet_hall'] = Hall::create(['store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'name' => $p . 'Banket zal', 'sort_order' => 2]);
        BanquetHallRate::create(['store_id' => $w['store']->id, 'hall_id' => $w['banquet_hall']->id, 'slot' => 'evening', 'price' => $this->n('5000000')]);
        $w['banquet_package'] = BanquetPackage::create(['store_id' => $w['store']->id, 'name' => $p . 'Standart', 'per_person_price' => $this->n('150000'), 'minimum_guests' => 50, 'included' => ['Osh']]);
        $w['banquet_extra'] = BanquetExtra::create(['store_id' => $w['store']->id, 'name' => $p . 'Tort', 'price' => $this->n('800000'), 'pricing' => 'per_event']);
        $banquet = $this->own($o, 'POST', '/pos/banquets', [
            'branch_id' => $w['branch']->id, 'event_date' => '2026-08-20', 'slot' => 'evening', 'hall_ids' => [$w['banquet_hall']->id],
            'event_type' => 'toy', 'customer_name' => $p . 'Karimovlar', 'customer_phone' => '90 123 45 67', 'guests_guaranteed' => 100,
            'banquet_package_id' => $w['banquet_package']->id,
        ])->json('data.uuid');
        $w['banquet'] = BanquetEvent::where('uuid', $banquet)->firstOrFail();
        $w['banquet2'] = BanquetEvent::where('uuid', $this->own($o, 'POST', '/pos/banquets', [
            'branch_id' => $w['branch']->id, 'event_date' => '2026-09-20', 'slot' => 'evening', 'hall_ids' => [$w['banquet_hall']->id],
            'event_type' => 'osh', 'customer_name' => $p . 'Ikkinchi', 'guests_guaranteed' => 60, 'banquet_package_id' => $w['banquet_package']->id,
        ])->json('data.uuid'))->firstOrFail();
        $rows = $this->own($o, 'PUT', "/pos/banquets/{$banquet}/payments", ['rows' => [
            ['kind' => 'deposit', 'due_date' => '2026-06-15', 'amount' => $this->n('5000000')],
            ['kind' => 'final', 'due_date' => '2026-08-19', 'amount' => bcsub((string) $w['banquet']->fresh()->total, $this->n('5000000'), 2)],
        ]]);
        $w['banquet_row'] = BanquetPayment::where('banquet_event_id', $w['banquet']->id)->orderBy('sort_order')->orderBy('id')->firstOrFail();
        $w['banquet_row2'] = BanquetPayment::where('banquet_event_id', $w['banquet']->id)->orderByDesc('sort_order')->orderByDesc('id')->firstOrFail();
        $this->own($o, 'POST', "/pos/banquets/{$banquet}/payments/{$w['banquet_row2']->uuid}/bank-transfer", ['amount' => $this->n('1000000'), 'reference' => $p . 'PP-1']);
        $w['banquet_entry'] = BanquetPaymentEntry::where('banquet_event_id', $w['banquet']->id)->orderBy('id')->firstOrFail();

        $account = $this->own($o, 'POST', '/pos/customer-accounts', ['name' => $p . 'Nasiya', 'phone' => '+998901234500', 'credit_limit' => $this->n('1000000')])->json('data.uuid');
        $w['account'] = CustomerAccount::where('uuid', $account)->firstOrFail();
        $corporate = $this->own($o, 'POST', '/pos/customer-accounts', [
            'name' => $p . 'Korxona', 'kind' => 'corporate', 'company_name' => $p . 'MChJ', 'credit_limit' => $this->n('5000000'), 'talon_mode' => 'daily_limit',
        ])->json('data.uuid');
        $w['corporate'] = CustomerAccount::where('uuid', $corporate)->firstOrFail();
        $member = $this->own($o, 'POST', "/pos/customer-accounts/{$corporate}/members", ['name' => $p . 'Xodim', 'phone' => '+998901234501', 'daily_limit' => $this->n('50000')])->json('data.uuid');
        $w['member'] = CustomerAccountMember::where('uuid', $member)->firstOrFail();

        $voucher = $this->own($c, 'POST', '/pos/vouchers/sell', ['face_value' => $this->n('100000', true), 'tender' => 'cash', 'buyer_name' => $p . 'Xaridor'])->json('data');
        $w['voucher'] = Voucher::where('uuid', $voucher['voucher']['uuid'] ?? $voucher['uuid'])->firstOrFail();
        $w['voucher_code'] = $voucher['code'] ?? $voucher['voucher']['code'];

        $entry = $this->own($w['manager_token'], 'POST', '/pos/waitlist', ['guest_name' => $p . 'Navbatchi', 'party_size' => 3, 'guest_phone' => '+998901234502'])->json('data.uuid');
        $w['waitlist'] = PosWaitlistEntry::where('uuid', $entry)->firstOrFail();

        config(['qr_payments.sandbox' => true, 'qr_payments.simulate' => true, 'qr_payments.simulate_drivers' => ['click']]);
        $w['store']->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'click' => ['enabled' => true, 'service_id' => $p === 'A-' ? '41001' : '41002', 'merchant_id' => '22001', 'merchant_user_id' => '33001', 'secret_key' => 'tenancy-click-' . $p],
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'delivery_fee' => 0,
            ],
        ])->save();
        PosQrSetting::create(['store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'providers' => ['click'], 'default_provider' => 'click']);
        $qr = $this->own($c, 'POST', "/pos/bills/{$w['bill']->uuid}/qr", ['provider' => 'click'])->json('data');
        $w['qr'] = PosQrPayment::where('uuid', $qr['qr']['uuid'])->firstOrFail();

        $this->own($o, 'POST', "/pos/bills/{$w['bill']->uuid}/print-request");
        $w['print_request'] = (string) DB::table('pos_bill_print_requests')->where('store_id', $w['store']->id)->value('request_id');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Observing B. Every row B's build inserted got an id in B's own range (the sequences are moved before and after
    // it), so "B's rows" are the rows with such an id, and "a reference to B" is any integer column holding such an id,
    // any json holding one, any uuid column holding one of B's uuids, any text holding B's marker: with or without a
    // foreign key, in any table. A response shows B when it holds B's marker, one of B's uuids, a number in B's id
    // range, one of B's own amounts, or an A+B total.
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * B's ids: every row B's build creates; A's from A_IDS, rows created later from AFTER_IDS. Nine digits on purpose:
     * no random number the application makes has nine (OTP 6, PIN 4, sandbox receipt numbers 8, corporate member codes
     * and change revisions 10 or more), and every id column is bigint and every id rule takes nine digits.
     */
    public const B_IDS = [410_000_000, 410_099_999];

    public const A_IDS = 2_000_000;

    public const AFTER_IDS = 60_000_000;

    /** A whole 9-digit number starting like B's ids: not part of a longer number, a word, a token, a uuid or a decimal. */
    public const B_ID_PATTERN = '/(?<![0-9A-Za-z.\-_])41000[0-9]{4}(?![0-9A-Za-z_\-]|\.[0-9])/';

    /** The ids B's build really used: [B_IDS[0], the highest] (a few hundred wide). */
    private array $bIds = [0, -1];

    private int $bLastId = 0;

    /** @var array<string, string> table => why its generic row could not be inserted */
    public array $seedErrors = [];

    /**
     * Tables that hold no store's data (no store_id or branch_id, no foreign key into a table that has one): platform,
     * queue, auth and reference rows. Observed all the same for references to B (a json, a payload, a text naming B),
     * but no row of theirs is B's. A table neither tenant nor listed here fails prepareObservation: classify it.
     */
    public const GLOBAL_TABLES = [
        'cache' => 'Laravel database cache (tests use the array store, diffed per call)',
        'cache_locks' => 'Laravel cache locks',
        'categories' => 'platform category tree shared by every store (the world\'s one category is made before A)',
        'districts' => 'platform reference: districts',
        'regions' => 'platform reference: regions',
        'failed_jobs' => 'queue: failed jobs (a payload naming B is still a reference)',
        'jobs' => 'queue: pending jobs (a payload naming B is still a reference)',
        'job_batches' => 'queue: job batches',
        'features' => 'Pennant feature flags (platform)',
        'idempotency_keys' => 'replay store keyed by user and key; a stored response naming B is a reference',
        'migrations' => 'schema history',
        'monitoring_markers' => 'platform monitoring state',
        'partition_archives' => 'M3 partition archive log (platform)',
        'password_reset_tokens' => 'auth: reset tokens by email',
        'payment_providers' => 'platform payment provider catalogue',
        'phone_verifications' => 'auth: one-time codes by phone',
        'platform_daily_stats' => 'RIZQIM-wide statistics, never shown to a store',
        'platform_hourly_stats' => 'RIZQIM-wide statistics, never shown to a store',
        'platform_stats_days' => 'RIZQIM-wide statistics, never shown to a store',
        'restore_drills' => 'backup restore drill log (platform)',
        'sessions' => 'web sessions (string ids)',
        'settings' => 'platform settings',
        'tariff_plans' => 'platform billing plans',
        'stores' => 'the store itself: B\'s row is the one with B\'s id (observed like every B row)',
    ];

    /** @var array<string, string> table => WHERE clause: B's rows, and every row that references B */
    private array $bFilters = [];

    /** @var list<string> */
    private array $bUuids = [];

    /** @var list<string> B's own amounts (no A row holds them), as the API prints them */
    private array $bAmounts = [];

    /** @var array<string, list<string>> what an A+B total of B's main lists would print as => which */
    private array $abTotals = [];

    /** @var list<string> B's people's Telegram chats (a message to one is a message about B) */
    private array $bChats = [];

    /** @var array<string, string> B as the world left it (every call is undone, so every call starts from it) */
    private array $bBaseline = [];

    /** @var array<string, string> partition => its partitioned table */
    private array $partitionParents = [];

    /** @var array<string, array{0: int, 1: bool}>|null sequences as they were before the world */
    private ?array $savedSequences = null;

    /** @var list<string> what a call did beside the database: events, notifications, HTTP, cache, other connections */
    private array $sideEffects = [];

    private bool $listening = false;

    /** @var (\Closure(): mixed)|null run inside each call before it is undone (another protected scope's snapshot) */
    protected ?\Closure $observeAlso = null;

    /**
     * Moves every sequence of the schema to $to (non-transactional; through the owner connection when the tests run
     * as a DML-only role, which may not call setval). The first call remembers where they were; the test's end puts
     * them back, so no other test sees ids it did not expect.
     */
    protected function moveSequences(int $to): void
    {
        $connection = \App\Support\Database\OwnerConnection::prepare() ? DB::connection(\App\Support\Database\OwnerConnection::NAME) : DB::connection();
        $sequences = DB::select('SELECT schemaname, sequencename, last_value FROM pg_sequences WHERE schemaname = current_schema()');
        if ($this->savedSequences === null) {
            $this->savedSequences = [];
            foreach ($sequences as $row) {
                $this->savedSequences["{$row->schemaname}.{$row->sequencename}"] = [(int) ($row->last_value ?? 1), $row->last_value !== null];
            }
            $this->beforeApplicationDestroyed(function () {
                $connection = \App\Support\Database\OwnerConnection::prepare() ? DB::connection(\App\Support\Database\OwnerConnection::NAME) : DB::connection();
                foreach ($this->savedSequences ?? [] as $sequence => [$value, $called]) {
                    $connection->select('SELECT setval(?::regclass, ?, ?)', [$sequence, $value, $called]);
                }
                $this->savedSequences = null;
            });
        }
        foreach ($sequences as $row) {
            $connection->select('SELECT setval(?::regclass, ?, false)', ["{$row->schemaname}.{$row->sequencename}", $to]);
        }
    }

    /**
     * Rows this transaction inserted, updated or deleted so far, per table (Postgres' own per-transaction counters,
     * savepoints rolled back included): the tables a call wrote are the only ones to hash again.
     *
     * @return array<string, int>
     */
    private function writeCounters(): array
    {
        $counters = [];
        foreach (DB::select('SELECT relname, n_tup_ins + n_tup_upd + n_tup_del AS n FROM pg_stat_xact_user_tables') as $row) {
            $table = $this->partitionParents[$row->relname] ?? $row->relname;
            $counters[$table] = ($counters[$table] ?? 0) + (int) $row->n;
        }

        return $counters;
    }

    /**
     * Builds the filters from the schema, B's uuids, amounts and A+B totals, and checks the observation itself: every
     * table classified, the write counters working, B big enough to be seen.
     */
    protected function prepareObservation(): void
    {
        $last = $this->bLastId;
        if ($last === 0 || $last >= 410_001_000) {
            throw new RuntimeException("Observation: B's ids end at {$last}, outside 410000000-410000999");
        }
        $this->bIds = [self::B_IDS[0], $last];
        [$from, $to] = $this->bIds;
        $tables = collect(DB::select("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p') AND NOT c.relispartition ORDER BY c.relname"))->pluck('relname')->all();
        $columns = [];
        foreach (DB::select('SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = current_schema() ORDER BY ordinal_position') as $row) {
            $columns[$row->table_name][$row->column_name] = $row->data_type;
        }
        $this->assertTablesClassified($tables, $columns);
        $empty = [];
        foreach ($tables as $table) {
            $own = isset(self::GLOBAL_TABLES[$table]) ? null : $this->ownRows($table, $columns[$table]);
            if ($own !== null && !DB::table($table)->whereRaw(str_replace(['BETWEEN 410000000 AND 410099999'], ["BETWEEN {$from} AND {$to}"], $own))->exists()) {
                $empty[] = $table;
            }
        }
        if ($empty !== [] || $this->seedErrors !== []) {
            throw new RuntimeException('World: tenant tables without a row of B: ' . implode(', ', $empty) . ' ' . implode('; ', $this->seedErrors));
        }

        $uuids = [];
        foreach ($tables as $table) {
            $own = $this->ownRows($table, $columns[$table]);
            foreach ($columns[$table] as $column => $type) {
                if ($type === 'uuid' && $own !== null) {
                    foreach (DB::table($table)->whereRaw($own)->whereNotNull($column)->pluck($column) as $uuid) {
                        $uuids[strtolower((string) $uuid)] = true;
                    }
                }
            }
        }
        $this->bUuids = array_keys($uuids);
        $uuidList = $this->bUuids === [] ? "''" : implode(',', array_map(fn ($u) => "'{$u}'", $this->bUuids));

        $filters = [];
        foreach ($tables as $table) {
            $parts = [];
            foreach ($columns[$table] as $column => $type) {
                $quoted = '"' . $column . '"';
                $parts[] = match ($type) {
                    'bigint', 'integer', 'smallint' => "{$quoted} BETWEEN {$from} AND {$to}",
                    'json', 'jsonb' => "{$quoted}::text ~ '(^|[^0-9A-Za-z.\\-_])410000[0-9]{3}([^0-9A-Za-z_.\\-]|$)' OR {$quoted}::text ILIKE '%" . self::MARK . "%' OR {$quoted}::text ~* '(" . ($this->bUuids === [] ? 'x^' : implode('|', $this->bUuids)) . ")'",
                    'uuid' => "{$quoted}::text IN ({$uuidList})",
                    'character varying', 'text', 'character' => "{$quoted} ILIKE '%" . self::MARK . "%'",
                    default => null,
                };
            }
            $parts = array_values(array_filter($parts));
            if ($parts === []) {
                if (!isset(self::GLOBAL_TABLES[$table])) {
                    throw new RuntimeException("Observation: {$table} has no column a reference to B could be in");
                }

                continue;
            }
            $filters[$table] = '(' . implode(' OR ', $parts) . ')';
        }
        $this->bFilters = $filters;

        $this->bAmounts = $this->distinctAmounts($tables, $columns);
        $this->abTotals = $this->abTotals();
        $this->bChats = DB::table('users')->whereBetween('id', self::B_IDS)->whereNotNull('telegram_id')->pluck('telegram_id')->map(fn ($id) => (string) $id)->all();
        $this->partitionParents = collect(DB::select('SELECT c.relname AS leaf, p.relname AS parent FROM pg_inherits i
            JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent'))->pluck('parent', 'leaf')->all();

        // The write counters must see a write (track_counts on), else no call's writes would be hashed again
        $level = DB::transactionLevel();
        $before = $this->writeCounters();
        DB::beginTransaction();
        DB::table('regions')->where('id', -1)->delete();
        DB::table('sessions')->insert(['id' => 'tenancy-canary', 'payload' => '', 'last_activity' => 0]);
        $seen = array_diff_assoc($this->writeCounters(), $before);
        DB::rollBack($level);
        if (!isset($seen['sessions'])) {
            throw new RuntimeException('Observation: pg_stat_xact_user_tables does not see this transaction\'s writes (track_counts off?)');
        }

        $this->bBaseline = $this->snapshotB();
        if (count($this->bUuids) < 50 || $this->bAmounts === [] || DB::table('orders')->whereBetween('id', self::B_IDS)->count() < 5) {
            throw new RuntimeException('World: B is too small to observe (' . count($this->bUuids) . ' uuids)');
        }
        $this->listenForSideEffects();
    }

    /**
     * The WHERE clause of B's own rows of a table: an id in B's range (a uuid-keyed table: store_id or branch_id of B).
     *
     * @param  array<string, string>  $columns
     */
    private function ownRows(string $table, array $columns): ?string
    {
        [$from, $to] = self::B_IDS;
        if (in_array($columns['id'] ?? null, ['bigint', 'integer', 'smallint'], true)) {
            return "id BETWEEN {$from} AND {$to}";
        }
        foreach (['store_id', 'branch_id', 'user_id', 'tokenable_id'] as $column) {
            if (isset($columns[$column])) {
                return "{$column} BETWEEN {$from} AND {$to}";
            }
        }

        return null;
    }

    /**
     * Every table is tenant data (store_id, branch_id, or a foreign key chain into one) or listed in GLOBAL_TABLES.
     *
     * @param  list<string>  $tables
     * @param  array<string, array<string, string>>  $columns
     */
    private function assertTablesClassified(array $tables, array $columns): void
    {
        $parents = [];
        foreach (DB::select("SELECT tc.relname AS child, pc.relname AS parent FROM pg_constraint c
            JOIN pg_class tc ON tc.oid = c.conrelid JOIN pg_class pc ON pc.oid = c.confrelid WHERE c.contype = 'f'") as $row) {
            $parents[$row->child][] = $row->parent;
        }
        $tenant = array_filter($tables, fn ($t) => isset($columns[$t]['store_id']) || isset($columns[$t]['branch_id']));
        do {
            $before = count($tenant);
            foreach ($tables as $table) {
                if (!in_array($table, $tenant, true) && array_intersect($parents[$table] ?? [], $tenant) !== []) {
                    $tenant[] = $table;
                }
            }
        } while (count($tenant) !== $before);

        $unclassified = array_values(array_diff($tables, $tenant, array_keys(self::GLOBAL_TABLES)));
        $stale = array_values(array_diff(array_keys(self::GLOBAL_TABLES), $tables));
        if ($unclassified !== [] || $stale !== []) {
            throw new RuntimeException('Observation: classify these tables in TenancyWorld::GLOBAL_TABLES or give them a store: '
                . implode(', ', $unclassified) . ($stale !== [] ? '; listed but gone: ' . implode(', ', $stale) : ''));
        }
    }

    /**
     * B's own amounts: the values of B's numeric columns that no A row holds, as "123.45" strings.
     *
     * @param  list<string>  $tables
     * @param  array<string, array<string, string>>  $columns
     * @return list<string>
     */
    private function distinctAmounts(array $tables, array $columns): array
    {
        $values = ['A' => [], 'B' => []];
        foreach ($tables as $table) {
            if (!in_array($columns[$table]['id'] ?? null, ['bigint', 'integer'], true)) {
                continue;
            }
            foreach ($columns[$table] as $column => $type) {
                if ($type !== 'numeric') {
                    continue;
                }
                foreach (['A' => [self::A_IDS, self::B_IDS[0] - 1], 'B' => self::B_IDS] as $side => $range) {
                    foreach (DB::table($table)->whereBetween('id', $range)->whereNotNull($column)->distinct()->pluck($column) as $value) {
                        $values[$side][$this->money((string) $value)] = true;
                    }
                }
            }
        }

        // Only amounts with cents (B's are A's + 317.17): no random number the application makes (codes, receipt
        // numbers, revisions) has a decimal part, and the pattern only matches a whole number with its cents
        return array_values(array_filter(array_keys(array_diff_key($values['B'], $values['A'])), fn ($v) => str_contains($v, '.') && strlen(str_replace('.', '', $v)) >= 5));
    }

    private function money(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    /**
     * What a list or report that forgot the store would add up to: counts and amount sums of A's and B's main tables
     * together (A's alone, or B's alone, are not these).
     *
     * @return array<string, list<string>>
     */
    private function abTotals(): array
    {
        $a = [self::A_IDS, self::B_IDS[0] - 1];
        $totals = [];
        foreach (['orders' => 'total_price', 'payments' => 'amount', 'order_items' => 'total_price', 'pos_shifts' => null, 'products' => null, 'bookings' => null, 'customer_accounts' => null] as $table => $amount) {
            $countA = DB::table($table)->whereBetween('id', $a)->count();
            $countB = DB::table($table)->whereBetween('id', self::B_IDS)->count();
            // Small counts (1 + 1) are everywhere in a response (guests, quantities): only a count of ten or more
            if ($countA > 0 && $countB > 0 && $countA + $countB >= 10) {
                $totals[(string) ($countA + $countB)][] = "{$table} count";
            }
            if ($amount !== null) {
                $sum = (string) DB::table($table)->where(fn ($q) => $q->whereBetween('id', $a)->orWhereBetween('id', self::B_IDS))->sum($amount);
                $totals[$this->money($sum)][] = "{$table} {$amount} sum";
            }
        }

        return $totals;
    }

    /**
     * @param  list<string>|null  $only  just these tables (the others are left out)
     * @return array<string, string> table => md5 of B's rows and of the rows referencing B
     */
    protected function snapshotB(?array $only = null): array
    {
        $selects = [];
        foreach ($this->bFilters as $table => $where) {
            if ($only !== null && !in_array($table, $only, true)) {
                continue;
            }
            $selects[] = "SELECT '{$table}' AS t, md5(coalesce(string_agg(x::text, '|' ORDER BY x::text), '')) AS h, count(*) AS n FROM \"{$table}\" x WHERE {$where}";
        }
        $snapshot = [];
        if ($selects === []) {
            return $snapshot;
        }
        foreach (DB::select(implode(' UNION ALL ', $selects)) as $row) {
            $snapshot[$row->t] = $row->h . ':' . $row->n;
        }
        ksort($snapshot);

        return $snapshot;
    }

    /**
     * What of B a response (headers and body) shows; what the request sent itself is not "shown".
     */
    protected function bLeakIn(string $content, string $request = ''): ?string
    {
        if (($at = stripos($content, self::MARK)) !== false && stripos($request, self::MARK) === false) {
            return 'shows "' . substr($content, max(0, $at - 40), 100) . '"';
        }
        $lower = strtolower($content);
        $sent = strtolower($request);
        foreach ($this->bUuids as $uuid) {
            if (str_contains($lower, $uuid) && !str_contains($sent, $uuid)) {
                return "shows B's uuid {$uuid}";
            }
        }
        if (preg_match_all(self::B_ID_PATTERN, $content, $ids)) {
            preg_match_all(self::B_ID_PATTERN, $request, $sentIds);
            $shown = array_filter(array_diff($ids[0], $sentIds[0]), fn ($id) => (int) $id <= $this->bIds[1]);
            if ($shown !== []) {
                $id = reset($shown);
                $at = strpos($content, $id);

                return "shows B's id {$id}: \"" . substr($content, max(0, $at - 40), 90) . '"';
            }
        }
        foreach ($this->bAmounts as $amount) {
            if (preg_match('/(?<![0-9A-Za-z.\-_])' . preg_quote($amount, '/') . '0*(?![0-9A-Za-z_]|\.?[0-9])/', $content, $m, PREG_OFFSET_CAPTURE) && !str_contains($request, $amount)) {
                return "shows B's amount {$amount}: \"" . substr($content, max(0, $m[0][1] - 40), 90) . '"';
            }
        }
        $json = json_decode(($p = strpos($content, "\n\n")) !== false ? substr($content, $p + 2) : $content, true);
        if (is_array($json) && ($total = $this->abTotalIn($json)) !== null) {
            return $total;
        }

        return null;
    }

    /**
     * A count or sum of A and B together under a key that names a total.
     *
     * @param  array<mixed>  $json
     */
    private function abTotalIn(array $json, string $path = ''): ?string
    {
        foreach ($json as $key => $value) {
            $at = $path === '' ? (string) $key : "{$path}.{$key}";
            if (is_array($value)) {
                if (($found = $this->abTotalIn($value, $at)) !== null) {
                    return $found;
                }
            } elseif ((is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))
                && preg_match('/(^|\.)(total|count|sum|revenue|total_\w+|\w+_total|\w+_count)$/i', $at, $m)) {
                // A bare total/count (a list's meta) is compared with every A+B total, a named one with its own
                // table's only ("orders_count" with orders)
                $last = strtolower($m[2]);
                $kinds = array_filter($this->abTotals[$this->money((string) $value)] ?? [], fn (string $kind) => in_array($last, ['total', 'count', 'sum', 'revenue'], true)
                    || str_contains($last, rtrim(explode('_', explode(' ', $kind)[0])[0], 's')));
                if ($kinds !== []) {
                    return "{$at} = {$value} is an A+B total (" . implode(', ', $kinds) . ')';
                }
            }
        }

        return null;
    }

    /**
     * Watches what a call does beside its response and its own connection's rows: events (broadcasts on B's channels),
     * notifications to B's people, HTTP to the outside naming B, queries on another connection (not undone with the
     * call).
     */
    private function listenForSideEffects(): void
    {
        if ($this->listening) {
            return;
        }
        $this->listening = true;
        \Illuminate\Support\Facades\Event::listen('*', function (string $name, array $payload) {
            foreach ($payload as $event) {
                if ($event instanceof \Illuminate\Contracts\Broadcasting\ShouldBroadcast) {
                    $channels = collect((array) $event->broadcastOn())->map(fn ($channel) => (string) ($channel->name ?? $channel))->implode(' ');
                    if ($this->namesB($channels)) {
                        $this->sideEffects[] = "broadcasts {$name} on B's channel: {$channels}";
                    }
                }
                if ($event instanceof \Illuminate\Notifications\Events\NotificationSending && $this->isB($event->notifiable)) {
                    $this->sideEffects[] = 'notifies B\'s ' . get_class($event->notifiable) . ' ' . get_class($event->notification);
                }
            }
        });
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) {
            if ($query->connectionName !== DB::getDefaultConnection()) {
                $this->sideEffects[] = "runs SQL on the {$query->connectionName} connection (not undone): " . Str::limit($query->sql, 120);
            }
        });
    }

    /**
     * A key or channel name that names B: keys and channels join ids with dots and colons, so any digit run in B's id
     * range counts, or one of B's uuids.
     */
    private function namesB(string $name): bool
    {
        preg_match_all('/\d+/', $name, $runs);
        foreach ($runs[0] as $run) {
            if ((int) $run >= $this->bIds[0] && (int) $run <= $this->bIds[1]) {
                return true;
            }
        }

        return collect($this->bUuids)->contains(fn ($uuid) => str_contains(strtolower($name), $uuid));
    }

    private function isB(mixed $notifiable): bool
    {
        return $notifiable instanceof \Illuminate\Database\Eloquent\Model
            && ((($id = $notifiable->getKey()) !== null && is_numeric($id) && $id >= self::B_IDS[0] && $id <= self::B_IDS[1])
                || ((int) ($notifiable->store_id ?? 0) >= self::B_IDS[0] && (int) ($notifiable->store_id ?? 0) <= self::B_IDS[1]));
    }

    /**
     * After-commit callbacks the call registered: the savepoint is rolled back, so Laravel would drop them; they run
     * here, inside it, so what they write is observed and undone like the call's own writes.
     */
    private function runAfterCommitCallbacks(int $level): void
    {
        $manager = app('db.transactions');
        $ran = [];
        for ($round = 0; $round < 5; $round++) {
            $records = $manager->getCommittedTransactions()->merge($manager->getPendingTransactions())
                ->filter(fn ($record) => $record->level > $level);
            $pending = false;
            foreach ($records as $record) {
                foreach ($record->getCallbacks() as $callback) {
                    $id = spl_object_id($callback);
                    if (!isset($ran[$id])) {
                        $ran[$id] = true;
                        $pending = true;
                        $callback();
                    }
                }
            }
            if (!$pending) {
                return;
            }
        }
    }

    /**
     * One request, undone afterwards: the database to a savepoint (after-commit callbacks run first), the array cache
     * to what it held (a key naming B written or removed is reported), the clock to NOW. B's snapshot is taken before
     * the undo; `side` lists what the call did outside the response and the rows.
     *
     * @param  array<string, mixed>  $body  the JSON body, or the query string of a GET
     * @return array{status: int, request: string, content: string, json: mixed, after: array<string, string>, side: list<string>}
     */
    protected function isolatedCall(string $method, string $uri, ?string $token, array $body, bool $observe = true): array
    {
        $store = app('cache')->store()->getStore();
        if (!$store instanceof \Illuminate\Cache\ArrayStore) {
            throw new RuntimeException('The tenancy suite needs CACHE_STORE=array (phpunit.xml), got ' . get_class($store));
        }
        $saved = (fn () => $this->storage)->call($store);
        $level = DB::transactionLevel();
        $writes = $this->writeCounters();
        $this->sideEffects = [];
        $http = count(Http::recorded());
        DB::beginTransaction();
        try {
            $this->withBearer($token);
            if ($method !== 'GET') {
                $this->withHeader('Idempotency-Key', (string) Str::uuid());
            }
            $url = '/' . $uri;
            if ($method === 'GET') {
                if ($body !== []) {
                    $url .= '?' . http_build_query($body);
                }
                $response = $this->json('GET', $url);
            } else {
                $response = $this->json($method, $url, $body);
            }
            $base = $response->baseResponse;
            $content = match (true) {
                $base instanceof \Symfony\Component\HttpFoundation\StreamedResponse => (string) $response->streamedContent(),
                $base instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse => (string) file_get_contents($base->getFile()->getPathname()),
                default => (string) $response->getContent(),
            };
            $headers = collect($base->headers->all())->except(['date', 'set-cookie', 'x-request-id'])->map(fn ($values, $name) => $name . ': ' . implode(', ', $values))->implode("\n");
            $this->runAfterCommitCallbacks($level);
            $also = $this->observeAlso !== null ? ($this->observeAlso)() : null;
            $touched = array_keys(array_diff_assoc($this->writeCounters(), $writes));
            $after = array_merge($this->bBaseline, $this->snapshotB($touched));
            ksort($after);
        } finally {
            DB::rollBack($level);
            $cache = (fn () => $this->storage)->call($store);
            (function () use ($saved) {
                $this->storage = $saved;
            })->call($store);
            app()->forgetScopedInstances();
            Carbon::setTestNow(Carbon::parse(self::NOW, 'UTC'));
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
        }

        $side = $this->sideEffects;
        foreach (array_keys(array_diff_key($cache, $saved) + array_diff_key($saved, $cache)) as $key) {
            if ($this->namesB((string) $key)) {
                $side[] = "writes or removes the cache key {$key}, which names B";
            }
        }
        foreach (Http::recorded()->slice($http) as [$request]) {
            $sent = $request->url() . ' ' . $request->body();
            $chat = collect($this->bChats)->first(fn ($chat) => str_contains($sent, '"' . $chat . '"') || str_contains($sent, 'chat_id=' . $chat));
            if (($leak = $this->bLeakIn($sent)) !== null || $chat !== null) {
                $side[] = 'sends HTTP ' . $request->url() . ' about B: ' . ($leak ?? "B's chat {$chat}");
            }
        }

        return [
            'status' => $base->getStatusCode(),
            'request' => $uri . ' ' . json_encode($body),
            'content' => $headers . "\n\n" . $content,
            'json' => json_decode($content, true),
            'after' => $after,
            'side' => $side,
            'also' => $also,
        ];
    }

    protected function startPinSession(array $terminal, User $user, string $pin): string
    {
        $this->givePin($user, $pin);

        return (string) $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], [
            'user_id' => $user->id,
            'pin' => $pin,
        ])->assertOk()->json('data.token');
    }

    /**
     * A request of the side's own staff while the world is built: must succeed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function own(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer($token);
        if ($method !== 'GET') {
            $this->withHeader('Idempotency-Key', (string) Str::uuid());
        }
        $response = $this->json($method, '/api/v1' . $uri, $data);
        if ($response->status() >= 300) {
            throw new RuntimeException("World: {$method} {$uri} gave {$response->status()}: " . Str::limit($response->getContent(), 600));
        }

        return $response;
    }
}
