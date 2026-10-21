<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\StaffPhoneLoginService;
use App\Services\Auth\StaffPinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * P2-31: the data the cross-app end-to-end test (scripts/e2e-stack.sh, e2e/stack/) runs against: one store with a
 * branch, an owner, a manager, a cook with a PIN, a customer, a stocked product and a KDS terminal that is already
 * paired. Test-only: it refuses to run in any environment but local, testing and e2e, so it can never put known PINs
 * and phones into a staging or production database. The ids, phones and the terminal's token the test needs are
 * written as JSON to E2E_SEED_FILE (default storage/app/e2e-seed.json).
 *
 *   APP_ENV=e2e php artisan migrate:fresh --force && APP_ENV=e2e php artisan db:seed --class=E2EStackSeeder --force
 */
class E2EStackSeeder extends Seeder
{
    public const OWNER_PHONE = '+998901000001';
    public const MANAGER_PHONE = '+998901000002';
    public const COOK_PHONE = '+998901000003';
    public const CUSTOMER_PHONE = '+998901000009';
    public const COOK_PIN = '4831';

    public function run(): void
    {
        if (!app()->environment(['local', 'testing', 'e2e'])) {
            throw new RuntimeException('E2EStackSeeder is for test databases only (APP_ENV local, testing or e2e).');
        }

        $store = Store::forceCreate([
            'name' => 'E2E Lavash',
            'slug' => 'e2e-lavash-' . Str::random(5),
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'delivery_fee' => 0,
            ],
        ]);

        $branch = Branch::create([
            'store_id' => $store->id,
            'name' => 'Chilonzor',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_km' => 10,
            'status' => BranchStatus::ACTIVE->value,
        ]);

        $owner = $this->staff($store, UserRole::OWNER, null, 'Olim Egamov', self::OWNER_PHONE, 910000001);
        $manager = $this->staff($store, UserRole::MANAGER, $branch, 'Malika Menejer', self::MANAGER_PHONE, 910000002);
        $cook = $this->staff($store, UserRole::COOK, $branch, 'Sanjar Oshpaz', self::COOK_PHONE, 910000003);
        app(StaffPinService::class)->setPin($cook, self::COOK_PIN, null);

        $customer = User::create([
            'name' => 'Aziz Mijoz',
            'phone' => self::CUSTOMER_PHONE,
            'password' => bcrypt(Str::random(32)),
            'telegram_id' => '910000009',
        ]);

        $category = Category::create(['name' => 'Lavash', 'slug' => 'lavash-' . Str::random(5), 'is_active' => true]);
        $variants = [];
        foreach ([['Lavash', 'Standart', 25000], ['Cola', '0.5 l', 8000]] as [$name, $variantName, $price]) {
            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . Str::random(5),
                'status' => 'active',
            ]);
            $variants[$name] = ProductVariant::create([
                'product_id' => $product->id,
                'name' => $variantName,
                'price' => $price,
                'stock' => 100,
                'is_active' => true,
                'uuid' => (string) Str::uuid(),
            ]);
        }

        // The kitchen display, paired the way a tablet does it (a code from the owner, then POST /devices/pair)
        $pairing = app(DevicePairingService::class);
        $code = $pairing->createCode($store, $branch, DeviceType::KDS, 'Oshxona', $owner)['code'];
        $terminal = $pairing->pair($code, ['platform' => 'android']);

        File::ensureDirectoryExists(dirname($this->seedFile()));
        File::put($this->seedFile(), json_encode([
            'store' => ['id' => $store->id, 'uuid' => $store->uuid, 'name' => $store->name],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'owner' => ['id' => $owner->id, 'phone' => self::OWNER_PHONE, 'telegram_id' => $owner->telegram_id],
            'manager' => ['id' => $manager->id, 'name' => $manager->name, 'phone' => self::MANAGER_PHONE, 'telegram_id' => $manager->telegram_id],
            'cook' => ['id' => $cook->id, 'name' => $cook->name, 'phone' => self::COOK_PHONE, 'pin' => self::COOK_PIN],
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'phone' => self::CUSTOMER_PHONE, 'telegram_id' => $customer->telegram_id],
            'products' => collect($variants)->map(fn (ProductVariant $variant, string $name) => [
                'name' => $name,
                'variant_uuid' => $variant->uuid,
                'price' => (string) $variant->price,
            ])->values()->all(),
            'terminal' => ['uuid' => $terminal['device']->uuid, 'token' => $terminal['token']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function seedFile(): string
    {
        // getenv(), not env(): this is a script's own switch, not application configuration
        return (string) (getenv('E2E_SEED_FILE') ?: storage_path('app/e2e-seed.json'));
    }

    // A member of staff who can sign in by SMS code with the phone (a vouched login phone) and from the Mini App
    private function staff(Store $store, UserRole $role, ?Branch $branch, string $name, string $phone, int $telegramId): User
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => bcrypt(Str::random(32)),
            'store_id' => $store->id,
            'branch_id' => $branch?->id,
            'role' => $role->value,
            'telegram_id' => (string) $telegramId,
        ]);
        app(StaffPhoneLoginService::class)->vouch($user, $phone);

        return $user->refresh();
    }
}
