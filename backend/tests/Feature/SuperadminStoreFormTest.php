<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\EditGlobalStore;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The superadmin's store form: saving one field never changes what it did not show or the admin did not touch.
 */
class SuperadminStoreFormTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'click' => ['enabled' => true, 'merchant_id' => 'click-merchant', 'service_id' => 'click-service', 'secret_key' => 'click-SECRET'],
                'payme' => ['enabled' => false],
                'delivery_enabled' => false,
                'pickup_enabled' => true,
                'delivery_fee' => 12000,
            ],
        ])->save();

        $this->actingAs(User::create(['name' => 'Super Admin', 'phone' => '+998909990001', 'role' => UserRole::SUPER_ADMIN->value, 'password' => bcrypt('password')]));
        Filament::setCurrentPanel('superadmin');
    }

    public function test_saving_another_field_keeps_the_payment_methods_as_they_were(): void
    {
        Livewire::test(EditGlobalStore::class, ['record' => $this->store->getRouteKey()])
            // Ticked as stored; the page state holds no merchant secret
            ->assertSet('data.active_payment_methods', ['cash', 'click'])
            ->assertSet('data.payment_settings.click.secret_key', null)
            ->fillForm(['name' => 'Renamed Burger'])
            ->call('save')
            ->assertHasNoFormErrors();

        $store = $this->store->fresh();
        $this->assertSame('Renamed Burger', $store->name);
        $this->assertTrue($store->payment_settings['cash']['enabled']);
        $this->assertTrue($store->payment_settings['click']['enabled']);
        $this->assertFalse($store->payment_settings['payme']['enabled']);
        $this->assertSame('click-SECRET', $store->payment_settings['click']['secret_key']);
        $this->assertSame('click-merchant', $store->payment_settings['click']['merchant_id']);
        $this->assertFalse($store->payment_settings['delivery_enabled']);
        $this->assertEquals(12000, $store->payment_settings['delivery_fee']);
    }

    public function test_unticking_a_method_still_switches_only_that_one_off(): void
    {
        Livewire::test(EditGlobalStore::class, ['record' => $this->store->getRouteKey()])
            ->fillForm(['active_payment_methods' => ['cash']])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->store->fresh()->payment_settings;
        $this->assertTrue($settings['cash']['enabled']);
        $this->assertFalse($settings['click']['enabled']);
        $this->assertSame('click-SECRET', $settings['click']['secret_key']);
    }

    public function test_the_credit_limit_field_defaults_to_the_column_default(): void
    {
        // 0 used to be the form default, which (at the time) blocked a store with no debt at all
        Livewire::test(EditGlobalStore::class, ['record' => $this->store->getRouteKey()])
            ->assertFormFieldExists('credit_limit', checkFieldUsing: fn (TextInput $field) => (float) $field->getDefaultState() === -100000.0)
            ->assertSee('-100000 = 100 000 UZS gacha qarz');
    }
}
