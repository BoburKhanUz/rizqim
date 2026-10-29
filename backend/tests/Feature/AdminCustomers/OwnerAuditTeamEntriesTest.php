<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * Review W3 S#4: what the store owner reads in /owner/audit-logs about an action of the RIZQIM team on their store:
 * the action and what changed, by "RIZQIM jamoasi" — not which member did it, from where, or the team's internal
 * reason and notes.
 */
class OwnerAuditTeamEntriesTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;
    use AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    public function test_a_team_action_shows_no_member_ip_or_internal_reason(): void
    {
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->confirmedSession($support);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->adminJson('POST', "/users/{$this->manager->id}/block", $token, ['reason' => 'Ichki tekshiruv: egasi bilan til biriktirgan'])
            ->assertOk();
        $this->assertSame('203.0.113.77', AuditLog::where('action', 'admin.users.blocked')->sole()->ip, 'the team still sees it all');

        // An entry of the owner's own staff, for comparison
        app(AuditLogger::class)->record('staff.pin_reset', $this->manager, [], [], ['staff_name' => $this->manager->name], [
            'actor' => $this->owner, 'store_id' => $this->store->id, 'ip' => '198.51.100.5',
        ]);

        Sanctum::actingAs($this->owner->fresh(), ['*']);
        $log = collect($this->getJson('/api/v1/owner/audit-logs')->assertOk()->json('data.data'));

        $team = $log->firstWhere('action', 'admin.users.blocked');
        $this->assertNotNull($team);
        $this->assertSame(['id' => null, 'name' => 'RIZQIM jamoasi', 'role' => 'platform'], $team['actor']);
        $this->assertNull($team['ip']);
        $this->assertSame(['blocked' => false], $team['before']);
        $this->assertSame(['blocked' => true], $team['after']);
        $this->assertSame('User', $team['subject_type']);
        $this->assertSame($this->manager->id, $team['subject_id']);
        $this->assertArrayNotHasKey('reason', $team['meta'] ?? []);
        $this->assertStringNotContainsString('Ichki tekshiruv', json_encode($team, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString($support->name, json_encode($team, JSON_UNESCAPED_UNICODE));
        $this->assertArrayHasKey('sessions_closed', $team['meta'], 'what happened to the store is kept');

        $own = $log->firstWhere('action', 'staff.pin_reset');
        $this->assertSame($this->owner->id, $own['actor']['id']);
        $this->assertSame('198.51.100.5', $own['ip']);

        // Nor can the member be found by filtering on their id
        $this->assertSame([], $this->getJson("/api/v1/owner/audit-logs?user_id={$support->id}")->assertOk()->json('data.data'));
    }
}
