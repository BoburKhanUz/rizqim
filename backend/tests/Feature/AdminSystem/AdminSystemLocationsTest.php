<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-31 Tizim → Viloyatlar va tumanlar: create, rename, switch, order, delete; never deleting one branches, addresses
 * or applications use.
 */
class AdminSystemLocationsTest extends TestCase
{
    use RefreshDatabase;
    use AdminSystemFixtures;

    private Region $tashkent;

    private Region $andijon;

    private District $chilonzor;

    private District $yunusobod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tashkent = Region::forceCreate(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $this->andijon = Region::forceCreate(['name_uz' => 'Andijon viloyati', 'is_active' => false]);
        $this->chilonzor = District::forceCreate(['region_id' => $this->tashkent->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        $this->yunusobod = District::forceCreate(['region_id' => $this->tashkent->id, 'name_uz' => 'Yunusobod tumani', 'is_active' => false]);

        $store = Store::forceCreate(['name' => 'Chilonzor Cafe']);
        Branch::create(['store_id' => $store->id, 'name' => 'Chilonzor', 'region_id' => $this->tashkent->id, 'district_id' => $this->chilonzor->id]);
        $customer = User::create(['name' => 'Customer', 'phone' => '+998901234567', 'password' => bcrypt('password')]);
        Address::create(['user_id' => $customer->id, 'title' => 'Uy', 'address' => 'Chilonzor 9', 'region_id' => $this->tashkent->id, 'district_id' => $this->chilonzor->id]);
    }

    public function test_the_list_orders_by_sort_order_then_name_and_says_what_uses_each(): void
    {
        $regions = $this->adminJson('GET', '/system/regions', $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)))->assertOk()->json('data');

        $this->assertSame(['Andijon viloyati', 'Toshkent shahri'], array_column($regions, 'name'));
        $tashkent = $regions[1];
        $this->assertSame([2, 1, ['branches' => 1, 'addresses' => 1, 'applications' => 0], false], [$tashkent['districts_count'], $tashkent['active_districts'], $tashkent['usage'], $tashkent['deletable']]);
        $this->assertSame(['Chilonzor tumani', 'Yunusobod tumani'], array_column($tashkent['districts'], 'name'));
        $this->assertSame([['branches' => 1, 'addresses' => 1], false], [$tashkent['districts'][0]['usage'], $tashkent['districts'][0]['deletable']]);
        $this->assertTrue($tashkent['districts'][1]['deletable']);
        $this->assertTrue($regions[0]['deletable']);

        $this->adminJson('GET', '/system/regions', $this->signIn($this->makeMember(PlatformRole::SALES)))->assertForbidden();
    }

    public function test_regions_are_created_renamed_and_switched(): void
    {
        $this->assertGuardedWrite('POST', '/system/regions', ['name' => 'Xorazm viloyati', 'reason' => 'Yangi hudud']);

        $created = $this->adminJson('POST', '/system/regions', $this->superToken(), ['name' => ' Xorazm viloyati ', 'is_active' => true, 'reason' => 'Yangi hudud'])
            ->assertCreated()->assertJsonPath('data.name', 'Xorazm viloyati')->assertJsonPath('data.is_active', true)->json('data');
        // Goes last
        $this->assertSame(1, $created['sort_order']);
        $this->assertSame(['name' => 'Xorazm viloyati', 'is_active' => true], AuditLog::where('action', 'admin.system.region_created')->sole()->after);

        $this->adminJson('POST', '/system/regions', $this->superToken(), ['name' => 'toshkent SHAHRI', 'reason' => 'Takror'])->assertStatus(422)->assertJsonPath('code', 'name_taken');
        $this->adminJson('POST', '/system/regions', $this->superToken(), ['name' => 'X', 'reason' => 'Qisqa'])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->adminJson('PATCH', "/system/regions/{$this->andijon->id}", $this->superToken(), ['is_active' => true, 'name' => 'Andijon', 'reason' => 'Ishga tushdi'])
            ->assertOk()->assertJsonPath('data.is_active', true)->assertJsonPath('data.name', 'Andijon');
        $audit = AuditLog::where('action', 'admin.system.region_updated')->sole();
        $this->assertSame([['name' => 'Andijon viloyati', 'is_active' => false], ['name' => 'Andijon', 'is_active' => true]], [$audit->before, $audit->after]);
        $this->adminJson('PATCH', "/system/regions/{$this->andijon->id}", $this->superToken(), ['is_active' => true, 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.region_updated')->count());
        $this->adminJson('PATCH', '/system/regions/999999', $this->superToken(), ['is_active' => true, 'reason' => 'Yana'])->assertNotFound();
    }

    public function test_a_region_or_district_in_use_is_never_deleted(): void
    {
        $token = $this->superToken();

        $this->adminJson('DELETE', "/system/regions/{$this->tashkent->id}", $token, ['reason' => 'Kerak emas'])->assertStatus(422)->assertJsonPath('code', 'has_districts')->assertJsonPath('data.districts', 2);
        $this->adminJson('DELETE', "/system/districts/{$this->chilonzor->id}", $token, ['reason' => 'Kerak emas'])->assertStatus(422)->assertJsonPath('code', 'in_use')
            ->assertJsonPath('data.usage', ['branches' => 1, 'addresses' => 1]);
        $this->assertTrue(District::whereKey($this->chilonzor->id)->exists());

        // An unused district goes; a region whose last district is used still cannot
        $this->assertGuardedWrite('DELETE', "/system/districts/{$this->yunusobod->id}", ['reason' => 'Takror tuman']);
        $this->adminJson('DELETE', "/system/districts/{$this->yunusobod->id}", $token, ['reason' => 'Takror tuman'])->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertFalse(District::whereKey($this->yunusobod->id)->exists());
        $this->assertSame(['name' => 'Yunusobod tumani'], AuditLog::where('action', 'admin.system.district_deleted')->sole()->before);

        // A region with no districts but a branch in it
        $samarqand = Region::forceCreate(['name_uz' => 'Samarqand viloyati', 'is_active' => true]);
        Branch::create(['store_id' => Store::forceCreate(['name' => 'Samarqand Osh'])->id, 'name' => 'Markaz', 'region_id' => $samarqand->id]);
        $this->adminJson('DELETE', "/system/regions/{$samarqand->id}", $token, ['reason' => 'Kerak emas'])->assertStatus(422)->assertJsonPath('code', 'in_use')->assertJsonPath('data.usage.branches', 1);

        $this->adminJson('DELETE', "/system/regions/{$this->andijon->id}", $token, ['reason' => 'Kerak emas'])->assertOk();
        $this->assertFalse(Region::whereKey($this->andijon->id)->exists());
        $this->assertSame(1, AuditLog::where('action', 'admin.system.region_deleted')->count());
    }

    public function test_the_order_is_the_full_list_and_reaches_the_mini_app(): void
    {
        $token = $this->superToken();
        $this->assertGuardedWrite('PUT', '/system/regions/order', ['ids' => [$this->tashkent->id, $this->andijon->id], 'reason' => 'Poytaxt birinchi']);

        $this->adminJson('PUT', '/system/regions/order', $token, ['ids' => [$this->tashkent->id], 'reason' => 'Chala'])->assertStatus(422)->assertJsonPath('code', 'order_mismatch');
        $this->adminJson('PUT', '/system/regions/order', $token, ['ids' => [$this->tashkent->id, $this->tashkent->id], 'reason' => 'Takror'])->assertStatus(422)->assertJsonValidationErrors('ids.0');

        $regions = $this->adminJson('PUT', '/system/regions/order', $token, ['ids' => [$this->tashkent->id, $this->andijon->id], 'reason' => 'Poytaxt birinchi'])->assertOk()->json('data');
        $this->assertSame(['Toshkent shahri', 'Andijon viloyati'], array_column($regions, 'name'));
        $audit = AuditLog::where('action', 'admin.system.regions_ordered')->sole();
        $this->assertSame([['order' => [$this->andijon->id, $this->tashkent->id]], ['order' => [$this->tashkent->id, $this->andijon->id]]], [$audit->before, $audit->after]);
        // The same order again writes nothing
        $this->adminJson('PUT', '/system/regions/order', $token, ['ids' => [$this->tashkent->id, $this->andijon->id], 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.regions_ordered')->count());

        $region = $this->adminJson('PUT', "/system/regions/{$this->tashkent->id}/districts/order", $token, ['ids' => [$this->yunusobod->id, $this->chilonzor->id], 'reason' => 'Markaz birinchi'])->assertOk()->json('data');
        $this->assertSame(['Yunusobod tumani', 'Chilonzor tumani'], array_column($region['districts'], 'name'));
        $this->adminJson('PUT', "/system/regions/{$this->andijon->id}/districts/order", $token, ['ids' => [$this->chilonzor->id], 'reason' => 'Boshqa viloyat'])->assertStatus(422)->assertJsonPath('code', 'order_mismatch');

        // The Mini App's list follows the order
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $locations = $this->actingAs(User::where('phone', '+998901234567')->sole(), 'sanctum')->getJson('/api/v1/locations')->assertOk()->json('data');
        $this->assertSame(['Toshkent shahri', 'Andijon viloyati'], array_column($locations['regions'], 'name'));
        $this->assertSame(['Yunusobod tumani', 'Chilonzor tumani'], array_column($locations['districts'], 'name'));
    }

    public function test_districts_are_added_renamed_and_moved_when_unused(): void
    {
        $token = $this->superToken();
        $this->assertGuardedWrite('POST', "/system/regions/{$this->tashkent->id}/districts", ['name' => 'Mirobod tumani', 'reason' => "Qo'shildi"]);

        $district = $this->adminJson('POST', "/system/regions/{$this->tashkent->id}/districts", $token, ['name' => 'Mirobod tumani', 'is_active' => true, 'reason' => "Qo'shildi"])
            ->assertCreated()->assertJsonPath('data.region_id', $this->tashkent->id)->json('data');
        $this->adminJson('POST', "/system/regions/{$this->tashkent->id}/districts", $token, ['name' => 'mirobod tumani', 'reason' => 'Takror'])->assertStatus(422)->assertJsonPath('code', 'name_taken');
        // The same name in another region is fine
        $this->adminJson('POST', "/system/regions/{$this->andijon->id}/districts", $token, ['name' => 'Mirobod tumani', 'reason' => 'Boshqa viloyat'])->assertCreated();

        $this->adminJson('PATCH', "/system/districts/{$district['id']}", $token, ['region_id' => $this->andijon->id, 'reason' => "Ko'chdi"])->assertStatus(422)->assertJsonPath('code', 'name_taken');
        $this->adminJson('PATCH', "/system/districts/{$district['id']}", $token, ['name' => 'Mirobod', 'region_id' => $this->andijon->id, 'reason' => "Ko'chdi"])
            ->assertOk()->assertJsonPath('data.region_id', $this->andijon->id)->assertJsonPath('data.name', 'Mirobod');
        $audit = AuditLog::where('action', 'admin.system.district_updated')->sole();
        $this->assertSame([['name' => 'Mirobod tumani', 'region_id' => $this->tashkent->id], ['name' => 'Mirobod', 'region_id' => $this->andijon->id]], [$audit->before, $audit->after]);

        // A district branches use stays in its region
        $this->adminJson('PATCH', "/system/districts/{$this->chilonzor->id}", $token, ['region_id' => $this->andijon->id, 'reason' => "Ko'chdi"])->assertStatus(422)->assertJsonPath('code', 'in_use');
        $this->adminJson('PATCH', "/system/districts/{$this->chilonzor->id}", $token, ['is_active' => false, 'reason' => "Vaqtincha yo'q"])->assertOk()->assertJsonPath('data.is_active', false);
        $this->adminJson('PATCH', "/system/districts/{$this->yunusobod->id}", $token, ['region_id' => 999999, 'reason' => 'Yo\'q viloyat'])->assertStatus(422)->assertJsonPath('code', 'region_not_found');
    }
}
