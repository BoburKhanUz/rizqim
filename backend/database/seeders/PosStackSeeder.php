<?php

namespace Database\Seeders;

use App\Domain\Pos\Accounts\CustomerAccountKind;
use App\Domain\Pos\Accounts\TalonMode;
use App\Domain\Pos\Menu\Combos;
use App\Domain\Pos\Menu\DailyMenuService;
use App\Domain\Pos\Menu\MealSets;
use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\StaffPay\StaffPayRules;
use App\Domain\Pos\Vouchers\VoucherEntryType;
use App\Domain\Pos\Vouchers\VoucherKind;
use App\Domain\Pos\Vouchers\VoucherStatus;
use App\Enums\BranchStatus;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\AutoGuestItem;
use App\Models\BanquetExtra;
use App\Models\BanquetHallRate;
use App\Models\BanquetOpsSetting;
use App\Models\BanquetPackage;
use App\Models\BanquetPackageItem;
use App\Models\BanquetPaymentSetting;
use App\Models\BanquetSetting;
use App\Models\Branch;
use App\Models\BranchBookingRules;
use App\Models\BranchLabelSettings;
use App\Models\BranchPackagingRule;
use App\Models\BranchProductRotation;
use App\Models\BranchTakeawaySettings;
use App\Models\Brand;
use App\Models\BrandChannel;
use App\Models\Category;
use App\Models\ChannelCommission;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountMember;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\MenuBatch;
use App\Models\ModifierGroup;
use App\Models\PosQrSetting;
use App\Models\PosTipSetting;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\RoomTariff;
use App\Models\ServiceFeeSetting;
use App\Models\StaffPaySetting;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\StaffPinService;
use App\Support\Money;
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
 * P6-39: a fourth store, a fast food ("Rizqim Fast Food", e2e/pos/test/lunch_rush_fast_food_test.dart): fiscal on the
 * sandbox driver (one fiscal line per combo), a branch on the `fast_food` profile, five stations with time targets
 * (grill, fryer, drinks, a barista for the coffee corner, the expo), a menu of lavash, burgers, fries, drinks, sauces and
 * coffee with quick modifiers (the meat as chips, drink and cup sizes as a size selector, defaults preselected), two
 * combos (the lavash kombo of the owner's receipt example, a burger kombo), cup stickers for drinks and coffee with a bag
 * label, a TSPL label printer on the LAN, packaging rules (a bag per takeaway, a cup per drink), pickup time rules
 * (24/7 off), its staff (owner, manager, two cashiers, a cook, an expo) with PINs, five Telegram customers and five
 * paired devices (two tills, the kitchen display, the expo screen, the ready-numbers board). In the seed file under
 * `fastfood`.
 *
 * P6 (stage B): a fifth store, the restaurant of the local bridge drill ("Rizqim Lokal Restoran",
 * e2e/pos/test/internet_outage_lan_test.dart): fiscal on the sandbox driver, a branch on the `restoran` profile with the
 * customer display on, eight tables, a kitchen of three stations (hot and grill on the kitchen display, the bar
 * without one), a short menu, its staff with PINs (owner, manager, cashier, two waiters, a cook), the till as the
 * branch's printing till, a LAN receipt printer, and five paired devices (the till, two waiters' phones, the
 * kitchen display, the customer display following the till). In the seed file under `lan`, with the devices' server ids
 * (the orders' `device_id`).
 *
 * P6-49: a sixth store, a stolovaya ("Rizqim Stolovaya", e2e/pos/test/stolovaya_lunch_test.dart): fiscal on the sandbox
 * driver (MXIK codes for advances and credit), a branch on the `stolovaya` profile with QR payments and gift
 * certificates switched on, the store's own Click and Payme merchant settings (test values) and the UzQR sandbox
 * gateway, a menu of first and second courses, salads, bread and drinks with a kompleks (meal set) and today's daily
 * menu, a corporate account "Paxta Tekstil" (daily limit talons, 12 members, one inactive, a monthly limit, no manager
 * approval), a subscription account (one meal a day), a nasiya customer, three gift certificates (one blocked), a
 * promo code, its staff (owner, manager, two cashiers) with PINs and two paired tills. In the seed file under
 * `stolovaya`.
 *
 * P6-59: a seventh store, a wedding hall ("Rizqim To'yxona", e2e/pos/test/toyxona_wedding_test.dart): the `toyxona`
 * profile with KDS and staff pay, two halls with rooms, slots, two packages with menu items, extras, hall rates, the
 * 30/40/rest payment template, cancellation bands, a contract template, the osh calculator, the waiters' event pay, its
 * staff (owner, two managers — one with a panel token —, cashier, two waiters, a cook), two tills and the kitchen
 * display; under `toyxona`. And an eighth, a dark kitchen ("Rizqim Dark Kitchen", e2e/pos/test/dark_kitchen_test.dart):
 * the `dark_kitchen` profile, three brands with channel rules, their dishes on three stations of one kitchen display,
 * the ingredients "Tovuq" and "Pishloq" linked across brands, the aggregator commission, a Telegram customer, two
 * aggregator orders of today, its staff, the till and the kitchen display; under `darkkitchen`.
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

    /** P6-39: the fast food store of the "tushlik cho'qqisi" end-to-end test (its own store) */
    public const FAST_FOOD_TIN = '301234570';

    public const FAST_FOOD_PINS = [
        'manager' => '7351',
        'cashier1' => '8462',
        'cashier2' => '9573',
        'cook' => '1684',
        'expo' => '2795',
    ];

    /** P6 (stage B): the restaurant of the "internet uzildi" local bridge drill (its own store) */
    public const LAN_TIN = '301234571';

    public const LAN_PINS = [
        'manager' => '8152',
        'cashier' => '9263',
        'waiter1' => '1374',
        'waiter2' => '2485',
        'cook' => '3596',
    ];

    /** P6-49: the stolovaya of the "stolovaya tushligi" drill (its own store) */
    public const STOLOVAYA_TIN = '301234572';

    public const STOLOVAYA_PINS = [
        'manager' => '4172',
        'cashier1' => '5283',
        'cashier2' => '6394',
    ];

    /** P6-59: the wedding hall of the "200 mehmonli to'y" drill (its own store) */
    public const TOYXONA_TIN = '301234573';

    public const TOYXONA_PINS = [
        'manager1' => '5162',
        'manager2' => '6273',
        'cashier' => '7384',
        'waiter1' => '8495',
        'waiter2' => '9516',
        'cook' => '1627',
    ];

    /** P6-59: the dark kitchen of the "dark kitchen" drill (its own store) */
    public const DARK_KITCHEN_TIN = '301234574';

    public const DARK_KITCHEN_PINS = [
        'manager' => '2738',
        'cashier' => '3849',
        'cook' => '4951',
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
            'fastfood' => $this->fastFood(),
            'lan' => $this->lanRestaurant(),
            'stolovaya' => $this->stolovaya(),
            'toyxona' => $this->toyxona(),
            'darkkitchen' => $this->darkKitchen(),
        ]);
    }

    /**
     * P6-39: the fast food store of the lunch-rush test (e2e/pos/test/lunch_rush_fast_food_test.dart), with its own
     * staff, menu, combos, stations, rules and devices.
     *
     * @return array<string, mixed> the seed file's `fastfood` section (the same shape as the top level)
     */
    private function fastFood(): array
    {
        $store = Store::forceCreate([
            'name' => 'Rizqim Fast Food',
            'slug' => 'rizqim-fast-food',
            'status' => 'active',
            'is_open' => true,
            // Equal hours: a pickup time is taken at any hour (24/7 itself, the round_the_clock module, stays off)
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::FAST_FOOD_TIN,
            'tax_regime' => 'vat',
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => false, 'pickup_enabled' => true, 'delivery_fee' => 0],
            // One fiscal line per combo (the owner's decision, `single`); the test switches it to `split` for a while
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'combo_fiscal_mode' => 'single',
                'charges' => ['packaging' => ['name' => 'Qadoq', 'mxik' => '10202002010000003']],
            ],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Chilonzor fast food',
            'latitude' => 41.285,
            'longitude' => 69.204,
            'radius_km' => 6,
            'status' => BranchStatus::ACTIVE->value,
            'business_day_starts_at' => '05:00:00',
        ]);
        // The fast food profile: quick counter, numbers, the board, KDS with expo, combo, quick modifiers, labels,
        // packaging and pickup times (round_the_clock is not on it)
        $branch->forceFill(['pos_settings' => PosSettings::withFormat(null, FormatProfile::FAST_FOOD, [])])->save();

        $pins = self::FAST_FOOD_PINS;
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
        $staff = [
            'owner' => $person(UserRole::OWNER, 'Timur Fastfudov', '+998901000061', null),
            'manager' => $person(UserRole::MANAGER, 'Zarina Menejer', '+998901000062', $pins['manager']),
            'cashier1' => $person(UserRole::CASHIER, 'Asal Kassir', '+998901000063', $pins['cashier1']),
            'cashier2' => $person(UserRole::CASHIER, 'Behruz Kassir', '+998901000064', $pins['cashier2']),
            'cook' => $person(UserRole::COOK, 'Shahzod Oshpaz', '+998901000065', $pins['cook']),
            'expo' => $person(UserRole::COOK, 'Ravshan Yig\'uvchi', '+998901000066', $pins['expo']),
        ];

        // Grill, fryer and drinks for the counter, a barista for the coffee corner, the expo assembles; each with its
        // time target (seconds; the run keeps them short, the fryer is the one that falls behind)
        $stations = [];
        foreach ([
            'grill' => ['Grill', 'grill', 60],
            'fryer' => ['Fritur', 'fryer', 20],
            'drinks' => ['Ichimliklar', 'drinks', 30],
            'barista' => ['Barista', 'barista', 45],
            'expo' => ["Yig'ish", 'expo', 30],
        ] as $key => [$name, $kind, $target]) {
            $stations[$key] = KitchenStation::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => $name,
                'kind' => $kind,
                'sort_order' => count($stations) + 1,
                'show_on_kds' => true,
                'target_seconds' => $target,
            ]);
        }

        $categories = [];
        foreach (['Lavash' => 'ff-lavash', 'Burger' => 'ff-burger', 'Fri' => 'ff-fri', 'Ichimliklar' => 'ff-ichimlik', 'Soslar' => 'ff-sous', 'Kofe' => 'ff-kofe', 'Kombo' => 'ff-kombo'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }
        $product = function (string $name, string $category, string $mxik, array $attributes = []) use ($store, $categories): Product {
            return Product::create($attributes + [
                'store_id' => $store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-ff-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);
        };
        $variant = fn (Product $product, string $name, string $price) => ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'price' => $price, 'stock' => 100000, 'is_active' => true]);

        $lavash = $product('Lavash', 'Lavash', '10202001011000021');
        $gamburger = $product('Gamburger', 'Burger', '10202001012000021');
        $chizburger = $product('Chizburger', 'Burger', '10202001012000022');
        $fri = $product('Kartoshka fri', 'Fri', '10202001013000021');
        // A dish of its own target: nuggets take longer than the fryer's default
        $nuggets = $product('Nagetslar', 'Fri', '10202001013000022', ['kds_target_seconds' => 30]);
        $pepsi = $product('Pepsi', 'Ichimliklar', '10202001005000021');
        $fanta = $product('Fanta', 'Ichimliklar', '10202001005000022');
        $water = $product('Suv', 'Ichimliklar', '10202001005000023');
        $hot = $product('Achchiq sous', 'Soslar', '10202001016000021');
        $garlic = $product('Sarimsoqli sous', 'Soslar', '10202001016000022');
        $cappuccino = $product('Kapuchino', 'Kofe', '10202001005000024');
        $latte = $product('Latte', 'Kofe', '10202001005000025');
        $lavashCombo = $product('Lavash kombo', 'Kombo', '10202001009000021');
        $burgerCombo = $product('Burger kombo', 'Kombo', '10202001009000022');

        $menu = [
            'Lavash' => $variant($lavash, 'Katta', '34000'),
            'Lavash (oddiy)' => $variant($lavash, 'Oddiy', '28000'),
            'Gamburger' => $variant($gamburger, 'Dona', '30000'),
            'Chizburger' => $variant($chizburger, 'Dona', '34000'),
            'Kartoshka fri' => $variant($fri, 'Porsiya', '15000'),
            'Nagetslar' => $variant($nuggets, '6 dona', '22000'),
            'Pepsi' => $variant($pepsi, 'Stakan', '8000'),
            'Fanta' => $variant($fanta, 'Stakan', '8000'),
            'Suv' => $variant($water, '0,5 l', '4000'),
            'Achchiq sous' => $variant($hot, 'Dona', '3000'),
            'Sarimsoqli sous' => $variant($garlic, 'Dona', '3000'),
            'Kapuchino' => $variant($cappuccino, 'Stakan', '22000'),
            'Latte' => $variant($latte, 'Stakan', '24000'),
            'Lavash kombo' => $variant($lavashCombo, 'Kombo', '45000'),
            'Burger kombo' => $variant($burgerCombo, 'Kombo', '42000'),
        ];

        // Quick modifiers: the meat as chips on the lavash tile, the drink and coffee sizes as a one-tap size selector,
        // each with its preselected option
        $meat = ModifierGroup::create(['store_id' => $store->id, 'name' => "Go'sht", 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'display_mode' => 'inline']);
        $beef = $meat->modifiers()->create(['name' => "Mol go'shtli", 'price_delta' => 0, 'sort_order' => 1, 'is_default' => true]);
        $chicken = $meat->modifiers()->create(['name' => "Tovuq go'shtli", 'price_delta' => 0, 'sort_order' => 2]);
        $lavash->modifierGroups()->attach($meat->id, ['sort_order' => 1]);
        $size = ModifierGroup::create(['store_id' => $store->id, 'name' => 'Hajm', 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'display_mode' => 'size']);
        $small = $size->modifiers()->create(['name' => '0,3 l', 'price_delta' => 0, 'sort_order' => 1]);
        $medium = $size->modifiers()->create(['name' => '0,4 l', 'price_delta' => 2000, 'sort_order' => 2, 'is_default' => true]);
        $large = $size->modifiers()->create(['name' => '0,5 l', 'price_delta' => 4000, 'sort_order' => 3]);
        foreach ([$pepsi, $fanta] as $drink) {
            $drink->modifierGroups()->attach($size->id, ['sort_order' => 1]);
        }
        $cup = ModifierGroup::create(['store_id' => $store->id, 'name' => 'Stakan hajmi', 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'display_mode' => 'size']);
        $cup->modifiers()->create(['name' => '0,3 l', 'price_delta' => 0, 'sort_order' => 1, 'is_default' => true]);
        $cup->modifiers()->create(['name' => '0,4 l', 'price_delta' => 5000, 'sort_order' => 2]);
        foreach ([$cappuccino, $latte] as $coffee) {
            $coffee->modifierGroups()->attach($cup->id, ['sort_order' => 1]);
        }

        // The combos. The lavash kombo is the owner's receipt example (30.10.2026): "1 ta lavash — katta, mol go'shtli",
        // "Ichimlik — 0,4 l Pepsi", "Sous — achchiq"
        $combos = app(Combos::class);
        $preset = fn ($modifier) => [['modifier_id' => $modifier->id]];
        $combos->save((int) $store->id, $lavashCombo, [
            ['name' => 'Asosiy', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $menu['Lavash']->id, 'modifiers' => $preset($beef), 'is_default' => true, 'label' => "1 ta lavash — katta, mol go'shtli"],
                ['variant_id' => $menu['Lavash']->id, 'modifiers' => $preset($chicken), 'label' => "1 ta lavash — katta, tovuq go'shtli"],
            ]],
            ['name' => 'Ichimlik', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $menu['Pepsi']->id, 'modifiers' => $preset($medium), 'is_default' => true, 'label' => 'Ichimlik — 0,4 l Pepsi'],
                ['variant_id' => $menu['Fanta']->id, 'modifiers' => $preset($medium), 'label' => 'Ichimlik — 0,4 l Fanta'],
                ['variant_id' => $menu['Pepsi']->id, 'modifiers' => $preset($large), 'price_delta' => '2000', 'label' => 'Ichimlik — 0,5 l Pepsi'],
            ]],
            ['name' => 'Sous', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $menu['Achchiq sous']->id, 'is_default' => true, 'label' => 'Sous — achchiq'],
                ['variant_id' => $menu['Sarimsoqli sous']->id, 'label' => 'Sous — sarimsoqli'],
            ]],
        ], true, $staff['owner']);
        $combos->save((int) $store->id, $burgerCombo, [
            ['name' => 'Asosiy', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $menu['Chizburger']->id, 'is_default' => true],
                ['variant_id' => $menu['Gamburger']->id],
            ]],
            ['name' => 'Garnir', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $menu['Kartoshka fri']->id, 'is_default' => true],
                ['variant_id' => $menu['Nagetslar']->id, 'price_delta' => '5000'],
            ]],
            ['name' => 'Ichimlik', 'min_picks' => 1, 'max_picks' => 1, 'options' => [
                ['variant_id' => $menu['Pepsi']->id, 'modifiers' => $preset($medium), 'is_default' => true, 'label' => 'Ichimlik — 0,4 l Pepsi'],
                ['variant_id' => $menu['Fanta']->id, 'modifiers' => $preset($medium), 'label' => 'Ichimlik — 0,4 l Fanta'],
            ]],
        ], true, $staff['owner']);

        // Routes by category; the sauces are put in at the grill. A combo line itself goes to no station (its parts do)
        foreach ([
            'grill' => ['Lavash', 'Burger', 'Soslar'],
            'fryer' => ['Fri'],
            'drinks' => ['Ichimliklar'],
            'barista' => ['Kofe'],
        ] as $station => $names) {
            foreach ($names as $name) {
                KitchenStationRoute::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'station_id' => $stations[$station]->id, 'category_id' => $categories[$name]->id, 'product_id' => null]);
            }
        }

        // Cup stickers for the drinks and the coffee (one per cup), a bag label for every takeaway; a TSPL label
        // printer on the LAN (the test points it at its emulator)
        BranchLabelSettings::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'size' => '40x30',
            'scope' => 'categories',
            'category_ids' => [$categories['Ichimliklar']->id, $categories['Kofe']->id],
            'per_unit' => true,
            'bag_label' => true,
        ]);
        $printer = Printer::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'name' => 'Yorliq printeri',
            'purpose' => 'label',
            'connection' => 'lan',
            'address' => '127.0.0.1',
            'port' => 9100,
            'paper_width' => 58,
        ]);

        // Packaging: a bag for every takeaway (counter and Telegram pickup) and a cup for every drink
        $bag = BranchPackagingRule::create([
            'store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'Paket', 'basis' => 'order', 'quantity' => 1,
            'unit_price' => '1500', 'mxik' => '10202002010000001', 'package_code' => '1500001', 'unit' => 'dona',
            'order_types' => ['takeaway', 'pickup', 'delivery'], 'is_active' => true, 'sort_order' => 1,
        ]);
        $cupRule = BranchPackagingRule::create([
            'store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'Stakan', 'basis' => 'item',
            'category_id' => $categories['Ichimliklar']->id, 'quantity' => 1, 'unit_price' => '1000',
            'mxik' => '10202002010000002', 'package_code' => '1500002', 'unit' => 'dona',
            'order_types' => ['takeaway', 'pickup', 'delivery'], 'is_active' => true, 'sort_order' => 2,
        ]);
        // Pickup times: 20 minutes ahead at the earliest, 15-minute slots, today and tomorrow, 10 minutes to cook
        BranchTakeawaySettings::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'pickup_min_lead_minutes' => 20,
            'pickup_slot_minutes' => 15,
            'pickup_max_days_ahead' => 1,
            'pickup_prep_minutes' => 10,
        ]);

        // The customers of this store's Telegram bot (several: one customer may place 10 orders a minute)
        $customers = [];
        foreach (['Nilufar', 'Jahongir', 'Sevara', 'Otabek', 'Dildora'] as $k => $name) {
            $customers[] = User::create([
                'name' => "{$name} Mijoz",
                'phone' => '+9989010000' . (70 + $k),
                'password' => bcrypt('pos-e2e-customer-not-used'),
                'telegram_id' => (string) (910000070 + $k),
            ]);
        }

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach ([
            'till1' => [DeviceType::POS, 'Kassa 1'],
            'till2' => [DeviceType::POS, 'Kassa 2'],
            // The station screens (grill, fryer, drinks, barista) on one kitchen display; the expo has its own
            'kds' => [DeviceType::KDS, 'Oshxona ekrani'],
            'expo' => [DeviceType::KDS, "Yig'ish ekrani"],
            'display' => [DeviceType::DISPLAY, 'Raqamlar ekrani'],
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
        $seedStaff['owner']['token'] = $staff['owner']->createToken('pos-e2e-fastfood-owner')->plainTextToken;

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::FAST_FOOD_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => $seedStaff,
            'terminals' => $terminals,
            'customers' => array_map(fn (User $customer) => ['id' => $customer->id, 'name' => $customer->name, 'token' => $customer->createToken('pos-e2e-fastfood')->plainTextToken], $customers),
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
            'categories' => array_map(fn (Category $category) => (int) $category->id, $categories),
            'stations' => array_map(fn (KitchenStation $station) => (int) $station->id, $stations),
            'label_printer' => ['id' => (int) $printer->id],
            'packaging' => ['bag' => (int) $bag->id, 'cup' => (int) $cupRule->id],
        ];
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
     * P6 (stage B): the restaurant of the "internet uzildi" drill, with its own staff, menu and devices.
     *
     * @return array<string, mixed> the seed file's `lan` section (the same shape as the top level)
     */
    private function lanRestaurant(): array
    {
        $store = Store::forceCreate([
            'name' => 'Rizqim Lokal Restoran',
            'slug' => 'rizqim-lokal-restoran',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::LAN_TIN,
            'tax_regime' => 'vat',
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => false, 'pickup_enabled' => true, 'delivery_fee' => 0],
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox'],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Chilonzor restorani',
            'latitude' => 41.285,
            'longitude' => 69.204,
            'radius_km' => 8,
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
        $pins = self::LAN_PINS;
        $staff = [
            'owner' => $person(UserRole::OWNER, 'Nodir Lokalov', '+998901000071', null),
            'manager' => $person(UserRole::MANAGER, 'Gulnora Menejer', '+998901000072', $pins['manager']),
            'cashier' => $person(UserRole::CASHIER, 'Dilnoza Kassir', '+998901000073', $pins['cashier']),
            'waiter1' => $person(UserRole::WAITER, 'Sherzod Ofitsiant', '+998901000074', $pins['waiter1']),
            'waiter2' => $person(UserRole::WAITER, 'Ulugbek Ofitsiant', '+998901000075', $pins['waiter2']),
            'cook' => $person(UserRole::COOK, 'Baxtiyor Oshpaz', '+998901000076', $pins['cook']),
        ];

        $stations = [];
        foreach ([
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
        foreach (['Issiq taomlar' => 'ln-issiq', 'Mangal' => 'ln-mangal', 'Ichimliklar' => 'ln-ichimlik'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }
        $make = function (string $name, string $category, string $mxik, string $variant, string $price) use ($store, $categories): ProductVariant {
            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-ln-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create(['product_id' => $product->id, 'name' => $variant, 'price' => $price, 'stock' => 100000, 'is_active' => true]);
        };
        $menu = [
            "Lag'mon" => $make("Lag'mon", 'Issiq taomlar', '10202001002000021', 'Porsiya', '48000'),
            'Manti' => $make('Manti', 'Issiq taomlar', '10202001002000022', 'Porsiya', '42000'),
            "Qo'y kabob" => $make("Qo'y kabob", 'Mangal', '10202001007000021', 'Six', '26000'),
            'Tovuq kabob' => $make('Tovuq kabob', 'Mangal', '10202001007000022', 'Six', '22000'),
            'Choy' => $make('Choy', 'Ichimliklar', '10202001005000021', 'Choynak', '15000'),
            'Limonad' => $make('Limonad', 'Ichimliklar', '10202001005000022', 'Grafin', '28000'),
        ];
        foreach (['hot' => 'Issiq taomlar', 'grill' => 'Mangal', 'bar' => 'Ichimliklar'] as $station => $category) {
            KitchenStationRoute::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'station_id' => $stations[$station]->id, 'category_id' => $categories[$category]->id, 'product_id' => null]);
        }

        $hall = Hall::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        foreach (range(1, 8) as $number) {
            DiningTable::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'hall_id' => $hall->id,
                'name' => "Stol {$number}",
                'kind' => 'table',
                'seats' => 4,
                'sort_order' => $number,
                'layout_x' => (($number - 1) % 4) * 5,
                'layout_y' => intdiv($number - 1, 4) * 4,
                'layout_w' => 4,
                'layout_h' => 3,
            ]);
        }

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        $devices = [];
        foreach ([
            'cashier' => [DeviceType::POS, 'Kassa', 'cashier'],
            'waiter1' => [DeviceType::POS, 'Ofitsiant 1 telefoni', 'waiter'],
            'waiter2' => [DeviceType::POS, 'Ofitsiant 2 telefoni', 'waiter'],
            'kds' => [DeviceType::KDS, 'Oshxona ekrani', 'kds'],
            'display' => [DeviceType::DISPLAY, 'Mijoz ekrani', 'customer_display'],
        ] as $key => [$type, $name, $app]) {
            $code = $pairing->createCode($store, $branch, $type, $name, $staff['owner'], $key === 'display' ? $devices['cashier'] : null)['code'];
            // The app each device runs, as the apps send it at pairing (P6 stage B: the hub is a cashier till)
            $paired = $pairing->pair($code, ['platform' => 'linux', 'app' => $app]);
            $devices[$key] = $paired['device'];
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

        // The till prints the online orders' tickets and the bills the waiters ask for (auto_print_bill is on by
        // default); the customer display shows its cart
        $branch->forceFill(['pos_settings' => PosSettings::withFormat(['printing_device_id' => $devices['cashier']->uuid], FormatProfile::RESTORAN, ['customer_display' => true])])->save();
        // The receipt printer on the LAN (reached by address, tied to no terminal; the printing till prints the bills on
        // it). The test points it at its emulator
        $printer = Printer::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'name' => 'Kassa chek printeri',
            'purpose' => 'receipt',
            'connection' => 'lan',
            'address' => '127.0.0.1',
            'port' => 9100,
            'paper_width' => 80,
        ]);

        $seedStaff = [];
        foreach ($staff as $key => $user) {
            $seedStaff[$key] = ['id' => $user->id, 'name' => $user->name, 'pin' => $pins[$key] ?? null];
        }
        $seedStaff['owner']['token'] = $staff['owner']->createToken('pos-e2e-lan-owner')->plainTextToken;

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::LAN_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => $seedStaff,
            'terminals' => $terminals,
            'device_ids' => array_map(fn ($device) => (int) $device->id, $devices),
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
            'stations' => array_map(fn (KitchenStation $station) => (int) $station->id, $stations),
            'receipt_printer' => ['id' => (int) $printer->id],
        ];
    }

    /**
     * P6-49: the stolovaya of the "stolovaya tushligi" drill (e2e/pos/test/stolovaya_lunch_test.dart), with its own
     * staff, menu, kompleks, daily menu, corporate and subscription accounts, nasiya customer, gift certificates, promo
     * code, the store's own Click and Payme merchant settings (sandbox-style test values; the test signs the providers'
     * webhooks with them) and the UzQR sandbox gateway, and two tills.
     *
     * @return array<string, mixed> the seed file's `stolovaya` section (the same shape as the top level)
     */
    private function stolovaya(): array
    {
        // Test merchant settings of the drill, readable on purpose (the seed file hands them to the webhook signer)
        $click = ['enabled' => true, 'service_id' => '49001', 'merchant_id' => '49002', 'merchant_user_id' => '49003', 'secret_key' => 'e2e-stolovaya-click'];
        $payme = ['enabled' => true, 'merchant_id' => 'e2e-stolovaya-payme-merchant', 'secret_key' => 'e2e-stolovaya-payme'];

        $store = Store::forceCreate([
            'name' => 'Rizqim Stolovaya',
            'slug' => 'rizqim-stolovaya',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::STOLOVAYA_TIN,
            'tax_regime' => 'vat',
            // A POS QR is always the store's own merchant account (StoreMerchant)
            'payment_account_type' => 'own_account',
            'payment_settings' => ['cash' => ['enabled' => true], 'click' => $click, 'payme' => $payme, 'delivery_enabled' => false, 'pickup_enabled' => true, 'delivery_fee' => 0],
            // A gift certificate sold is an advance (the `deposit` charge's MXIK); UzQR through the sandbox gateway
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => [
                    'deposit' => ['mxik' => '10202002003000001'],
                    'credit' => ['mxik' => '10202002004000001'],
                ],
                'uzqr' => ['driver' => 'sandbox'],
            ],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Paxta stolovayasi',
            'latitude' => 41.33,
            'longitude' => 69.28,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
            'business_day_starts_at' => '05:00:00',
        ]);
        // The stolovaya profile (quick counter, daily menu, kompleks, customer and corporate accounts), with the QR
        // payments and the gift certificates switched on over it
        $branch->forceFill(['pos_settings' => PosSettings::withFormat(null, FormatProfile::STOLOVAYA, ['qr_payments' => true, 'vouchers' => true])])->save();

        $pins = self::STOLOVAYA_PINS;
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
        $staff = [
            'owner' => $person(UserRole::OWNER, 'Gulnora Stolovayeva', '+998901000081', null),
            'manager' => $person(UserRole::MANAGER, 'Farrux Menejer', '+998901000082', $pins['manager']),
            'cashier1' => $person(UserRole::CASHIER, 'Mohira Kassir', '+998901000083', $pins['cashier1']),
            'cashier2' => $person(UserRole::CASHIER, 'Sardor Kassir', '+998901000084', $pins['cashier2']),
        ];

        $categories = [];
        foreach (['Birinchi taom' => 'st-birinchi', 'Ikkinchi taom' => 'st-ikkinchi', 'Salatlar' => 'st-salat', 'Non' => 'st-non', 'Ichimliklar' => 'st-ichimlik', 'Kompleks' => 'st-kompleks'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }
        $make = function (string $name, string $category, string $mxik, string $variant, string $price) use ($store, $categories): ProductVariant {
            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-st-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create(['product_id' => $product->id, 'name' => $variant, 'price' => $price, 'stock' => 100000, 'is_active' => true]);
        };
        $menu = [
            'Mastava' => $make('Mastava', 'Birinchi taom', '10202001020000031', 'Kosa', '14000'),
            "Sho'rva" => $make("Sho'rva", 'Birinchi taom', '10202001020000032', 'Kosa', '15000'),
            'Palov' => $make('Palov', 'Ikkinchi taom', '10202001020000033', 'Porsiya', '22000'),
            "Qovurma lag'mon" => $make("Qovurma lag'mon", 'Ikkinchi taom', '10202001020000034', 'Porsiya', '24000'),
            'Kotlet pyure' => $make('Kotlet pyure', 'Ikkinchi taom', '10202001020000035', 'Porsiya', '20000'),
            'Achichuk' => $make('Achichuk', 'Salatlar', '10202001020000036', 'Porsiya', '6000'),
            'Vinegret' => $make('Vinegret', 'Salatlar', '10202001020000037', 'Porsiya', '7000'),
            'Non' => $make('Non', 'Non', '10202001020000038', 'Dona', '3000'),
            'Choy' => $make('Choy', 'Ichimliklar', '10202001020000039', 'Piyola', '2000'),
            'Kompot' => $make('Kompot', 'Ichimliklar', '10202001020000040', 'Stakan', '4000'),
            'Kompleks tushlik' => $make('Kompleks tushlik', 'Kompleks', '10202001020000041', 'Kompleks', '32000'),
        ];

        // The kompleks: one line at a fixed price, its parts for the kitchen (mastava, kotlet pyure, achichuk, non, kompot)
        app(MealSets::class)->save((int) $store->id, $menu['Kompleks tushlik']->product()->firstOrFail(), [
            ['variant_id' => $menu['Mastava']->id, 'label' => 'Mastava'],
            ['variant_id' => $menu['Kotlet pyure']->id, 'label' => 'Kotlet pyure'],
            ['variant_id' => $menu['Achichuk']->id, 'label' => 'Achichuk'],
            ['variant_id' => $menu['Non']->id, 'label' => 'Non'],
            ['variant_id' => $menu['Kompot']->id, 'label' => 'Kompot'],
        ], true, $staff['owner']);

        // Today's daily menu: the first and second courses rotate; sho'rva and qovurma lag'mon are off today
        $daily = app(DailyMenuService::class);
        $rotating = array_map(fn (string $name) => (int) $menu[$name]->product_id, ['Mastava', "Sho'rva", 'Palov', "Qovurma lag'mon", 'Kotlet pyure']);
        $daily->setRotation($branch, $rotating, $staff['owner']);
        $today = $daily->today($branch);
        $daily->save($branch, $today, array_map(fn (string $name) => (int) $menu[$name]->product_id, ['Mastava', 'Palov', 'Kotlet pyure']), $staff['owner']);

        // QR at the till: Click, Payme and UzQR, Click first; the test shortens the expiry over the owner API
        PosQrSetting::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'providers' => ['click', 'payme', 'uzqr'], 'default_provider' => 'click', 'expiry_minutes' => 5]);

        // "Paxta Tekstil": its staff eat on the company's account, 40 000 so'm a day each, no manager approval at the
        // till; the twelfth member has left (inactive)
        $corporate = CustomerAccount::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'name' => 'Paxta Tekstil',
            'kind' => CustomerAccountKind::CORPORATE->value,
            'company_name' => 'Paxta Tekstil MChJ',
            'inn' => '305123456',
            'contact_name' => 'Rustam Kadrlar',
            'contact_phone' => '+998901200000',
            'invoice_email' => 'buxgalter@paxta-tekstil.example',
            'credit_limit' => '20000000',
            'monthly_limit' => '5000000',
            'charge_approval' => false,
            'talon_mode' => TalonMode::DAILY_LIMIT->value,
        ]);
        $members = [];
        foreach (['Aziza Tikuvchi', 'Bekzod Usta', 'Dilnoza Bichuvchi', 'Elyor Mexanik', 'Feruza Dazmolchi', 'Gayrat Omborchi', 'Hilola Texnolog', 'Ikrom Haydovchi', 'Jasur Elektrik', 'Kamola Hisobchi', 'Lola Laborant', 'Murod Ketgan'] as $i => $name) {
            $members[] = CustomerAccountMember::create([
                'store_id' => $store->id,
                'customer_account_id' => $corporate->id,
                'name' => $name,
                'phone' => '+9989012001' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'code' => 'PT-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'daily_limit' => '40000',
                'is_active' => $i < 11,
            ]);
        }
        // An abonement: one lunch a day per member, any price
        $subscription = CustomerAccount::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'name' => "Ipak Yo'li Logistika",
            'kind' => CustomerAccountKind::CORPORATE->value,
            'company_name' => "Ipak Yo'li Logistika MChJ",
            'credit_limit' => '10000000',
            'charge_approval' => false,
            'talon_mode' => TalonMode::SUBSCRIPTION->value,
        ]);
        $subscribers = [];
        foreach (['Nodir Ekspeditor', 'Oydin Dispetcher'] as $i => $name) {
            $subscribers[] = CustomerAccountMember::create([
                'store_id' => $store->id,
                'customer_account_id' => $subscription->id,
                'name' => $name,
                'phone' => '+9989013001' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'code' => 'IY-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'meals_per_day' => 1,
            ]);
        }
        // A nasiya customer of the old kind (a manager approves the charge)
        $nasiya = CustomerAccount::create([
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'name' => 'Shavkat aka',
            'phone' => '+998901300099',
            'credit_limit' => '500000',
        ]);

        // Three gift certificates sold a week ago (their advance receipts belong to that day); the third one is blocked
        $soldOn = now()->subDays(7);
        $certificates = [];
        /** @var list<array{0: string, 1: string, 2: bool}> $issued code, face value, blocked */
        $issued = [['ACDE-FGHJ-KMN3', '100000', false], ['PQRT-UVWX-Y469', '60000', false], ['HJKM-NPQR-TUV4', '50000', true]];
        foreach ($issued as [$code, $value, $blocked]) {
            $voucher = Voucher::create([
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'kind' => VoucherKind::GIFT->value,
                'code' => $code,
                'face_value' => $value,
                'balance' => $value,
                'currency' => 'UZS',
                'valid_from' => $soldOn->toDateString(),
                'valid_until' => $soldOn->copy()->addMonthsNoOverflow(6)->toDateString(),
                'status' => $blocked ? VoucherStatus::BLOCKED->value : VoucherStatus::ACTIVE->value,
                'sale_tender' => 'cash',
                'sold_by' => $staff['cashier1']->id,
                'sold_at' => $soldOn,
                'blocked_at' => $blocked ? $soldOn->copy()->addDay() : null,
                'blocked_by' => $blocked ? $staff['owner']->id : null,
                'block_reason' => $blocked ? "Xaridor yo'qotdi" : null,
            ]);
            VoucherEntry::create(['voucher_id' => $voucher->id, 'store_id' => $store->id, 'branch_id' => $branch->id, 'type' => VoucherEntryType::SALE->value, 'business_date' => $soldOn->toDateString(), 'amount' => $value, 'balance_after' => $value, 'tender' => 'cash', 'user_id' => $staff['cashier1']->id]);
            if ($blocked) {
                VoucherEntry::create(['voucher_id' => $voucher->id, 'store_id' => $store->id, 'branch_id' => $branch->id, 'type' => VoucherEntryType::BLOCK->value, 'business_date' => $soldOn->copy()->addDay()->toDateString(), 'amount' => '0', 'balance_after' => $value, 'user_id' => $staff['owner']->id, 'note' => "Xaridor yo'qotdi"]);
            }
            $certificates[] = ['uuid' => (string) $voucher->uuid, 'code' => $code, 'value' => Money::of($value)->toString(), 'blocked' => $blocked];
        }

        // A promo code of the store: 10% off, at most 5 000 so'm, from 20 000 so'm of dishes
        $promo = PromoCode::create(['code' => 'STOLOVAYA10', 'type' => 'percentage', 'value' => 10, 'min_order_amount' => '20000', 'max_discount_amount' => '5000', 'is_active' => true, 'store_id' => $store->id]);

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach (['till1' => [DeviceType::POS, 'Kassa 1'], 'till2' => [DeviceType::POS, 'Kassa 2']] as $key => [$type, $name]) {
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
        $seedStaff['owner']['token'] = $staff['owner']->createToken('pos-e2e-stolovaya-owner')->plainTextToken;
        $member = fn (CustomerAccountMember $m) => ['uuid' => (string) $m->uuid, 'name' => $m->name, 'code' => $m->code, 'phone' => $m->phone, 'active' => (bool) $m->is_active];

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::STOLOVAYA_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => $seedStaff,
            'terminals' => $terminals,
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
            'merchant' => ['click' => $click, 'payme' => $payme],
            'corporate' => ['uuid' => (string) $corporate->uuid, 'name' => $corporate->name, 'daily_limit' => '40000.00', 'monthly_limit' => '5000000.00', 'members' => array_map($member, $members)],
            'subscription' => ['uuid' => (string) $subscription->uuid, 'name' => $subscription->name, 'members' => array_map($member, $subscribers)],
            'nasiya' => ['uuid' => (string) $nasiya->uuid, 'name' => $nasiya->name, 'credit_limit' => '500000.00'],
            'vouchers' => $certificates,
            'promo' => ['id' => (int) $promo->id, 'code' => $promo->code],
            'business_date' => $today,
        ];
    }

    /**
     * P6 wave 5 (P6-59): the wedding hall of the "200 mehmonli to'y" drill (e2e/pos/test/toyxona_wedding_test.dart) —
     * its own store on the `toyxona` profile (banquets, bookings, rooms; KDS and staff pay switched on over it), two
     * halls (the white hall with two rooms inside it, the blue hall), the slots, two packages with per-person menu
     * items, extras, the hall rates, the payment template 30 % / 40 % 14 days before / the rest on the day, the
     * cancellation bands, a contract template, the osh calculator, the production sheet's reserve and MXIK codes,
     * waiters' event pay, its staff with PINs, two tills and the kitchen display.
     *
     * @return array<string, mixed> the seed file's `toyxona` section (the same shape as the top level)
     */
    private function toyxona(): array
    {
        $store = Store::forceCreate([
            'name' => "Rizqim To'yxona",
            'slug' => 'rizqim-toyxona',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::TOYXONA_TIN,
            'tax_regime' => 'vat',
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => false, 'pickup_enabled' => false, 'delivery_fee' => 0],
            // Advances (deposits, interims) and the retained part of a cancellation through the charges' MXIK codes too
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => [
                    'deposit' => ['mxik' => '10202002003000003'],
                    'booking_fee' => ['mxik' => '10202002007000003'],
                    'service_charge' => ['mxik' => '10202002005000003'],
                ],
            ],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => "Bodomzor to'yxonasi",
            'latitude' => 41.34,
            'longitude' => 69.29,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
            // The calendar day is the business day: "today" of the drill is the date the seed file says
            'business_day_starts_at' => '00:00:00',
        ]);
        $branch->forceFill(['pos_settings' => PosSettings::withFormat(null, FormatProfile::TOYXONA, ['kds' => true, 'staff_pay' => true])])->save();

        $pins = self::TOYXONA_PINS;
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
        $staff = [
            'owner' => $person(UserRole::OWNER, "Rustam To'yxonachi", '+998901000091', null),
            'manager1' => $person(UserRole::MANAGER, 'Nodira Menejer', '+998901000092', $pins['manager1']),
            'manager2' => $person(UserRole::MANAGER, 'Ulugbek Menejer', '+998901000093', $pins['manager2']),
            'cashier' => $person(UserRole::CASHIER, 'Zarina Kassir', '+998901000094', $pins['cashier']),
            'waiter1' => $person(UserRole::WAITER, 'Shoxrux Ofitsiant', '+998901000095', $pins['waiter1']),
            'waiter2' => $person(UserRole::WAITER, 'Islom Ofitsiant', '+998901000096', $pins['waiter2']),
            'cook' => $person(UserRole::COOK, 'Bahodir Oshpaz', '+998901000097', $pins['cook']),
        ];

        // The kitchen: the osh kazans, the hot kitchen (salads too), the bar; all on the kitchen display
        $stations = [];
        foreach (['osh' => ['Qozon', 'osh'], 'kitchen' => ['Issiq sex', 'kitchen'], 'bar' => ['Bar', 'other']] as $key => [$name, $kind]) {
            $stations[$key] = KitchenStation::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => $name, 'kind' => $kind, 'sort_order' => count($stations) + 1, 'show_on_kds' => true]);
        }
        $categories = [];
        foreach (['Osh' => 'ty-osh', 'Taomlar' => 'ty-taom', 'Salatlar' => 'ty-salat', 'Ichimliklar' => 'ty-ichimlik'] as $name => $slug) {
            $categories[$name] = Category::create(['name' => $name, 'slug' => 'pos-e2e-' . $slug, 'is_active' => true]);
        }
        $make = function (string $name, string $category, string $mxik, string $variant, string $price) use ($store, $categories): ProductVariant {
            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => 'pos-e2e-ty-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create(['product_id' => $product->id, 'name' => $variant, 'price' => $price, 'stock' => 100000, 'is_active' => true]);
        };
        $menu = [
            "To'y oshi" => $make("To'y oshi", 'Osh', '10202001001000051', 'Porsiya', '45000'),
            'Qazi' => $make('Qazi', 'Taomlar', '10202001002000052', 'Porsiya', '60000'),
            'Shashlik' => $make('Shashlik', 'Taomlar', '10202001007000053', 'Six', '25000'),
            'Achichuk' => $make('Achichuk', 'Salatlar', '10202001008000054', 'Porsiya', '12000'),
            'Coca-Cola 1L' => $make('Coca-Cola 1L', 'Ichimliklar', '10202001005000055', 'Shisha', '15000'),
            'Choy' => $make('Choy', 'Ichimliklar', '10202001005000056', 'Choynak', '8000'),
        ];
        foreach ([['osh', 'Osh'], ['kitchen', 'Taomlar'], ['kitchen', 'Salatlar'], ['bar', 'Ichimliklar']] as [$station, $category]) {
            KitchenStationRoute::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'station_id' => $stations[$station]->id, 'category_id' => $categories[$category]->id]);
        }

        // Two halls; the white hall has two rooms inside it (a room may not be booked while a banquet holds the hall)
        $white = Hall::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'Oq zal', 'sort_order' => 1]);
        $blue = Hall::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => "Ko'k zal", 'sort_order' => 2]);
        $rooms = [];
        foreach ([[$white, 'Oq zal VIP 1'], [$white, 'Oq zal VIP 2'], [$blue, "Ko'k zal xonasi"]] as $i => [$hall, $name]) {
            $rooms[] = DiningTable::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'hall_id' => $hall->id, 'name' => $name, 'kind' => 'room', 'seats' => 12, 'sort_order' => $i + 1, 'layout_x' => $i * 5, 'layout_y' => 0, 'layout_w' => 4, 'layout_h' => 3]);
        }
        $rates = ['Oq zal' => ['morning' => '3000000', 'day' => '4000000', 'evening' => '6000000'], "Ko'k zal" => ['morning' => '2000000', 'day' => '2500000', 'evening' => '4000000']];
        foreach ([$white, $blue] as $hall) {
            foreach ($rates[$hall->name] as $slot => $price) {
                BanquetHallRate::create(['store_id' => $store->id, 'hall_id' => $hall->id, 'slot' => $slot, 'price' => $price]);
            }
        }

        // Slots, hold hours, warnings (200/250/300 guests, 06:00–23:00) and the MXIK of the advances and the
        // cancellation fee (test codes; the accountant confirms the real ones)
        BanquetSetting::create([
            'store_id' => $store->id,
            'slots' => ['morning' => ['start' => '07:00', 'end' => '11:00'], 'day' => ['start' => '12:00', 'end' => '16:00'], 'evening' => ['start' => '18:00', 'end' => '23:00']],
            'hold_hours' => 72,
            'guest_warning_thresholds' => [200, 250, 300],
            'window_start' => '06:00',
            'window_end' => '23:00',
            'advance_mxik' => '10202002003000003',
            'cancellation_mxik' => '10202002007000003',
            'quote_currency' => 'USD',
            'version' => 1,
        ]);
        $packages = [];
        foreach ([
            "To'y paketi" => ['180000', 150, ["To'y oshi", 'Qazi', 'Salatlar', 'Choy va non'], [["To'y oshi", '1', 'portion'], ['Qazi', '0.1', 'kg'], ['Achichuk', '0.15', 'kg'], [null, '1', 'piece', 'Non'], ['Choy', '0.25', 'portion']]],
            'Oddiy paket' => ['120000', 80, ["To'y oshi", 'Salatlar', 'Choy'], [["To'y oshi", '1', 'portion'], ['Achichuk', '0.15', 'kg'], ['Choy', '0.25', 'portion']]],
        ] as $name => [$price, $minimum, $included, $items]) {
            $package = BanquetPackage::create(['store_id' => $store->id, 'name' => $name, 'per_person_price' => $price, 'minimum_guests' => $minimum, 'included' => $included, 'sort_order' => count($packages)]);
            foreach ($items as $i => $item) {
                $variant = $item[0] !== null ? $menu[$item[0]] : null;
                BanquetPackageItem::create(['banquet_package_id' => $package->id, 'product_id' => $variant?->product_id, 'product_variant_id' => $variant?->id, 'name' => $item[3] ?? $item[0], 'quantity_per_person' => $item[1], 'unit' => $item[2], 'sort_order' => $i]);
            }
            $packages[$name] = $package;
        }
        $extras = [];
        foreach (['Tort' => ['1500000', 'per_event'], 'Dekor' => ['2000000', 'per_event'], "Qo'shimcha soat" => ['1000000', 'per_event'], 'Mineral suv' => ['6000', 'per_person']] as $name => [$price, $pricing]) {
            $extras[$name] = BanquetExtra::create(['store_id' => $store->id, 'name' => $name, 'price' => $price, 'pricing' => $pricing, 'sort_order' => count($extras)]);
        }
        BanquetPaymentSetting::create([
            'store_id' => $store->id,
            'schedule_template' => [['kind' => 'deposit', 'percent' => '30', 'days_before' => null], ['kind' => 'interim', 'percent' => '40', 'days_before' => 14], ['kind' => 'final', 'percent' => null, 'days_before' => 0]],
            'cancellation_rules' => [['min_days' => 30, 'retained_percent' => '0'], ['min_days' => 14, 'retained_percent' => '30'], ['min_days' => 0, 'retained_percent' => '100']],
            'reminder_days' => [3, 1],
            'overdue_reminders' => true,
            'version' => 1,
        ]);
        // The osh calculator: 6 guests per kg of osh, its ingredients per kg, the kazans; a 5 % reserve on the sheet;
        // the final sale's MXIK codes; the store's own contract text
        BanquetOpsSetting::create([
            'store_id' => $store->id,
            'reserve_percent' => '5',
            'osh' => ['persons_per_kg' => '6', 'product_ids' => [(int) $menu["To'y oshi"]->product_id], 'ingredients' => [
                ['key' => 'rice', 'name' => 'Guruch', 'unit' => 'kg', 'per_kg' => '1'],
                ['key' => 'meat', 'name' => "Go'sht", 'unit' => 'kg', 'per_kg' => '0.8'],
                ['key' => 'carrot', 'name' => 'Sabzi', 'unit' => 'kg', 'per_kg' => '1'],
                ['key' => 'oil', 'name' => "Yog'", 'unit' => 'l', 'per_kg' => '0.2'],
            ], 'kazans' => [20, 30, 50]],
            'portion_weights' => [['product_id' => (int) $menu["To'y oshi"]->product_id, 'grams' => 350]],
            'package_mxik' => '10305001003000003',
            'hall_mxik' => '10305001004000003',
            'extra_mxik' => '10305001005000003',
            'contract_template' => "RIZQIM TO'YXONA — Bodomzor\n\n" . \App\Domain\Pos\Banquet\Ops\BanquetContracts::DEFAULT_TEMPLATE,
            'version' => 1,
        ]);
        // The waiters get 250 000 so'm for every settled event they served (event_rate, kind `banquet`)
        StaffPaySetting::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'period' => 'day', 'sales_attribution' => 'at_payment', 'updated_by' => $staff['owner']->id]);
        app(StaffPayRules::class)->create($branch, ['role' => 'waiter', 'effective_from' => '2026-01-01', 'components' => [['type' => 'event_rate', 'amount' => '250000', 'event_types' => ['banquet']]]], $staff['owner']);

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach (['till1' => [DeviceType::POS, 'Kassa 1'], 'till2' => [DeviceType::POS, 'Kassa 2 (menejer)'], 'kds' => [DeviceType::KDS, 'Oshxona ekrani']] as $key => [$type, $name]) {
            $code = $pairing->createCode($store, $branch, $type, $name, $staff['owner'])['code'];
            $paired = $pairing->pair($code, ['platform' => 'linux']);
            $terminals[$key] = [
                'token' => $paired['token'],
                'device' => ['uuid' => $paired['device']->uuid, 'type' => $type->value, 'name' => $name, 'platform' => 'linux', 'app_version' => null, 'store_id' => $store->id, 'branch_id' => $branch->id],
                'store' => ['id' => $store->id, 'name' => $store->name],
                'branch' => ['id' => $branch->id, 'name' => $branch->name],
            ];
        }

        $seedStaff = [];
        foreach ($staff as $key => $user) {
            $seedStaff[$key] = ['id' => $user->id, 'name' => $user->name, 'pin' => $pins[$key] ?? null];
        }
        $seedStaff['owner']['token'] = $staff['owner']->createToken('pos-e2e-toyxona-owner')->plainTextToken;
        // The second manager works in the web panel (a normal sign-in): the hold race is the till against the panel
        $seedStaff['manager2']['token'] = $staff['manager2']->createToken('pos-e2e-toyxona-manager2')->plainTextToken;

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::TOYXONA_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => $seedStaff,
            'terminals' => $terminals,
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
            'stations' => array_map(fn (KitchenStation $station) => (int) $station->id, $stations),
            'halls' => ['white' => ['id' => (int) $white->id, 'name' => $white->name], 'blue' => ['id' => (int) $blue->id, 'name' => $blue->name]],
            'rooms' => array_map(fn (DiningTable $room) => ['id' => (int) $room->id, 'name' => $room->name, 'hall_id' => (int) $room->hall_id], $rooms),
            'hall_rates' => $rates,
            'packages' => array_map(fn (BanquetPackage $package) => ['id' => (int) $package->id, 'per_person_price' => Money::of($package->per_person_price)->toString(), 'minimum_guests' => (int) $package->minimum_guests], $packages),
            'extras' => array_map(fn (BanquetExtra $extra) => ['id' => (int) $extra->id, 'price' => Money::of($extra->price)->toString(), 'pricing' => $extra->pricing->value], $extras),
            'business_date' => now('Asia/Tashkent')->toDateString(),
        ];
    }

    /**
     * P6 wave 5 (P6-59): the dark kitchen of the "dark kitchen" drill (e2e/pos/test/dark_kitchen_test.dart) — its own
     * store on the `dark_kitchen` profile (brands, the ingredient stop-list): three brands in one kitchen with their
     * channel rules (Burger Bro 15 % dearer on the aggregator, Lavash Lab 10 % dearer in the mobile app, Shirin Bar not
     * sold in the mobile app), their dishes on three stations of one kitchen display, the ingredients "Tovuq" (closes
     * a burger and two lavash dishes of two brands) and "Pishloq" (not critical: its dishes stay on sale, marked), the
     * owner's 25 % aggregator commission, a Telegram customer, its staff with PINs, the till and the kitchen display.
     * Two aggregator orders of today are placed through the checkout action with the `aggregator` channel and
     * delivered: there is no aggregator API yet (their prices are the channel's).
     *
     * @return array<string, mixed> the seed file's `darkkitchen` section (the same shape as the top level)
     */
    private function darkKitchen(): array
    {
        $store = Store::forceCreate([
            'name' => 'Rizqim Dark Kitchen',
            'slug' => 'rizqim-dark-kitchen',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'tin' => self::DARK_KITCHEN_TIN,
            'tax_regime' => 'vat',
            'payment_settings' => ['cash' => ['enabled' => true], 'delivery_enabled' => false, 'pickup_enabled' => true, 'delivery_fee' => 0],
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox'],
        ]);
        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Chilonzor oshxonasi',
            'latitude' => 41.28,
            'longitude' => 69.20,
            'radius_km' => 8,
            'status' => BranchStatus::ACTIVE->value,
            'business_day_starts_at' => '00:00:00',
        ]);
        $branch->forceFill(['pos_settings' => PosSettings::withFormat(null, FormatProfile::DARK_KITCHEN, [])])->save();

        $pins = self::DARK_KITCHEN_PINS;
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
        $staff = [
            'owner' => $person(UserRole::OWNER, 'Akmal Brendov', '+998901000101', null),
            'manager' => $person(UserRole::MANAGER, 'Sevinch Menejer', '+998901000102', $pins['manager']),
            'cashier' => $person(UserRole::CASHIER, 'Doston Kassir', '+998901000103', $pins['cashier']),
            'cook' => $person(UserRole::COOK, 'Javlon Oshpaz', '+998901000104', $pins['cook']),
        ];

        $brands = [];
        foreach (['burger' => ['Burger Bro', 'BB', '#DC2626'], 'lavash' => ['Lavash Lab', 'LL', '#2563EB'], 'shirin' => ['Shirin Bar', 'SB', '#DB2777']] as $key => [$name, $code, $color]) {
            $brands[$key] = Brand::create(['store_id' => $store->id, 'name' => $name, 'short_code' => $code, 'color' => $color, 'is_active' => true, 'sort_order' => count($brands)]);
        }
        BrandChannel::create(['store_id' => $store->id, 'brand_id' => $brands['burger']->id, 'channel' => 'aggregator', 'is_hidden' => false, 'price_percent' => '15']);
        BrandChannel::create(['store_id' => $store->id, 'brand_id' => $brands['lavash']->id, 'channel' => 'mobile', 'is_hidden' => false, 'price_percent' => '10']);
        BrandChannel::create(['store_id' => $store->id, 'brand_id' => $brands['shirin']->id, 'channel' => 'mobile', 'is_hidden' => true, 'price_percent' => null]);

        $stations = [];
        $categories = [];
        foreach (['burger' => ['Grill', 'grill', 'Burgerlar'], 'lavash' => ['Lavash sexi', 'kitchen', 'Lavashlar'], 'shirin' => ['Desert', 'other', 'Desertlar']] as $key => [$name, $kind, $category]) {
            $stations[$key] = KitchenStation::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => $name, 'kind' => $kind, 'sort_order' => count($stations) + 1, 'show_on_kds' => true]);
            $categories[$key] = Category::create(['name' => $category, 'slug' => 'pos-e2e-dk-' . $key, 'is_active' => true]);
            KitchenStationRoute::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'station_id' => $stations[$key]->id, 'category_id' => $categories[$key]->id]);
        }
        $make = function (string $brand, string $name, string $mxik, string $price) use ($store, $categories, $brands): ProductVariant {
            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $categories[$brand]->id,
                'brand_id' => $brands[$brand]->id,
                'name' => $name,
                'slug' => 'pos-e2e-dk-' . mb_strtolower(preg_replace('/\W+/', '-', $name)),
                'status' => 'active',
                'mxik_code' => $mxik,
            ]);

            return ProductVariant::create(['product_id' => $product->id, 'name' => 'Porsiya', 'price' => $price, 'stock' => 100000, 'is_active' => true]);
        };
        $menu = [
            'Chiken burger' => $make('burger', 'Chiken burger', '10202001020000061', '45000'),
            'Double burger' => $make('burger', 'Double burger', '10202001020000062', '55000'),
            'Fri' => $make('burger', 'Fri', '10202001020000063', '15000'),
            'Tovuqli lavash' => $make('lavash', 'Tovuqli lavash', '10202001020000064', '35000'),
            "Go'shtli lavash" => $make('lavash', "Go'shtli lavash", '10202001020000065', '40000'),
            'Tovuqli shaurma' => $make('lavash', 'Tovuqli shaurma', '10202001020000066', '32000'),
            'Cheesecake' => $make('shirin', 'Cheesecake', '10202001020000067', '28000'),
            'Milkshake' => $make('shirin', 'Milkshake', '10202001020000068', '22000'),
        ];

        // "Tovuq" closes the chicken dishes of two brands; "Pishloq" only marks its dishes (not critical)
        $chicken = Ingredient::create(['store_id' => $store->id, 'name' => 'Tovuq', 'unit' => 'kg']);
        $chicken->products()->attach(array_fill_keys(array_map(fn (string $dish) => (int) $menu[$dish]->product_id, ['Chiken burger', 'Tovuqli lavash', 'Tovuqli shaurma']), ['is_critical' => true]));
        $cheese = Ingredient::create(['store_id' => $store->id, 'name' => 'Pishloq', 'unit' => 'kg']);
        $cheese->products()->attach(array_fill_keys(array_map(fn (string $dish) => (int) $menu[$dish]->product_id, ['Double burger', 'Cheesecake']), ['is_critical' => false]));
        // The aggregator (Yandex Eats) takes 25 %; Telegram and the mobile app pay RIZQIM's commission (the order's own)
        ChannelCommission::create(['store_id' => $store->id, 'channel' => 'aggregator', 'percent' => '25', 'note' => 'Yandex Eats']);

        $customer = User::create(['name' => 'Laylo Mijoz', 'phone' => '+998901000109', 'password' => bcrypt('pos-e2e-customer-not-used'), 'telegram_id' => '910000109']);
        // The same customer signed in on the mobile app: a token bound to a customer_mobile device is the `mobile`
        // channel of POST /orders (the Mini App's token has no device: `telegram`)
        $phone = \App\Models\Device::create(['user_id' => $customer->id, 'type' => DeviceType::CUSTOMER_MOBILE->value, 'name' => 'Mijoz ilovasi', 'platform' => 'android', 'last_seen_at' => now()]);
        $mobileToken = app(\App\Services\Auth\TokenService::class)->createDeviceToken($customer, $phone, \App\Services\Auth\TokenService::ACCESS_TOKEN_NAME, ['*'], now()->addDay())->plainTextToken;

        // Two aggregator orders of today, delivered (no aggregator API yet: the checkout action with that channel).
        // Nothing listens while the seeder runs: no broadcasts
        $broadcaster = config('broadcasting.default');
        config(['broadcasting.default' => 'null']);
        $aggregator = [];
        foreach ([[['Double burger', 2], ['Milkshake', 1]], [["Go'shtli lavash", 1], ['Chiken burger', 1]]] as $lines) {
            $order = app(\App\Actions\Order\CreateOrderAction::class)->execute(
                $customer,
                array_map(fn (array $line) => ['variant_id' => $menu[$line[0]]->uuid, 'quantity' => $line[1]], $lines),
                ['store_id' => $store->id, 'branch_id' => $branch->id, 'type' => 'pickup', 'payment_method' => 'cash', 'channel' => 'aggregator', 'notes' => 'Yandex Eats'],
            );
            $order->forceFill(['status' => \App\Enums\OrderStatus::DELIVERED->value])->save();
            $aggregator[] = ['uuid' => (string) $order->uuid, 'total' => Money::of($order->total_price)->toString(), 'discount' => Money::of($order->discount ?? '0')->toString()];
        }
        config(['broadcasting.default' => $broadcaster]);

        $pairing = app(DevicePairingService::class);
        $terminals = [];
        foreach (['till' => [DeviceType::POS, 'Kassa'], 'kds' => [DeviceType::KDS, 'Oshxona ekrani']] as $key => [$type, $name]) {
            $code = $pairing->createCode($store, $branch, $type, $name, $staff['owner'])['code'];
            $paired = $pairing->pair($code, ['platform' => 'linux']);
            $terminals[$key] = [
                'token' => $paired['token'],
                'device' => ['uuid' => $paired['device']->uuid, 'type' => $type->value, 'name' => $name, 'platform' => 'linux', 'app_version' => null, 'store_id' => $store->id, 'branch_id' => $branch->id],
                'store' => ['id' => $store->id, 'name' => $store->name],
                'branch' => ['id' => $branch->id, 'name' => $branch->name],
            ];
        }

        $seedStaff = [];
        foreach ($staff as $key => $user) {
            $seedStaff[$key] = ['id' => $user->id, 'name' => $user->name, 'pin' => $pins[$key] ?? null];
        }
        $seedStaff['owner']['token'] = $staff['owner']->createToken('pos-e2e-darkkitchen-owner')->plainTextToken;

        return [
            'store' => ['id' => $store->id, 'name' => $store->name, 'tin' => self::DARK_KITCHEN_TIN],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'staff' => $seedStaff,
            'terminals' => $terminals,
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'token' => $customer->createToken('pos-e2e-darkkitchen')->plainTextToken, 'mobile_token' => $mobileToken],
            'brands' => array_map(fn (Brand $brand) => ['id' => (int) $brand->id, 'name' => $brand->name, 'code' => $brand->short_code, 'color' => $brand->color], $brands),
            'products' => array_map(fn (ProductVariant $variant) => ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id], $menu),
            'stations' => array_map(fn (KitchenStation $station) => (int) $station->id, $stations),
            'ingredients' => ['chicken' => ['id' => (int) $chicken->id, 'name' => $chicken->name], 'cheese' => ['id' => (int) $cheese->id, 'name' => $cheese->name]],
            'aggregator_orders' => $aggregator,
            'business_date' => now('Asia/Tashkent')->toDateString(),
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
