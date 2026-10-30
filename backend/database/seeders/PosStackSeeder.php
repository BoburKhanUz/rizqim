<?php

namespace Database\Seeders;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\StaffPay\StaffPayRules;
use App\Enums\BranchStatus;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\AutoGuestItem;
use App\Models\Branch;
use App\Models\BranchBookingRules;
use App\Models\BranchProductRotation;
use App\Models\Category;
use App\Models\CustomerAccount;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\MenuBatch;
use App\Models\ModifierGroup;
use App\Models\PosTipSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoomTariff;
use App\Models\ServiceFeeSetting;
use App\Models\StaffPaySetting;
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
 * P6-19: a second store, an osh markazi ("Bodomzor osh markazi", e2e/pos/test/osh_markazi_lunch_test.dart): fiscal on the
 * sandbox driver, a branch the test switches to the `osh_markazi` profile over the owner API, two stations (osh, kitchen,
 * both on the kitchen display), a counter menu (to'y oshi by the portion from kazans the cook opens, achichuk, non, choy
 * and two daily dishes), its owner, manager, two cashiers and a cook with PINs, and four paired devices: two tills, the
 * kitchen display and a customer display (the "ready numbers" board). In the seed file under `osh`.
 *
 * P6-29: a third store, a restaurant ("Rizqim Restoran", e2e/pos/test/friday_restaurant_test.dart): fiscal on the
 * sandbox driver, a branch on the `restoran` profile (skewer buttons and weight-later switched on over it) with 20
 * tables in two halls, a kitchen of four stations (cold, hot and grill on the kitchen display, the bar without one), a
 * restaurant menu (salads, hot dishes, desserts, skewers with ×2…×10 buttons, a fish weighed after it is chosen,
 * drinks), the service charge rules (12% in the hall, 15% from 8 guests), the tip pool split by role weights (waiter 2,
 * cook 1), table booking rules (a deposit of 50 000 per guest from 6 guests, needed to confirm), the staff pay rules
 * (daily period) and its staff with PINs: owner, manager, cashier, four waiters, a hostess and a cook; seven paired
 * devices (the till, four waiters' phones, the hostess tablet, the kitchen display). In the seed file under `restoran`.
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

    /** P6-19: the osh markazi of the "tushlik" end-to-end test (its own store, so the choyxona's files never meet it) */
    public const OSH_TIN = '301234568';

    public const OSH_MANAGER_PIN = '6142';

    public const OSH_CASHIER_1_PIN = '2718';

    public const OSH_CASHIER_2_PIN = '3159';

    public const OSH_COOK_PIN = '1648';

    /** P6-29: the restaurant of the "juma kechasi" end-to-end test (its own store) */
    public const RESTORAN_TIN = '301234569';

    public const RESTORAN_PINS = [
        'manager' => '6271',
        'cashier' => '7182',
        'waiter1' => '1539',
        'waiter2' => '2864',
        'waiter3' => '3579',
        'waiter4' => '4680',
        'hostess' => '5791',
        'cook' => '6802',
    ];

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
            'osh' => $this->oshMarkazi(),
            'restoran' => $this->restaurant(),
        ]);
    }

    /**
     * P6-19: the osh markazi store of the lunch-rush test, with its own staff, menu and devices. The test sets its
     * format profile, daily menu, kazans, plans and the bank terminal mode itself (over the APIs the owner and the
     * cook use).
     *
     * @return array<string, mixed> the seed file's `osh` section (the same shape as the top level)
     */
    private function oshMarkazi(): array
    {
        $store = Store::forceCreate([
            'name' => 'Bodomzor osh markazi',
            'slug' => 'rizqim-osh-markazi',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::OSH_TIN,
            'tax_regime' => 'vat',
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => false, 'pickup_enabled' => true, 'delivery_fee' => 0],
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox'],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Bodomzor',
            'latitude' => 41.338,
            'longitude' => 69.285,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
            'business_day_starts_at' => '05:00:00',
        ]);

        $person = function (UserRole $role, string $name, string $phone, ?string $pin) use ($store, $branch): User {
            $user = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => bcrypt('pos-e2e-staff-not-used'),
                'store_id' => $store->id,
                'branch_id' => $role === UserRole::OWNER ? null : $branch->id,
                'role' => $role->value,
            ]);
            if ($pin !== null) {
                app(StaffPinService::class)->setPin($user, $pin, null);
            }

            return $user->refresh();
        };
        $owner = $person(UserRole::OWNER, 'Rustam Oshpazov', '+998901000021', null);
        $manager = $person(UserRole::MANAGER, 'Nodira Menejer', '+998901000022', self::OSH_MANAGER_PIN);
        $cashier1 = $person(UserRole::CASHIER, 'Gulnora Kassir', '+998901000023', self::OSH_CASHIER_1_PIN);
        $cashier2 = $person(UserRole::CASHIER, 'Sardor Kassir', '+998901000024', self::OSH_CASHIER_2_PIN);
        $cook = $person(UserRole::COOK, 'Bahodir Oshpaz', '+998901000025', self::OSH_COOK_PIN);

        // Both stations are on the kitchen display: every line of a counter order is bumped there (pay first, then kitchen)
        $stations = [];
        foreach (['osh' => ['Qozon', 'osh'], 'kitchen' => ['Oshxona', 'kitchen']] as $key => [$name, $kind]) {
            $stations[$key] = KitchenStation::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => $name,
                'kind' => $kind,
                'sort_order' => count($stations) + 1,
                'show_on_kds' => true,
            ]);
        }

        $categories = [];
        foreach (['Osh' => 'om-osh', 'Salatlar' => 'om-salatlar', 'Suyuq taomlar' => 'om-suyuq', 'Non va choy' => 'om-non-choy'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }
        $make = function (string $name, string $category, string $mxik, string $price, array $productAttributes = []) use ($store, $categories): ProductVariant {
            $product = Product::create($productAttributes + [
                'store_id' => $store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-om-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create(['product_id' => $product->id, 'name' => 'Porsiya', 'price' => $price, 'stock' => 100000, 'is_active' => true]);
        };
        $menu = [
            // By the portion from the kazan (the cook opens each kazan with its portion count)
            "To'y oshi" => $make("To'y oshi", 'Osh', '10202001001000002', '45000', ['batch_tracked' => true]),
            'Achichuk' => $make('Achichuk', 'Salatlar', '10202001008000003', '12000'),
            // Daily dishes: sold only on a day whose "Bugungi menyu" has them
            'Mastava' => $make('Mastava', 'Suyuq taomlar', '10202001003000001', '28000'),
            "Sho'rva" => $make("Sho'rva", 'Suyuq taomlar', '10202001003000002', '32000'),
            'Non' => $make('Non', 'Non va choy', '10202001006000002', '5000', ['quick_add' => true]),
            'Choy' => $make('Choy', 'Non va choy', '10202001005000002', '4000', ['quick_add' => true]),
        ];
        // The rotation is per branch (P6-15)
        foreach (['Mastava', "Sho'rva"] as $dish) {
            BranchProductRotation::create(['branch_id' => $branch->id, 'product_id' => $menu[$dish]->product_id]);
        }
        foreach ([
            ['station' => 'osh', 'product_id' => $menu["To'y oshi"]->product_id],
            ['station' => 'kitchen', 'category_id' => $categories['Salatlar']->id],
            ['station' => 'kitchen', 'category_id' => $categories['Suyuq taomlar']->id],
            ['station' => 'kitchen', 'category_id' => $categories['Non va choy']->id],
        ] as $route) {
            KitchenStationRoute::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'station_id' => $stations[$route['station']]->id,
                'category_id' => $route['category_id'] ?? null,
                'product_id' => $route['product_id'] ?? null,
            ]);
        }

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach ([
            'till1' => [DeviceType::POS, 'Kassa 1'],
            'till2' => [DeviceType::POS, 'Kassa 2'],
            'kds' => [DeviceType::KDS, 'Oshxona ekrani'],
            // The "Tayyorlanmoqda / Tayyor" board on the wall (a free device, no staff)
            'display' => [DeviceType::DISPLAY, 'Raqamlar ekrani'],
        ] as $key => [$type, $name]) {
            $code = $pairing->createCode($store, $branch, $type, $name, $owner)['code'];
            $paired = $pairing->pair($code, ['platform' => 'linux']);
            $terminals[$key] = [
                'token' => $paired['token'],
                'device' => [
                    'uuid' => $paired['device']->uuid,
                    'type' => $type->value,
                    'name' => $name,
                    'platform' => 'linux',
                    'app_version' => null,
                    'store_id' => $store->id,
                    'branch_id' => $branch->id,
                ],
                'store' => ['id' => $store->id, 'name' => $store->name],
                'branch' => ['id' => $branch->id, 'name' => $branch->name],
            ];
        }

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::OSH_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => [
                'owner' => ['id' => $owner->id, 'name' => $owner->name, 'pin' => null, 'token' => $owner->createToken('pos-e2e-osh-owner')->plainTextToken],
                'manager' => ['id' => $manager->id, 'name' => $manager->name, 'pin' => self::OSH_MANAGER_PIN],
                'cashier1' => ['id' => $cashier1->id, 'name' => $cashier1->name, 'pin' => self::OSH_CASHIER_1_PIN],
                'cashier2' => ['id' => $cashier2->id, 'name' => $cashier2->name, 'pin' => self::OSH_CASHIER_2_PIN],
                'cook' => ['id' => $cook->id, 'name' => $cook->name, 'pin' => self::OSH_COOK_PIN],
            ],
            'terminals' => $terminals,
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
        ];
    }

    /**
     * P6-29: the restaurant of the Friday-evening test, with its own staff, menu, rules and devices.
     *
     * @return array<string, mixed> the seed file's `restoran` section (the same shape as the top level)
     */
    private function restaurant(): array
    {
        $store = Store::forceCreate([
            'name' => 'Rizqim Restoran',
            'slug' => 'rizqim-restoran',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::RESTORAN_TIN,
            'tax_regime' => 'vat',
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => false, 'pickup_enabled' => true, 'delivery_fee' => 0],
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => [
                    'place_fee' => ['mxik' => '10202002002000002'],
                    'service_charge' => ['mxik' => '10202002005000002'],
                ],
            ],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Yunusobod restorani',
            'latitude' => 41.366,
            'longitude' => 69.288,
            'radius_km' => 8,
            'status' => BranchStatus::ACTIVE->value,
            'business_day_starts_at' => '05:00:00',
        ]);
        // The restaurant profile (courses, tips, staff pay, guest split, waiter transfer, hostess, table booking,
        // service charge rules), with the grill's skewer buttons and the fish weighed after it is chosen
        $branch->forceFill(['pos_settings' => PosSettings::withFormat(null, FormatProfile::RESTORAN, ['skewer_buttons' => true, 'weight_later' => true])])->save();

        $person = function (UserRole $role, string $name, string $phone, ?string $pin) use ($store, $branch): User {
            $user = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => bcrypt('pos-e2e-staff-not-used'),
                'store_id' => $store->id,
                'branch_id' => $role === UserRole::OWNER ? null : $branch->id,
                'role' => $role->value,
            ]);
            if ($pin !== null) {
                app(StaffPinService::class)->setPin($user, $pin, null);
            }

            return $user->refresh();
        };
        $pins = self::RESTORAN_PINS;
        $staff = [
            'owner' => $person(UserRole::OWNER, 'Shavkat Restoranov', '+998901000041', null),
            'manager' => $person(UserRole::MANAGER, 'Feruza Menejer', '+998901000042', $pins['manager']),
            'cashier' => $person(UserRole::CASHIER, 'Laylo Kassir', '+998901000043', $pins['cashier']),
            'waiter1' => $person(UserRole::WAITER, 'Akmal Ofitsiant', '+998901000044', $pins['waiter1']),
            'waiter2' => $person(UserRole::WAITER, 'Bekzod Ofitsiant', '+998901000045', $pins['waiter2']),
            'waiter3' => $person(UserRole::WAITER, 'Jasur Ofitsiant', '+998901000046', $pins['waiter3']),
            'waiter4' => $person(UserRole::WAITER, 'Otabek Ofitsiant', '+998901000047', $pins['waiter4']),
            'hostess' => $person(UserRole::HOSTESS, 'Madina Hostes', '+998901000048', $pins['hostess']),
            'cook' => $person(UserRole::COOK, 'Rustam Oshpaz', '+998901000049', $pins['cook']),
        ];

        // Cold, hot and grill cook from the kitchen display; the bar pours without one (the waiter serves at once)
        $stations = [];
        foreach ([
            'cold' => ['Sovuq sex', 'salad_bar', true],
            'hot' => ['Issiq sex', 'kitchen', true],
            'grill' => ['Mangal', 'mangal', true],
            'bar' => ['Bar', 'other', false],
        ] as $key => [$name, $kind, $kds]) {
            $stations[$key] = KitchenStation::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => $name,
                'kind' => $kind,
                'sort_order' => count($stations) + 1,
                'show_on_kds' => $kds,
            ]);
        }

        $categories = [];
        foreach (['Salatlar' => 'rs-salatlar', 'Issiq taomlar' => 'rs-issiq', 'Mangal' => 'rs-mangal', 'Desertlar' => 'rs-desert', 'Ichimliklar' => 'rs-ichimlik'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }
        $make = function (string $name, string $category, string $mxik, string $variant, string $price, array $variantAttributes = [], array $productAttributes = []) use ($store, $categories): ProductVariant {
            $product = Product::create($productAttributes + [
                'store_id' => $store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-rs-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create($variantAttributes + ['product_id' => $product->id, 'name' => $variant, 'price' => $price, 'stock' => 100000, 'is_active' => true]);
        };
        $menu = [
            'Sezar salati' => $make('Sezar salati', 'Salatlar', '10202001008000011', 'Porsiya', '68000'),
            'Achchiq-chuchuk' => $make('Achchiq-chuchuk', 'Salatlar', '10202001008000012', 'Porsiya', '24000'),
            "Mol go'shti steyk" => $make("Mol go'shti steyk", 'Issiq taomlar', '10202001002000011', 'Porsiya', '165000'),
            "Lag'mon" => $make("Lag'mon", 'Issiq taomlar', '10202001002000012', 'Porsiya', '48000'),
            // The grill: a skewer with the ×2…×10 buttons and its waiting time; a fish by the kg, weighed raw after it is chosen
            "Qo'y kabob" => $make("Qo'y kabob", 'Mangal', '10202001007000011', 'Six', '26000', [], ['quick_multipliers' => [2, 3, 5, 10], 'wait_minutes' => 15]),
            'Sazan' => $make('Sazan', 'Mangal', '10202001007000012', 'Mangalda', '240000', [
                'unit' => 'kg', 'unit_label' => 'kg', 'quantity_step' => '0.05', 'min_quantity' => '0.5',
            ], ['weight_mode' => 'raw', 'wait_minutes' => 25]),
            'Medovik' => $make('Medovik', 'Desertlar', '10202001009000011', 'Bo\'lak', '34000'),
            'Chizkeyk' => $make('Chizkeyk', 'Desertlar', '10202001009000012', 'Bo\'lak', '42000'),
            'Limonad' => $make('Limonad', 'Ichimliklar', '10202001005000011', 'Grafin', '28000'),
            'Choy' => $make('Choy', 'Ichimliklar', '10202001005000012', 'Choynak', '15000', [], ['quick_add' => true]),
        ];
        foreach ([
            'cold' => ['Salatlar', 'Desertlar'],
            'hot' => ['Issiq taomlar'],
            'grill' => ['Mangal'],
            'bar' => ['Ichimliklar'],
        ] as $station => $names) {
            foreach ($names as $name) {
                KitchenStationRoute::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'station_id' => $stations[$station]->id, 'category_id' => $categories[$name]->id, 'product_id' => null]);
            }
        }

        // 20 tables: 16 of four in the main hall, 4 of ten in the VIP hall
        $main = Hall::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $vip = Hall::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'VIP zal', 'sort_order' => 2]);
        foreach (range(1, 20) as $number) {
            DiningTable::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'hall_id' => $number <= 16 ? $main->id : $vip->id,
                'name' => "Stol {$number}",
                'kind' => 'table',
                'seats' => $number <= 16 ? 4 : 10,
                'sort_order' => $number,
                'layout_x' => (($number - 1) % 5) * 5,
                'layout_y' => intdiv($number - 1, 5) * 4,
                'layout_w' => 4,
                'layout_h' => 3,
            ]);
        }

        // Service charge: 10% on the row, 12% for a hall order by the rules, 15% for a table of 8 guests or more
        ServiceFeeSetting::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'service_charge_percent' => '10',
            'place_fee_amount' => '0',
            'place_fee_mode' => 'per_person',
            'service_charge_rules' => ['by_order_type' => ['hall' => '12.00'], 'large_group_min_guests' => 8, 'large_group_percent' => '15.00', 'cover_amount' => null],
        ]);
        // Tips go to one pool for the day, split by role weights: a waiter 2 parts, the cook 1
        PosTipSetting::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'mode' => 'pool',
            'pool_rule' => 'role_weights',
            'pool_period' => 'day',
            'roles' => ['waiter', 'cook'],
            'role_weights' => ['waiter' => '2', 'cook' => '1'],
        ]);
        // Table bookings: at least an hour ahead, 2 hours a table, 50 000 deposit per guest from 6 guests, needed to confirm
        BranchBookingRules::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'table_slot_minutes' => 120,
            'table_min_advance_minutes' => 60,
            'deposit_mode' => 'per_guest',
            'deposit_amount' => '50000',
            'deposit_from_guests' => 6,
            'deposit_required_to_confirm' => true,
        ]);
        // Staff pay by the business day, sales to the waiter at payment time; one rule per role
        StaffPaySetting::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'period' => 'day', 'sales_attribution' => 'at_payment', 'updated_by' => $staff['owner']->id]);
        $rules = app(StaffPayRules::class);
        foreach ([
            'waiter' => [['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'sales_percent', 'percent' => '5'], ['type' => 'service_charge_percent', 'percent' => '30'], ['type' => 'tips']],
            'cook' => [['type' => 'shift_rate', 'amount' => '180000'], ['type' => 'tips']],
            'hostess' => [['type' => 'shift_rate', 'amount' => '120000']],
            'cashier' => [['type' => 'shift_rate', 'amount' => '110000']],
        ] as $role => $components) {
            $rules->create($branch, ['role' => $role, 'effective_from' => '2026-01-01', 'components' => $components], $staff['owner']);
        }

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach ([
            'cashier' => [DeviceType::POS, 'Kassa'],
            'waiter1' => [DeviceType::POS, 'Ofitsiant 1 telefoni'],
            'waiter2' => [DeviceType::POS, 'Ofitsiant 2 telefoni'],
            'waiter3' => [DeviceType::POS, 'Ofitsiant 3 telefoni'],
            'waiter4' => [DeviceType::POS, 'Ofitsiant 4 telefoni'],
            'hostess' => [DeviceType::POS, 'Hostes planshet'],
            'kds' => [DeviceType::KDS, 'Oshxona ekrani'],
        ] as $key => [$type, $name]) {
            $code = $pairing->createCode($store, $branch, $type, $name, $staff['owner'])['code'];
            $paired = $pairing->pair($code, ['platform' => 'linux']);
            $terminals[$key] = [
                'token' => $paired['token'],
                'device' => [
                    'uuid' => $paired['device']->uuid,
                    'type' => $type->value,
                    'name' => $name,
                    'platform' => 'linux',
                    'app_version' => null,
                    'store_id' => $store->id,
                    'branch_id' => $branch->id,
                ],
                'store' => ['id' => $store->id, 'name' => $store->name],
                'branch' => ['id' => $branch->id, 'name' => $branch->name],
            ];
        }

        $seedStaff = [];
        foreach ($staff as $key => $user) {
            $seedStaff[$key] = ['id' => $user->id, 'name' => $user->name, 'pin' => $pins[$key] ?? null];
        }
        $seedStaff['owner']['token'] = $staff['owner']->createToken('pos-e2e-restoran-owner')->plainTextToken;

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::RESTORAN_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => $seedStaff,
            'terminals' => $terminals,
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
        ];
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
