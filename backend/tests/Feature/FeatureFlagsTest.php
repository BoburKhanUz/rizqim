<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Features\Features;
use App\Filament\Superadmin\Pages\FeatureFlags;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Store $otherStore;
    private User $owner;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Pilot Store', 'slug' => 'pilot-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->otherStore = Store::create(['name' => 'Other Store', 'slug' => 'other-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->owner = User::create(['name' => 'Owner', 'telegram_id' => '6000001', 'store_id' => $this->store->id, 'role' => UserRole::OWNER]);
        $this->superAdmin = User::create(['name' => 'Platform Admin', 'email' => 'root@rizqim.uz', 'password' => Hash::make('a-strong-password'), 'role' => UserRole::SUPER_ADMIN]);
        // P4-11: the superadmin panel requires 2FA
        $this->superAdmin->saveAppAuthenticationSecret(app(\App\Services\Admin\TwoFactorService::class)->generateSecret());
    }

    public function test_flag_is_off_by_default_and_can_be_enabled_for_one_store_only(): void
    {
        $this->assertFalse(Feature::for($this->store)->active(Features::NEW_CHECKOUT_COPY));

        Feature::for($this->store)->activate(Features::NEW_CHECKOUT_COPY);

        $this->assertTrue(Feature::for($this->store)->active(Features::NEW_CHECKOUT_COPY));
        $this->assertFalse(Feature::for($this->otherStore)->active(Features::NEW_CHECKOUT_COPY));
        $this->assertDatabaseHas('features', ['name' => Features::NEW_CHECKOUT_COPY, 'scope' => Store::class . '|' . $this->store->id]);
    }

    public function test_default_scope_is_the_signed_in_users_store(): void
    {
        Feature::for($this->store)->activate(Features::NEW_CHECKOUT_COPY);

        $this->actingAs($this->owner);

        $this->assertTrue(Feature::active(Features::NEW_CHECKOUT_COPY));
    }

    public function test_api_returns_the_flags_of_the_users_store(): void
    {
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/features')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['features' => [Features::NEW_CHECKOUT_COPY => false]]]);

        Feature::for($this->store)->activate(Features::NEW_CHECKOUT_COPY);

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/features')
            ->assertOk()
            ->assertJsonPath('data.features.' . Features::NEW_CHECKOUT_COPY, true);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/features')->assertUnauthorized();
    }

    public function test_superadmin_toggles_a_flag_for_a_store_in_the_panel(): void
    {
        Filament::setCurrentPanel('superadmin');
        $this->actingAs($this->superAdmin);
        $column = 'flag_' . str_replace('-', '_', Features::NEW_CHECKOUT_COPY);

        Livewire::test(FeatureFlags::class)
            ->assertSee('Pilot Store')
            ->assertTableColumnStateSet($column, false, $this->store)
            ->call('updateTableColumnState', $column, (string) $this->store->getKey(), true);

        $this->assertTrue(Feature::for($this->store)->active(Features::NEW_CHECKOUT_COPY));
        $this->assertFalse(Feature::for($this->otherStore)->active(Features::NEW_CHECKOUT_COPY));

        Livewire::test(FeatureFlags::class)
            ->callTableAction('reset', $this->store);

        $this->assertDatabaseMissing('features', ['name' => Features::NEW_CHECKOUT_COPY, 'scope' => Store::class . '|' . $this->store->id, 'value' => 'true']);
        $this->assertFalse(Feature::for($this->store)->active(Features::NEW_CHECKOUT_COPY));
    }

    public function test_panel_page_is_superadmin_only(): void
    {
        $this->actingAs($this->owner)->get('/superadmin/feature-flags')->assertForbidden();
        $this->actingAs($this->superAdmin)->get('/superadmin/feature-flags')->assertOk()->assertSee('Feature flaglar');
    }
}
