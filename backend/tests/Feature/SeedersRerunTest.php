<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Branch;
use App\Models\District;
use App\Models\PaymentProvider;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PaymentProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reference-data seeders are run again on live databases (deploys, new regions): a re-run only adds what is
 * missing and never deletes or overwrites what the platform already uses.
 */
class SeedersRerunTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerunning_the_location_seeder_keeps_branches_addresses_and_panel_switches(): void
    {
        $this->seed(LocationSeeder::class);
        $this->assertTrue(District::where('name_uz', 'Asaka tumani')->sole()->is_active);
        $this->assertFalse(Region::where('name_uz', 'Toshkent shahri')->sole()->is_active);

        // In use: switched on in the superadmin panel, a branch and a customer address in it
        $region = Region::where('name_uz', 'Toshkent shahri')->sole();
        $district = District::where('region_id', $region->id)->where('name_uz', 'Chilonzor tumani')->sole();
        $region->update(['is_active' => true]);
        $district->update(['is_active' => true]);
        $store = Store::forceCreate(['name' => 'Chilonzor Cafe']);
        $branch = Branch::create(['store_id' => $store->id, 'name' => 'Chilonzor', 'region_id' => $region->id, 'district_id' => $district->id]);
        $customer = User::create(['name' => 'Customer', 'phone' => '+998901234567', 'password' => bcrypt('password')]);
        $address = Address::create(['user_id' => $customer->id, 'title' => 'Uyim', 'address' => 'Chilonzor 9', 'region_id' => $region->id, 'district_id' => $district->id]);

        // A district missing from an older seed run comes back
        District::where('name_uz', 'Yangihayot tumani')->delete();
        $regionCount = Region::count();
        $districtCount = District::count();

        $this->seed(LocationSeeder::class);

        $this->assertSame($regionCount, Region::count());
        $this->assertSame($districtCount + 1, District::count());
        $this->assertSame($region->id, $branch->fresh()->region_id);
        $this->assertSame($district->id, $branch->fresh()->district_id);
        $this->assertSame($district->id, $address->fresh()->district_id);
        $this->assertTrue($region->fresh()->is_active);
        $this->assertTrue($district->fresh()->is_active);
        $this->assertTrue(District::where('name_uz', 'Asaka tumani')->sole()->is_active);
    }

    public function test_rerunning_the_payment_provider_seeder_keeps_entered_credentials(): void
    {
        $this->seed(PaymentProviderSeeder::class);
        $payme = PaymentProvider::where('slug', 'payme')->sole();
        $payme->update(['is_active' => false, 'credentials' => ['merchant_id' => 'live-merchant', 'secret_key' => 'live-secret', 'test_mode' => 'false']]);
        PaymentProvider::where('slug', 'paynet')->delete();

        $this->seed(PaymentProviderSeeder::class);

        $payme = $payme->fresh();
        $this->assertFalse((bool) $payme->is_active);
        $this->assertSame('live-merchant', $payme->credentials['merchant_id']);
        $this->assertSame('live-secret', $payme->credentials['secret_key']);
        $this->assertSame(4, PaymentProvider::count());
    }
}
