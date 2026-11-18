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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Feature\AccessFixtures;

/**
 * M4 stage 1 (docs/scale/M4_DESIGN.md 3.1): two complete stores, A and B, built the same way from the existing fixtures
 * and, where a record only exists at the end of a flow (a paid bill, a refund, a deposit), through the store's own API
 * as its own staff. Every name and label of B starts with `ZZB-` so a response that shows B's data is found by text.
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

    public const MARK = 'ZZB-';

    /** @var array{A: array<string, mixed>, B: array<string, mixed>} */
    protected array $world = ['A' => [], 'B' => []];

    protected Category $sharedCategory;

    protected function buildTenancyWorld(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW, 'UTC'));
        $this->sharedCategory = Category::create(['name' => 'Taomlar', 'slug' => 'taomlar-' . Str::random(5), 'is_active' => true]);
        $this->customer = $this->makeCustomer(0, ['name' => 'Mijoz']);
        foreach (['A' => 'A-', 'B' => self::MARK] as $side => $prefix) {
            $this->buildSide($side, $prefix);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function side(string $side): array
    {
        return $this->world[$side];
    }

    private function buildSide(string $side, string $p): void
    {
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
        $w['variant'] = $variant($w['product'], 'Six', '18000');
        $w['tea_product'] = $product('Choy');
        $w['tea'] = $variant($w['tea_product'], 'Choynak', '5000');
        $w['plov_product'] = $product('Osh', ['batch_tracked' => true]);
        $w['plov'] = $variant($w['plov_product'], 'Osh kg', '120000', ['unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '0.5']);
        $w['meat_product'] = $product('Baliq');
        $w['meat'] = $variant($w['meat_product'], 'Baliq kg', '90000', ['unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.1']);
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
        $w['shift'] = PosShift::where('uuid', $this->own($c, 'POST', '/pos/shifts', ['opening_cash' => '100000'])->json('data.uuid'))->firstOrFail();

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
            ['tender' => 'cash', 'amount' => (string) $w['paid_bill']->refresh()->total, 'tendered_amount' => '50000', 'tip_amount' => '2000'],
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
            'total_price' => 18000,
            'commission_amount' => 1800,
            'notes' => $p . 'Izoh',
        ]);
        OrderItem::create(['order_id' => $w['online_order']->id, 'product_variant_id' => $w['variant']->id, 'product_name' => $p . 'Shashlik', 'quantity' => 1, 'unit' => 'piece', 'unit_price' => 18000, 'total_price' => 18000]);
        OrderStatusHistory::create(['order_id' => $w['online_order']->id, 'status' => OrderStatus::NEW->value, 'notes' => 'created']);

        // A marketplace delivery ready for a courier
        $w['delivery_order'] = Order::create([
            'store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'user_id' => $this->customer->id, 'status' => OrderStatus::READY,
            'order_type' => 'delivery', 'payment_method' => 'cash', 'total_price' => 18000, 'commission_amount' => 1800,
            'shipping_address' => ['address' => $p . 'Manzil', 'latitude' => 41.311081, 'longitude' => 69.240562],
        ]);

        $this->buildFrontOfHouse($w, $p);

        // An offline operation the server could not apply (a round on an order it never saw): a sync error to resolve
        $op = (string) Str::uuid();
        $this->own($w['till_token'], 'POST', '/pos/sync', ['operations' => [[
            'op_id' => $op, 'seq' => 1, 'type' => 'order.round', 'staff_id' => $w['cashier']->id, 'occurred_at' => now()->subMinutes(5)->toJSON(),
            'payload' => ['order' => (string) Str::uuid(), 'items' => [['line_id' => (string) Str::uuid(), 'variant_id' => $w['tea']->uuid, 'quantity' => '1', 'unit_price' => '5000']]],
        ]]]);
        $w['sync_error'] = $op;

        $this->buildBackOffice($w, $p);

        // The owner's phone (partner app): a user device with a push token
        $phone = $this->own($o = $w['owner_token'], 'POST', '/devices', ['type' => 'partner_mobile', 'name' => $p . 'Telefon', 'platform' => 'android'])->json('data');
        $w['phone'] = \App\Models\Device::where('uuid', $phone['uuid'] ?? $phone['device']['uuid'])->firstOrFail();

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
        ProductVariant::create(['product_id' => $w['combo_product']->id, 'name' => $p . 'Kombo', 'price' => '30000', 'stock' => 100, 'is_active' => true]);
        $this->own($o, 'POST', '/owner/combos', ['product_id' => $w['combo_product']->id, 'slots' => [['name' => $p . 'Ichimlik', 'options' => [['variant_id' => $w['tea']->id, 'is_default' => true]]]]]);
        $w['combo'] = $latest(\App\Models\Combo::class);
        $w['set_product'] = Product::create(['store_id' => $store->id, 'category_id' => $this->sharedCategory->id, 'name' => $p . 'Kompleks', 'slug' => Str::slug($p . 'Kompleks') . '-' . Str::random(5), 'status' => 'active']);
        ProductVariant::create(['product_id' => $w['set_product']->id, 'name' => $p . 'Kompleks', 'price' => '25000', 'stock' => 100, 'is_active' => true]);
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
        $this->own($o, 'POST', '/owner/room-tariffs', ['branch_id' => $branch, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => '300000']);
        $w['room_tariff'] = $latest(\App\Models\RoomTariff::class);
        $this->own($o, 'POST', '/owner/auto-guest-items', ['branch_id' => $branch, 'product_variant_id' => $w['tea']->id, 'quantity_per_guest' => '0.5']);
        $w['auto_guest_item'] = $latest(\App\Models\AutoGuestItem::class);

        $this->own($o, 'POST', '/owner/staff-pay/rules', ['branch_id' => $branch, 'role' => 'waiter', 'effective_from' => '2026-06-01', 'components' => [['type' => 'shift_rate', 'amount' => '100000']]]);
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
        $deposit = $this->own($c, 'POST', "/pos/bookings/{$booking}/deposits", ['amount' => '50000', 'tender' => 'cash'])->json('data.payment.uuid');
        $w['deposit'] = BookingPayment::where('uuid', $deposit)->firstOrFail();

        $w['banquet_hall'] = Hall::create(['store_id' => $w['store']->id, 'branch_id' => $w['branch']->id, 'name' => $p . 'Banket zal', 'sort_order' => 2]);
        BanquetHallRate::create(['store_id' => $w['store']->id, 'hall_id' => $w['banquet_hall']->id, 'slot' => 'evening', 'price' => '5000000']);
        $w['banquet_package'] = BanquetPackage::create(['store_id' => $w['store']->id, 'name' => $p . 'Standart', 'per_person_price' => '150000', 'minimum_guests' => 50, 'included' => ['Osh']]);
        $w['banquet_extra'] = BanquetExtra::create(['store_id' => $w['store']->id, 'name' => $p . 'Tort', 'price' => '800000', 'pricing' => 'per_event']);
        $banquet = $this->own($o, 'POST', '/pos/banquets', [
            'branch_id' => $w['branch']->id, 'event_date' => '2026-08-20', 'slot' => 'evening', 'hall_ids' => [$w['banquet_hall']->id],
            'event_type' => 'toy', 'customer_name' => $p . 'Karimovlar', 'customer_phone' => '90 123 45 67', 'guests_guaranteed' => 100,
            'banquet_package_id' => $w['banquet_package']->id,
        ])->json('data.uuid');
        $w['banquet'] = BanquetEvent::where('uuid', $banquet)->firstOrFail();
        $rows = $this->own($o, 'PUT', "/pos/banquets/{$banquet}/payments", ['rows' => [
            ['kind' => 'deposit', 'due_date' => '2026-06-15', 'amount' => '5000000'],
            ['kind' => 'final', 'due_date' => '2026-08-19', 'amount' => '15000000'],
        ]]);
        $w['banquet_row'] = BanquetPayment::where('banquet_event_id', $w['banquet']->id)->orderBy('sort_order')->orderBy('id')->firstOrFail();
        $w['banquet_row2'] = BanquetPayment::where('banquet_event_id', $w['banquet']->id)->orderByDesc('sort_order')->orderByDesc('id')->firstOrFail();
        $this->own($o, 'POST', "/pos/banquets/{$banquet}/payments/{$w['banquet_row2']->uuid}/bank-transfer", ['amount' => '1000000', 'reference' => $p . 'PP-1']);
        $w['banquet_entry'] = BanquetPaymentEntry::where('banquet_event_id', $w['banquet']->id)->orderBy('id')->firstOrFail();

        $account = $this->own($o, 'POST', '/pos/customer-accounts', ['name' => $p . 'Nasiya', 'phone' => '+998901234500', 'credit_limit' => '1000000'])->json('data.uuid');
        $w['account'] = CustomerAccount::where('uuid', $account)->firstOrFail();
        $corporate = $this->own($o, 'POST', '/pos/customer-accounts', [
            'name' => $p . 'Korxona', 'kind' => 'corporate', 'company_name' => $p . 'MChJ', 'credit_limit' => '5000000', 'talon_mode' => 'daily_limit',
        ])->json('data.uuid');
        $w['corporate'] = CustomerAccount::where('uuid', $corporate)->firstOrFail();
        $member = $this->own($o, 'POST', "/pos/customer-accounts/{$corporate}/members", ['name' => $p . 'Xodim', 'phone' => '+998901234501', 'daily_limit' => '50000'])->json('data.uuid');
        $w['member'] = CustomerAccountMember::where('uuid', $member)->firstOrFail();

        $voucher = $this->own($c, 'POST', '/pos/vouchers/sell', ['face_value' => '100000', 'tender' => 'cash', 'buyer_name' => $p . 'Xaridor'])->json('data');
        $w['voucher'] = Voucher::where('uuid', $voucher['voucher']['uuid'] ?? $voucher['uuid'])->firstOrFail();
        $w['voucher_code'] = $voucher['code'] ?? $voucher['voucher']['code'];

        $entry = $this->own($w['manager_token'], 'POST', '/pos/waitlist', ['guest_name' => $p . 'Navbatchi', 'party_size' => 3, 'guest_phone' => '+998901234502'])->json('data.uuid');
        $w['waitlist'] = PosWaitlistEntry::where('uuid', $entry)->firstOrFail();

        config(['qr_payments.sandbox' => false, 'qr_payments.simulate' => true, 'qr_payments.simulate_drivers' => ['click']]);
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
    // Observing B: per table, a hash of B's rows (and of every row that points at one of B's rows), and B's uuids
    // ---------------------------------------------------------------------------------------------------------------

    /** @var array<string, string> table => WHERE clause selecting B's rows and rows that reference them */
    private array $bFilters = [];

    /** @var list<string> */
    private array $bUuids = [];

    /** @var array<string, string> B as the world left it (every call is undone, so every call starts from it) */
    private array $bBaseline = [];

    /** @var array<string, string> partition => its partitioned table */
    private array $partitionParents = [];

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
     * Works out, from the schema (store_id, branch_id, single-column foreign keys up to three levels), which rows of
     * every table are B's or point at B's, and freezes B's row ids at this moment into the filters: a row created later
     * that points at a B row is still caught.
     */
    protected function prepareObservation(): void
    {
        $storeId = (int) $this->world['B']['store']->id;
        $tables = collect(DB::select("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p') AND NOT c.relispartition ORDER BY c.relname"))->pluck('relname')->all();
        $columns = [];
        foreach (DB::select("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = current_schema()") as $row) {
            $columns[$row->table_name][$row->column_name] = $row->data_type;
        }
        $foreign = [];
        foreach (DB::select("SELECT tc.relname AS child, a.attname AS col, pc.relname AS parent
            FROM pg_constraint c
            JOIN pg_class tc ON tc.oid = c.conrelid JOIN pg_class pc ON pc.oid = c.confrelid
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            JOIN pg_attribute pa ON pa.attrelid = c.confrelid AND pa.attnum = c.confkey[1]
            WHERE c.contype = 'f' AND array_length(c.conkey, 1) = 1 AND pa.attname = 'id' AND NOT tc.relispartition") as $row) {
            $foreign[$row->child][$row->col] = $row->parent;
        }

        $branches = DB::table('branches')->where('store_id', $storeId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $direct = [];
        foreach ($tables as $table) {
            $parts = [];
            if (isset($columns[$table]['store_id'])) {
                $parts[] = "store_id = {$storeId}";
            }
            if (isset($columns[$table]['branch_id']) && $branches !== []) {
                $parts[] = 'branch_id IN (' . implode(',', $branches) . ')';
            }
            $direct[$table] = $parts;
        }

        $ids = [];
        $filters = [];
        for ($round = 0; $round < 4; $round++) {
            foreach ($tables as $table) {
                $parts = $direct[$table];
                foreach ($foreign[$table] ?? [] as $column => $parent) {
                    if (($ids[$parent] ?? []) !== []) {
                        $parts[] = "{$column} IN (" . implode(',', $ids[$parent]) . ')';
                    }
                }
                if ($parts === []) {
                    continue;
                }
                $filters[$table] = '(' . implode(' OR ', $parts) . ')';
                if (isset($columns[$table]['id'])) {
                    $ids[$table] = DB::table($table)->whereRaw($filters[$table])->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
                }
            }
        }
        $this->bFilters = $filters;

        $uuids = [];
        foreach ($filters as $table => $where) {
            foreach ($columns[$table] as $column => $type) {
                if ($type === 'uuid') {
                    foreach (DB::table($table)->whereRaw($where)->whereNotNull($column)->pluck($column) as $uuid) {
                        $uuids[(string) $uuid] = true;
                    }
                }
            }
        }
        $this->bUuids = array_keys($uuids);
        $this->partitionParents = collect(DB::select('SELECT c.relname AS leaf, p.relname AS parent FROM pg_inherits i
            JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent'))->pluck('parent', 'leaf')->all();
        $this->bBaseline = $this->snapshotB();
        if (count($this->bUuids) < 20 || !isset($filters['orders'], $filters['order_items'])) {
            throw new RuntimeException('World: B is too small to observe (' . count($this->bUuids) . ' uuids)');
        }
    }

    /**
     * @param  list<string>|null  $only  just these tables (the others are left out)
     * @return array<string, string> table => md5 of B's rows (and of the rows pointing at them)
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
     * What of B a response body shows: the marker (any case: slugs are lower case) or one of B's uuids.
     */
    protected function bLeakIn(string $content, string $request = ''): ?string
    {
        if (($at = stripos($content, self::MARK)) !== false && stripos($request, self::MARK) === false) {
            return 'shows "' . substr($content, max(0, $at - 40), 100) . '"';
        }
        foreach ($this->bUuids as $uuid) {
            // A uuid the request itself sent (echoed in a "not found") shows nothing the caller did not know
            if (stripos($content, $uuid) !== false && stripos($request, $uuid) === false) {
                return "shows B's uuid {$uuid}";
            }
        }

        return null;
    }

    /**
     * One request, undone afterwards: the database to a savepoint, the array cache (rate limiters, idempotency
     * replays, cached reads) to what it held, the clock to NOW. $observe: B's snapshot taken before the undo.
     *
     * @param  array<string, mixed>  $body  the JSON body, or the query string of a GET
     * @return array{status: int, content: string, json: mixed, after: array<string, string>|null}
     */
    protected function isolatedCall(string $method, string $uri, ?string $token, array $body, bool $observe): array
    {
        $store = app('cache')->store()->getStore();
        if (!$store instanceof \Illuminate\Cache\ArrayStore) {
            throw new RuntimeException('The tenancy suite needs CACHE_STORE=array (phpunit.xml), got ' . get_class($store));
        }
        $saved = (fn () => $this->storage)->call($store);
        $level = DB::transactionLevel();
        $writes = $observe ? $this->writeCounters() : [];
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
            $after = null;
            if ($observe) {
                $touched = array_keys(array_diff_assoc($this->writeCounters(), $writes));
                $after = array_merge($this->bBaseline, $this->snapshotB($touched));
                ksort($after);
            }
        } finally {
            DB::rollBack($level);
            (function () use ($saved) {
                $this->storage = $saved;
            })->call($store);
            app()->forgetScopedInstances();
            Carbon::setTestNow(Carbon::parse(self::NOW, 'UTC'));
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
        }

        return ['status' => $response->baseResponse->getStatusCode(), 'request' => $uri . ' ' . json_encode($body), 'content' => $content, 'json' => json_decode($content, true), 'after' => $after];
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
