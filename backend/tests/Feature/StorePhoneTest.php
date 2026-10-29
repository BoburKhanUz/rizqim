<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramRequest;
use App\Models\District;
use App\Models\Region;
use App\Services\Admin\Stores\StoreProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The store's own phone: every form that asks for it saves it (it used to be dropped for want of a column), and the
 * places that show the store's contact details show it.
 */
class StorePhoneTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private Region $region;
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:store-phone']);
        Queue::fake([SendTelegramRequest::class]);

        $this->setUpLifecycleStore();
        $this->region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $this->district = District::create(['region_id' => $this->region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
    }

    public function test_a_store_created_through_the_provisioner_keeps_its_phone(): void
    {
        $store = app(StoreProvisioner::class)->create([
            'name' => 'Wizard Cafe',
            'phone' => '+998712003344',
            'owner_name' => 'Wizard Owner',
            'owner_phone' => '+998901110002',
            'branch_region_id' => $this->region->id,
            'branch_district_id' => $this->district->id,
            'branch_address' => 'Bunyodkor 1',
            'branch_lat' => 41.3,
            'branch_lng' => 69.2,
        ])['store'];

        $this->assertSame('+998712003344', $store->fresh()->phone);
    }

    public function test_the_owner_settings_api_reads_and_updates_it_and_keeps_it_when_left_out(): void
    {
        $this->store->update(['phone' => '+998712001100']);
        $settings = ['is_open' => true, 'payment_settings' => ['delivery_fee' => 0]];

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/settings')
            ->assertOk()
            ->assertJsonPath('data.phone', '+998712001100');

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $settings + ['phone' => '+998712002200'])->assertOk();
        $this->assertSame('+998712002200', $this->store->fresh()->phone);

        // A client that does not send the phone (the settings screen before it had the field) keeps it
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $settings)->assertOk();
        $this->assertSame('+998712002200', $this->store->fresh()->phone);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $settings + ['phone' => null])->assertOk();
        $this->assertNull($this->store->fresh()->phone);
    }

    public function test_customers_see_it_in_the_store_list_and_the_catalog(): void
    {
        $this->store->update(['phone' => '+998712004455']);

        $stores = collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/stores')->assertOk()->json('data'))->keyBy('uuid');
        $this->assertSame('+998712004455', $stores[$this->store->uuid]['phone']);

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog?store_id=' . $this->store->uuid)
            ->assertOk()
            ->assertJsonPath('data.store.phone', '+998712004455');
    }
}
