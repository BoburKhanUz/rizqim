<?php

namespace Tests\Feature\AdminStores;

use App\Enums\CommissionChannel;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\CreateGlobalStore;
use App\Jobs\SendTelegramRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\Finance\Billing\CommissionPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P4-22 + P4-21: the one store creation path (StoreProvisioner: the Filament wizard and "convert an application") writes
 * no flat commission rate unless one is typed, so the per-channel policy (RIZQIM courier 15%, own delivery 10%, pickup
 * 5%, hall 0%) applies.
 */
class StoreProvisionerCommissionTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    private function wizard(array $overrides = []): Store
    {
        config(['services.telegram.bot_token' => '123456:create-store', 'services.telegram.bot_username' => 'rizqim_test_bot']);
        Queue::fake([SendTelegramRequest::class]);
        Http::fake();
        $admin = User::forceCreate(['name' => 'Super Admin', 'role' => UserRole::SUPER_ADMIN->value, 'telegram_id' => '777002', 'password' => bcrypt('password')]);
        $region = $this->region();
        $district = $this->district($region);

        $this->actingAs($admin);
        Filament::setCurrentPanel('superadmin');
        Livewire::test(CreateGlobalStore::class)
            ->fillForm(array_merge([
                'name' => 'Wizard Uch',
                'commission_rate' => null,
                'payment_account_type' => 'platform_account',
                'owner_name' => 'Ega',
                'owner_phone' => '+998901110077',
                'owner_password' => 'secret123',
                'branch_region_id' => $region->id,
                'branch_district_id' => $district->id,
                'branch_address' => 'Bunyodkor 1',
                'branch_radius' => 5,
                'branch_lat' => 41.3,
                'branch_lng' => 69.2,
            ], $overrides))
            ->call('create')
            ->assertHasNoFormErrors();

        return Store::where('name', $overrides['name'] ?? 'Wizard Uch')->sole();
    }

    public function test_the_wizard_without_a_rate_leaves_it_null_and_the_channel_policy_applies(): void
    {
        $store = $this->wizard();

        $this->assertNull($store->commission_rate);
        $policy = app(CommissionPolicy::class);
        $this->assertEquals(15, (float) $policy->rateFor($store, CommissionChannel::RIZQIM_COURIER));
        $this->assertEquals(10, (float) $policy->rateFor($store, CommissionChannel::OWN_DELIVERY));
        $this->assertEquals(5, (float) $policy->rateFor($store, CommissionChannel::PICKUP));
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => str_contains($job->params['text'], "Komissiya: <b>kanal bo'yicha (15/10/5/0%)</b>"));
    }

    public function test_a_typed_rate_is_a_flat_rate(): void
    {
        $store = $this->wizard(['name' => 'Wizard Tort', 'commission_rate' => 12, 'owner_phone' => '+998901110078']);

        $this->assertSame('12.00', (string) $store->commission_rate);
    }

    public function test_converting_an_application_leaves_the_rate_null(): void
    {
        Queue::fake([SendTelegramRequest::class]);
        $region = $this->region();
        $district = $this->district($region);
        $application = $this->makeApplication(['region_id' => $region->id]);
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SALES));

        $storeId = $this->adminJson('POST', "/store-applications/{$application->uuid}/convert", $token, [
            'branch_district_id' => $district->id,
            'branch_address' => 'Chilonzor 9',
            'branch_lat' => 41.28,
            'branch_lng' => 69.2,
            'reason' => 'Hujjatlar tekshirildi',
        ])->assertCreated()->json('data.store_id');

        $this->assertNull(Store::findOrFail($storeId)->commission_rate);
        // The card shows the channel rates P4-21 works out
        $card = $this->adminJson('GET', "/stores/{$storeId}", $token)->assertOk();
        $this->assertSame('billing_v2', $card->json('data.plan.source'));
        $this->assertNull($card->json('data.plan.commission.flat_rate'));
    }
}
