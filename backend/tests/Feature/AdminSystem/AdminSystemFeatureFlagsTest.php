<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Features\Features;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Tests\TestCase;

/**
 * P4-31 Tizim → Funksiyalar: the Pennant flags per store, switched and reset as the old superadmin page did, audited.
 */
class AdminSystemFeatureFlagsTest extends TestCase
{
    use RefreshDatabase;
    use AdminSystemFixtures;

    private const FLAG = Features::NEW_CHECKOUT_COPY;

    private Store $pilot;

    private Store $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pilot = Store::create(['name' => 'Pilot Store', 'slug' => 'pilot-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->other = Store::create(['name' => 'Other Store', 'slug' => 'other-store', 'commission_rate' => 10, 'status' => 'active']);
    }

    public function test_the_flags_and_the_stores_are_listed_without_storing_anything(): void
    {
        Feature::for($this->pilot)->activate(self::FLAG);
        $token = $this->signIn($this->makeMember(PlatformRole::SUPERADMIN));

        $flag = $this->adminJson('GET', '/system/feature-flags', $token)->assertOk()->json('data.flags.0');
        $this->assertSame([self::FLAG, false, 1, 1, 2], [$flag['name'], $flag['default'], $flag['stores_on'], $flag['stores_off'], $flag['stores_total']]);
        $this->assertSame(Features::FLAGS[self::FLAG]['description'], $flag['description']);

        $page = $this->adminJson('GET', '/system/feature-flags/stores', $token)->assertOk()->json('data');
        $this->assertSame(['Other Store', 'Pilot Store'], array_column(array_column($page['data'], 'store'), 'name'));
        $this->assertSame(['active' => false, 'stored' => false], $page['data'][0]['flags'][self::FLAG]);
        $this->assertSame(['active' => true, 'stored' => true], $page['data'][1]['flags'][self::FLAG]);
        $this->assertSame(2, $page['total']);
        // Reading stored nothing for the other store
        $this->assertSame(1, DB::table('features')->count());

        $this->assertSame(['Pilot Store'], array_column(array_column($this->adminJson('GET', '/system/feature-flags/stores?q=pilot', $token)->json('data.data'), 'store'), 'name'));

        foreach ([PlatformRole::VIEWER, PlatformRole::OPERATOR, PlatformRole::SALES] as $role) {
            $this->adminJson('GET', '/system/feature-flags', $this->signIn($this->makeMember($role)))->assertForbidden();
        }
    }

    public function test_a_flag_is_switched_for_one_store_with_a_step_up_and_a_reason(): void
    {
        $uri = '/system/feature-flags/' . self::FLAG . "/stores/{$this->pilot->id}";
        $this->assertGuardedWrite('PUT', $uri, ['active' => true, 'reason' => 'Pilot ovqatxona']);
        $this->assertFalse(Feature::for($this->pilot)->active(self::FLAG));

        $this->adminJson('PUT', $uri, $this->superToken(), ['active' => true, 'reason' => 'Pilot ovqatxona'])->assertOk()
            ->assertJsonPath('data.flags.' . self::FLAG, ['active' => true, 'stored' => true])->assertJsonPath('message', 'Funksiya yoqildi.');
        Feature::flushCache();
        $this->assertTrue(Feature::for($this->pilot)->active(self::FLAG));
        $this->assertFalse(Feature::for($this->other)->active(self::FLAG));

        $audit = AuditLog::where('action', 'admin.system.feature_flag_set')->sole();
        $this->assertSame([['active' => false], ['active' => true], self::FLAG, $this->pilot->id, 'Pilot ovqatxona'], [$audit->before, $audit->after, $audit->payload_json['flag'], $audit->store_id, $audit->payload_json['reason']]);

        // Already on: nothing new in the audit
        $this->adminJson('PUT', $uri, $this->superToken(), ['active' => true, 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.feature_flag_set')->count());

        $this->adminJson('PUT', '/system/feature-flags/no-such-flag/stores/' . $this->pilot->id, $this->superToken(), ['active' => true, 'reason' => 'Sinov'])->assertNotFound();
        $this->adminJson('PUT', '/system/feature-flags/' . self::FLAG . '/stores/999999', $this->superToken(), ['active' => true, 'reason' => 'Sinov'])->assertNotFound();
        $this->adminJson('PUT', $uri, $this->superToken(), ['reason' => 'Sinov'])->assertStatus(422)->assertJsonValidationErrors('active');
    }

    public function test_a_store_goes_back_to_the_defaults(): void
    {
        Feature::for($this->pilot)->activate(self::FLAG);
        $uri = "/system/feature-flags/stores/{$this->pilot->id}";
        $this->assertGuardedWrite('DELETE', $uri, ['reason' => 'Pilot tugadi']);

        $this->adminJson('DELETE', $uri, $this->superToken(), ['reason' => 'Pilot tugadi'])->assertOk()->assertJsonPath('data.flags.' . self::FLAG, ['active' => false, 'stored' => false]);
        $this->assertSame(0, DB::table('features')->count());
        $this->assertSame([self::FLAG], AuditLog::where('action', 'admin.system.feature_flags_reset')->sole()->payload_json['flags']);

        // Nothing stored: nothing to audit
        $this->adminJson('DELETE', $uri, $this->superToken(), ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.system.feature_flags_reset')->count());
    }
}
