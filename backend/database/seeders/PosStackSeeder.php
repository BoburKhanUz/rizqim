<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\AutoGuestItem;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CustomerAccount;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\MenuBatch;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoomTariff;
use App\Models\ServiceFeeSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\StaffPinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * P3-33: the data the POS stack end-to-end test (scripts/pos-e2e.sh, e2e/pos/) runs against: one choyxona with fiscal
 * mode on (sandbox driver, TIN, MXIK codes), a branch, two halls (the main hall with ten rooms "Kabinka 1..10", the
 * courtyard with tapchans and tables), room tariffs for weekdays and weekends, minimum spends on some rooms, a service
 * charge per hall, the choy-non guest rules, kitchen stations with their routes, the menu (osh by the kg from a kazan
 * batch, shashlik by the skewer, salads, tea, bread, a dish with modifiers), the staff (owner, manager, cashier, two
 * waiters, a cook) with PINs, four paired terminals (the till, two waiters' phones, the kitchen display), a customer
 * of the Telegram bot and a nasiya account. Deterministic: the same names, prices and PINs on every run; only the
 * generated uuids, tokens and ids differ.
 *
 * Test-only: it refuses to run in any environment but local, testing and e2e, so it can never put known PINs and
 * tokens into a staging or production database. Everything the test needs (ids, PINs, the terminals as
 * `POST /devices/pair` answers them, the customer's and the owner's tokens) is written as JSON to E2E_POS_SEED_FILE (default
 * storage/app/pos-e2e-seed.json).
 *
 *   APP_ENV=e2e php artisan migrate:fresh --force && APP_ENV=e2e php artisan db:seed --class=PosStackSeeder --force
 */
class PosStackSeeder extends Seeder
{
    public const MANAGER_PIN = '5926';
    public const CASHIER_PIN = '8264';
    public const WAITER_1_PIN = '3175';
    public const WAITER_2_PIN = '7539';
    public const COOK_PIN = '4831';

    public const TIN = '301234567';

    private Store $store;

    private Branch $branch;

    public function run(): void
    {
        if (!app()->environment(['local', 'testing', 'e2e'])) {
            throw new RuntimeException('PosStackSeeder is for test databases only (APP_ENV local, testing or e2e).');
        }

        $this->store = Store::forceCreate([
            'name' => 'Rizqim Choyxona',
            'slug' => 'rizqim-choyxona',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::TIN,
            'tax_regime' => 'vat',
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'delivery_enabled' => false,
                'pickup_enabled' => true,
                'delivery_fee' => 0,
            ],
            // Fiscal mode on the sandbox driver; the services that are not menu items need their MXIK codes
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => [
                    'room_fee' => ['mxik' => '10202002001000001'],
                    'place_fee' => ['mxik' => '10202002002000001'],
                    'min_spend_topup' => ['mxik' => '10202002006000001'],
                    'service_charge' => ['mxik' => '10202002005000001'],
                ],
            ],
        ]);

        $this->branch = Branch::create([
            'store_id' => $this->store->id,
            'name' => 'Chilonzor choyxonasi',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_km' => 10,
            'status' => BranchStatus::ACTIVE->value,
            'business_day_starts_at' => '05:00:00',
        ]);

        $owner = $this->staff(UserRole::OWNER, 'Olim Egamov', '+998901000001', null);
        $manager = $this->staff(UserRole::MANAGER, 'Malika Menejer', '+998901000002', self::MANAGER_PIN);
        $cashier = $this->staff(UserRole::CASHIER, 'Kamola Kassir', '+998901000003', self::CASHIER_PIN);
        $waiter1 = $this->staff(UserRole::WAITER, 'Botir Ofitsiant', '+998901000004', self::WAITER_1_PIN);
        $waiter2 = $this->staff(UserRole::WAITER, 'Dilshod Ofitsiant', '+998901000005', self::WAITER_2_PIN);
        $cook = $this->staff(UserRole::COOK, 'Sanjar Oshpaz', '+998901000006', self::COOK_PIN);

        $stations = $this->stations();
        $menu = $this->menu($stations);
        $halls = $this->halls();
        $this->tariffsAndFees($halls);
        $this->guestItems($menu, $halls);

        $customer = User::create([
            'name' => 'Aziz Mijoz',
            'phone' => '+998901000009',
            'password' => bcrypt('pos-e2e-customer-not-used'),
            'telegram_id' => '910000009',
        ]);
        $account = CustomerAccount::create([
            'store_id' => $this->store->id,
            'name' => 'Jamshid aka',
            'phone' => '+998901112233',
            'group_name' => "Jo'ralar guruhi",
            'credit_limit' => '3000000',
        ]);

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach ([
            'cashier' => [DeviceType::POS, 'Kassa'],
            'waiter1' => [DeviceType::POS, 'Ofitsiant 1 telefoni'],
            'waiter2' => [DeviceType::POS, 'Ofitsiant 2 telefoni'],
            'kds' => [DeviceType::KDS, 'Oshxona ekrani'],
        ] as $key => [$type, $name]) {
            $code = $pairing->createCode($this->store, $this->branch, $type, $name, $owner)['code'];
            $paired = $pairing->pair($code, ['platform' => 'linux']);
            // The shape of the POST /devices/pair answer, which is what the apps store (rizqim_api PairedTerminal)
            $terminals[$key] = [
                'token' => $paired['token'],
                'device' => [
                    'uuid' => $paired['device']->uuid,
                    'type' => $type->value,
                    'name' => $name,
                    'platform' => 'linux',
                    'app_version' => null,
                    'store_id' => $this->store->id,
                    'branch_id' => $this->branch->id,
                ],
                'store' => ['id' => $this->store->id, 'name' => $this->store->name],
                'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            ];
        }

        $this->writeSeedFile([
            'store' => ['id' => $this->store->id, 'name' => $this->store->name, 'tin' => self::TIN],
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            'staff' => [
                // The partner web's sign-in (no PIN): the owner settings API, e.g. the booking confirmation's SMS switch
                'owner' => ['id' => $owner->id, 'name' => $owner->name, 'pin' => null, 'token' => $owner->createToken('pos-e2e-owner')->plainTextToken],
                'manager' => ['id' => $manager->id, 'name' => $manager->name, 'pin' => self::MANAGER_PIN],
                'cashier' => ['id' => $cashier->id, 'name' => $cashier->name, 'pin' => self::CASHIER_PIN],
                'waiter1' => ['id' => $waiter1->id, 'name' => $waiter1->name, 'pin' => self::WAITER_1_PIN],
                'waiter2' => ['id' => $waiter2->id, 'name' => $waiter2->name, 'pin' => self::WAITER_2_PIN],
                'cook' => ['id' => $cook->id, 'name' => $cook->name, 'pin' => self::COOK_PIN],
            ],
            'terminals' => $terminals,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                // The Telegram bot's customer, signed in (a token without a device is a `telegram` order channel)
                'token' => $customer->createToken('pos-e2e')->plainTextToken,
            ],
            'account' => ['uuid' => $account->uuid, 'name' => $account->name, 'credit_limit' => '3000000.00'],
        ]);
    }

    /**
     * @return array<string, KitchenStation>
     */
    private function stations(): array
    {
        $stations = [];
        // Tea and bread are served from the samovar: no kitchen display for them (the waiter marks them served)
        foreach ([
            'osh' => ['Osh', 'osh', true],
            'mangal' => ['Mangal', 'mangal', true],
            'kitchen' => ['Oshxona', 'kitchen', true],
            'choy' => ['Choy', 'tea', false],
        ] as $key => [$name, $kind, $kds]) {
            $stations[$key] = KitchenStation::create([
                'store_id' => $this->store->id,
                'branch_id' => $this->branch->id,
                'name' => $name,
                'kind' => $kind,
                'sort_order' => count($stations) + 1,
                'show_on_kds' => $kds,
            ]);
        }

        return $stations;
    }

    /**
     * The menu: name => variant (the product is `$variant->product`).
     *
     * @param  array<string, KitchenStation>  $stations
     * @return array<string, ProductVariant>
     */
    private function menu(array $stations): array
    {
        $categories = [];
        foreach (['Osh' => 'osh', 'Mangal' => 'mangal', 'Salatlar' => 'salatlar', 'Taomlar' => 'taomlar', 'Ichimlik va non' => 'choy-non'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }

        $make = function (string $name, string $category, string $mxik, string $variant, string $price, array $variantAttributes = [], array $productAttributes = []) use ($categories): ProductVariant {
            $product = Product::create($productAttributes + [
                'store_id' => $this->store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create($variantAttributes + [
                'product_id' => $product->id,
                'name' => $variant,
                'price' => $price,
                'stock' => 100000,
                'is_active' => true,
            ]);
        };

        $menu = [
            // By the kilogram from the kazan, in half-kilogram steps
            'Osh' => $make('Osh', 'Osh', '10202001001000001', 'Choyxona oshi', '120000', [
                'unit' => 'kg', 'unit_label' => 'kg (guruch)', 'quantity_step' => '0.5', 'min_quantity' => '0.5',
            ], ['batch_tracked' => true]),
            'Shashlik' => $make('Shashlik', 'Mangal', '10202001007000001', 'Six', '18000'),
            'Achichuk' => $make('Achichuk', 'Salatlar', '10202001008000001', 'Porsiya', '15000'),
            'Svejiy salat' => $make('Svejiy salat', 'Salatlar', '10202001008000002', 'Porsiya', '22000'),
            'Lagmon' => $make('Lagmon', 'Taomlar', '10202001002000001', 'Porsiya', '35000'),
            'Choy' => $make('Choy', 'Ichimlik va non', '10202001005000001', 'Choynak', '5000'),
            'Non' => $make('Non', 'Ichimlik va non', '10202001006000001', 'Dona', '4000'),
        ];

        // Lagmon has modifiers: a size (one, required) and extras (any number)
        $lagmon = $menu['Lagmon']->product;
        $size = ModifierGroup::create(['store_id' => $this->store->id, 'name' => 'Hajm', 'min_select' => 1, 'max_select' => 1, 'is_required' => true]);
        $size->modifiers()->create(['name' => 'Oddiy', 'price_delta' => 0, 'sort_order' => 1]);
        $size->modifiers()->create(['name' => 'Katta', 'price_delta' => 10000, 'sort_order' => 2]);
        $extras = ModifierGroup::create(['store_id' => $this->store->id, 'name' => "Qo'shimcha", 'min_select' => 0]);
        $extras->modifiers()->create(['name' => 'Achchiq sous', 'price_delta' => 3000, 'max_quantity' => 3, 'sort_order' => 1]);
        $extras->modifiers()->create(['name' => "Qo'shimcha go'sht", 'price_delta' => 15000, 'sort_order' => 2]);
        $lagmon->modifierGroups()->attach($size->id, ['sort_order' => 1]);
        $lagmon->modifierGroups()->attach($extras->id, ['sort_order' => 2]);

        // One kazan of 40 kg
        MenuBatch::create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'product_id' => $menu['Osh']->product_id,
            'label' => '1-qozon',
            'quantity_produced' => '40',
            'quantity_left' => '40',
            'started_at' => now(),
        ]);

        // Routes: osh and shashlik by product, the salads and lagmon by category, tea and bread by category
        foreach ([
            ['station' => 'osh', 'product_id' => $menu['Osh']->product_id],
            ['station' => 'mangal', 'product_id' => $menu['Shashlik']->product_id],
            ['station' => 'kitchen', 'category_id' => $categories['Salatlar']->id],
            ['station' => 'kitchen', 'category_id' => $categories['Taomlar']->id],
            ['station' => 'choy', 'category_id' => $categories['Ichimlik va non']->id],
        ] as $route) {
            KitchenStationRoute::create([
                'store_id' => $this->store->id,
                'branch_id' => $this->branch->id,
                'station_id' => $stations[$route['station']]->id,
                'category_id' => $route['category_id'] ?? null,
                'product_id' => $route['product_id'] ?? null,
            ]);
        }

        // The one-tap buttons of the till and the waiter's phone
        Product::whereIn('id', [$menu['Choy']->product_id, $menu['Non']->product_id])->update(['quick_add' => true]);

        return $menu;
    }

    /**
     * @return array{main: Hall, yard: Hall, rooms: array<int, DiningTable>, tapchans: array<int, DiningTable>, tables: array<int, DiningTable>}
     */
    private function halls(): array
    {
        $main = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $yard = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Hovli', 'sort_order' => 2]);

        $place = function (Hall $hall, string $name, string $kind, int $seats, int $position, int $x, int $y, array $extra = []): DiningTable {
            return DiningTable::create($extra + [
                'store_id' => $this->store->id,
                'branch_id' => $this->branch->id,
                'hall_id' => $hall->id,
                'name' => $name,
                'kind' => $kind,
                'seats' => $seats,
                'sort_order' => $position,
                'layout_x' => $x,
                'layout_y' => $y,
                'layout_w' => 4,
                'layout_h' => 3,
            ]);
        };

        $rooms = [];
        foreach (range(1, 10) as $number) {
            $extra = match ($number) {
                // A set minimum spend on two rooms: per room, and per guest
                3 => ['min_spend' => '500000', 'min_spend_mode' => 'per_table'],
                7 => ['min_spend' => '60000', 'min_spend_mode' => 'per_person'],
                default => [],
            };
            $rooms[$number] = $place($main, "Kabinka {$number}", 'room', $number === 10 ? 20 : 10, $number, (($number - 1) % 5) * 5, intdiv($number - 1, 5) * 4, $extra);
        }
        $tapchans = [];
        foreach (range(1, 4) as $number) {
            $tapchans[$number] = $place($yard, "Tapchan {$number}", 'tapchan', 8, $number, (($number - 1) % 4) * 5, 0);
        }
        $tables = [];
        foreach (range(1, 6) as $number) {
            $tables[$number] = $place($yard, "Stol {$number}", 'table', 4, 10 + $number, (($number - 1) % 6) * 4, 5);
        }

        return ['main' => $main, 'yard' => $yard, 'rooms' => $rooms, 'tapchans' => $tapchans, 'tables' => $tables];
    }

    /**
     * @param  array{main: Hall, yard: Hall, rooms: array<int, DiningTable>, tapchans: array<int, DiningTable>, tables: array<int, DiningTable>}  $halls
     */
    private function tariffsAndFees(array $halls): void
    {
        $tariff = fn (array $subject, string $dayType, string $price) => RoomTariff::create($subject + [
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'day_type' => $dayType,
            'price' => $price,
        ]);
        // Rooms 300 000 on weekdays and 400 000 on weekends; the VIP room (Kabinka 10) has its own; tapchans cost less
        $tariff(['table_kind' => 'room'], 'weekday', '300000');
        $tariff(['table_kind' => 'room'], 'weekend', '400000');
        $tariff(['dining_table_id' => $halls['rooms'][10]->id], 'weekday', '500000');
        $tariff(['dining_table_id' => $halls['rooms'][10]->id], 'weekend', '600000');
        $tariff(['table_kind' => 'tapchan'], 'weekday', '100000');
        $tariff(['table_kind' => 'tapchan'], 'weekend', '150000');

        // Service charge: 10% in the main hall; 5% and a 5 000 "joy narxi" for every guest in the courtyard
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $halls['main']->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_person']);
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $halls['yard']->id, 'service_charge_percent' => '5', 'place_fee_amount' => '5000', 'place_fee_mode' => 'per_person']);
    }

    /**
     * Choy and non for every guest: half a teapot and a bread in the halls (the courtyard's tea is one per guest).
     *
     * @param  array<string, ProductVariant>  $menu
     * @param  array{main: Hall, yard: Hall, rooms: array<int, DiningTable>, tapchans: array<int, DiningTable>, tables: array<int, DiningTable>}  $halls
     */
    private function guestItems(array $menu, array $halls): void
    {
        $rule = fn (ProductVariant $variant, string $perGuest, ?Hall $hall) => AutoGuestItem::create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'hall_id' => $hall?->id,
            'product_variant_id' => $variant->id,
            'quantity_per_guest' => $perGuest,
        ]);
        $rule($menu['Choy'], '0.5', null);
        $rule($menu['Non'], '1', null);
        $rule($menu['Choy'], '1', $halls['yard']);
    }

    private function staff(UserRole $role, string $name, string $phone, ?string $pin): User
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => bcrypt('pos-e2e-staff-not-used'),
            'store_id' => $this->store->id,
            // The owner works in every branch of the store; the others in this one
            'branch_id' => $role === UserRole::OWNER ? null : $this->branch->id,
            'role' => $role->value,
        ]);
        if ($pin !== null) {
            app(StaffPinService::class)->setPin($user, $pin, null);
        }

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeSeedFile(array $data): void
    {
        // getenv(), not env(): this is a script's own switch, not application configuration
        $path = (string) (getenv('E2E_POS_SEED_FILE') ?: storage_path('app/pos-e2e-seed.json'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
