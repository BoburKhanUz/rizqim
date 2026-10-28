<?php

namespace Tests\Feature\AdminPos;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24 Qurilmalar: every store's devices with online state and outdated versions, and revoking with a reason.
 */
class AdminPosDevicesTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_the_list_marks_online_offline_never_seen_and_outdated_devices(): void
    {
        $online = $this->device(['name' => 'Online', 'last_seen_at' => now()->subMinutes(3), 'app_version' => '1.10.0']);
        $offline = $this->device(['name' => 'Offline', 'last_seen_at' => now()->subHours(2), 'app_version' => '1.9.0']);
        $never = $this->device(['name' => 'Never', 'last_seen_at' => null, 'app_version' => null]);
        $kds = $this->device(['name' => 'KDS', 'type' => 'kds', 'last_seen_at' => now()->subDays(2), 'app_version' => '2.0.1']);
        $revoked = $this->device(['name' => 'Revoked', 'last_seen_at' => now()->subDays(9), 'app_version' => '1.0.0']);
        $revoked->forceFill(['revoked_at' => now()])->save();
        $foreign = $this->device(['name' => 'Foreign', 'last_seen_at' => now()->subMinutes(1), 'app_version' => '1.10.0'], $this->foreignStore);
        // A person's own install with no store belongs to the customer sections
        $this->device(['name' => 'Phone', 'type' => 'customer_mobile', 'store_id' => null, 'branch_id' => null]);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $response = $this->adminJson('GET', '/pos/devices', $token)->assertOk();

        // The longest unseen first, never-seen last; revoked and store-less devices are out
        $this->assertSame(['KDS', 'Offline', 'Online', 'Foreign', 'Never'], array_column($response->json('data.data'), 'name'));
        $this->assertSame(['total' => 5, 'online' => 2, 'offline' => 2, 'never' => 1, 'revoked' => 1, 'outdated' => 1, 'by_type' => ['kds' => 1, 'pos' => 4]], $response->json('data.counts'));
        $this->assertSame(['kds' => '2.0.1', 'pos' => '1.10.0'], $response->json('data.latest_versions'));

        $rows = collect($response->json('data.data'))->keyBy('name');
        $this->assertSame(['offline', 'online', 'never', 'online'], [$rows['Offline']['state'], $rows['Online']['state'], $rows['Never']['state'], $rows['Foreign']['state']]);
        $this->assertSame([true, false, false, false], [$rows['Offline']['outdated'], $rows['Online']['outdated'], $rows['Never']['outdated'], $rows['KDS']['outdated']]);
        $this->assertSame(['1.10.0', 'Lifecycle Burger', 'Main Branch', 'Kassa (POS)'], [$rows['Offline']['latest_version'], $rows['Offline']['store']['name'], $rows['Offline']['branch']['name'], $rows['Offline']['type_label']]);
        $this->assertArrayNotHasKey('push_token', $rows['Online']);
        $this->assertArrayNotHasKey('id', $rows['Online']);

        $uuids = fn (string $query) => array_column($this->adminJson('GET', '/pos/devices' . $query, $token)->assertOk()->json('data.data'), 'name');
        $this->assertSame(['Online', 'Foreign'], $uuids('?state=online'));
        $this->assertSame(['KDS', 'Offline'], $uuids('?state=offline'));
        $this->assertSame(['Never'], $uuids('?state=never'));
        $this->assertSame(['Revoked'], $uuids('?state=revoked'));
        $this->assertSame(['Offline'], $uuids('?outdated=1'));
        $this->assertSame(['KDS'], $uuids('?type=kds'));
        $this->assertSame(['Foreign'], $uuids("?store_id={$this->foreignStore->id}"));
        $this->assertSame(['Foreign'], $uuids('?q=foreign'));
        $this->assertSame(['Foreign'], $uuids('?q=Foreign+Grill'));
        $this->assertSame(['Revoked'], array_slice($uuids('?state=all&q=revo'), 0, 1));
        $this->assertCount(6, $this->adminJson('GET', '/pos/devices?state=all', $token)->json('data.data'));
        $this->adminJson('GET', '/pos/devices?state=bogus', $token)->assertStatus(422);

        $this->adminJson('GET', "/pos/devices/{$offline->uuid}", $token)->assertOk()->assertJsonPath('data.name', 'Offline')->assertJsonPath('data.outdated', true);
        $this->adminJson('GET', "/pos/devices/{$revoked->uuid}", $token)->assertOk()->assertJsonPath('data.state', 'revoked');
        $this->adminJson('GET', '/pos/devices/' . \Illuminate\Support\Str::uuid(), $token)->assertNotFound()->assertJsonPath('code', 'not_found');
        $stranger = Device::where('name', 'Phone')->sole();
        $this->adminJson('GET', "/pos/devices/{$stranger->uuid}", $token)->assertNotFound();
    }

    public function test_the_reported_offline_queue_is_shown(): void
    {
        $device = $this->device();
        $device->forceFill(['sync_pending_ops' => 4, 'sync_oldest_op_at' => now()->subHour(), 'sync_reported_at' => now()->subMinutes(5), 'sync_clock_skew_seconds' => 900])->save();

        $row = $this->adminJson('GET', "/pos/devices/{$device->uuid}", $this->signIn($this->makeMember(PlatformRole::SALES)))->assertOk()->json('data.sync');
        $this->assertSame([4, 900, true], [$row['pending_ops'], $row['clock_skew_seconds'], $row['clock_warning']]);
    }

    public function test_revoking_needs_stores_manage_a_step_up_and_a_reason_and_is_audited(): void
    {
        $device = $this->device();

        // The operator sees devices but cannot revoke them
        $this->adminJson('POST', "/pos/devices/{$device->uuid}/revoke", $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), ['reason' => 'Yo\'qolgan'])->assertForbidden();
        $this->assertNull($device->refresh()->revoked_at);

        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);
        $this->adminJson('POST', "/pos/devices/{$device->uuid}/revoke", $token, ['reason' => 'Yo\'qolgan'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('POST', "/pos/devices/{$device->uuid}/revoke", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->adminJson('POST', "/pos/devices/{$device->uuid}/revoke", $token, ['reason' => "Kassa o'g'irlangan"])->assertOk()
            ->assertJsonPath('data.state', 'revoked')->assertJsonPath('message', "Qurilma o'chirildi. Undagi barcha sessiyalar yopildi.");
        $device->refresh();
        $this->assertSame([true, $sales->id], [$device->isRevoked(), $device->revoked_by]);

        $audit = AuditLog::where('action', 'admin.pos.device_revoked')->sole();
        $this->assertSame([$sales->id, $this->store->id, $device->id, ['revoked' => false], ['revoked' => true]], [$audit->user_id, $audit->store_id, $device->id, $audit->before, $audit->after]);
        $this->assertSame("Kassa o'g'irlangan", $audit->payload_json['reason']);
        $this->assertSame(1, AuditLog::where('action', 'device.revoked')->count());

        // Revoking a revoked device changes nothing
        $this->adminJson('POST', "/pos/devices/{$device->uuid}/revoke", $token, ['reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.device_revoked')->count());
    }

    public function test_a_revoked_terminal_token_stops_working(): void
    {
        $paired = $this->pairTerminal($this->owner, $this->branch);
        $device = $paired['device'];
        $this->bearerJson('GET', '/api/v1/terminal', $paired['token'])->assertOk();

        $this->adminJson('POST', "/pos/devices/{$device->uuid}/revoke", $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN)), ['reason' => 'Sinov'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->bearerJson('GET', '/api/v1/terminal', $paired['token'])->assertUnauthorized();
    }

    public function test_support_and_unauthenticated_callers_see_nothing(): void
    {
        $device = $this->device();

        $this->adminJson('GET', '/pos/devices', $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertForbidden();
        $this->adminJson('GET', "/pos/devices/{$device->uuid}", $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertForbidden();
        $this->adminJson('GET', '/pos/devices')->assertUnauthorized();
    }
}
