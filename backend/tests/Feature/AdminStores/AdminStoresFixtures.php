<?php

namespace Tests\Feature\AdminStores;

use App\Enums\StoreApplicationStage;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\User;
use Tests\Feature\AdminFixtures;

/**
 * P4-22: the admin API fixtures (members, sign-in, step-up) plus stores with an owner and a branch in a region, and
 * applications at a given stage.
 */
trait AdminStoresFixtures
{
    use AdminFixtures;

    protected function region(string $name = 'Toshkent shahri'): Region
    {
        return Region::forceCreate(['name_uz' => $name, 'is_active' => true]);
    }

    protected function district(Region $region, string $name = 'Chilonzor tumani'): District
    {
        return District::forceCreate(['region_id' => $region->id, 'name_uz' => $name, 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeStore(array $attributes = [], ?Region $region = null): Store
    {
        static $sequence = 0;
        $sequence++;

        $store = new Store();
        $store->forceFill(array_merge([
            'name' => "Ovqatxona {$sequence}",
            'status' => StoreStatus::ACTIVE->value,
            'is_active' => true,
            'payment_settings' => Store::initialPaymentSettings(['cash']),
        ], $attributes))->save();

        Branch::forceCreate([
            'store_id' => $store->id,
            'name' => 'Asosiy Filial',
            'region_id' => $region?->id,
            'address' => 'Chilonzor 1',
            'latitude' => 41.28,
            'longitude' => 69.2,
            'radius_km' => 5,
            'is_active' => true,
        ]);

        return $store->refresh();
    }

    protected function makeOwner(Store $store, ?string $phone = null, ?string $telegramId = null): User
    {
        $owner = new User();
        $owner->forceFill([
            'name' => 'Ega ' . $store->id,
            'role' => UserRole::OWNER->value,
            'store_id' => $store->id,
            'phone' => $phone,
            'telegram_id' => $telegramId,
            'telegram_invite_token' => $telegramId === null ? 'tok-' . $store->id . '-' . uniqid() : null,
        ])->save();

        return $owner->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeApplication(array $attributes = [], StoreApplicationStage $stage = StoreApplicationStage::APPLICATION): StoreApplication
    {
        static $sequence = 0;
        $sequence++;

        return StoreApplication::create(array_merge([
            'name' => "Yangi kafe {$sequence}",
            'format' => 'cafe',
            'owner_name' => 'Aziz',
            'phone' => '+99890' . str_pad((string) (1000000 + $sequence), 7, '0', STR_PAD_LEFT),
            'source' => 'website',
            'stage' => $stage->value,
            'stage_changed_at' => now(),
            'due_on' => now('Asia/Tashkent')->addDay()->format('Y-m-d'),
        ], $attributes));
    }
}
