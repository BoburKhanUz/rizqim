<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * P2-33: DELETE /terminal — a paired terminal unpairs itself with its own token (the kitchen display before it forgets
 * its pairing), with the same effect as the owner's revoke: the device is revoked, its token and every staff PIN
 * session on it stop at once, and the audit log says so.
 */
class TerminalSelfRevokeTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_a_terminal_revokes_itself_and_its_staff_sessions(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $other = $this->pairTerminal($this->owner, $this->branch, DeviceType::POS);
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $session = $this->pinSession($terminal, $cook);
        $this->bearerJson('GET', '/api/v1/staff/kitchen/tickets', $session)->assertOk();

        $this->bearerJson('DELETE', '/api/v1/terminal', $terminal['token'])->assertOk()->assertJsonPath('data', null);

        $this->assertNotNull(Device::find($terminal['device']->id)->revoked_at);
        $this->assertSame(0, PersonalAccessToken::where('device_id', $terminal['device']->id)->count());
        $this->bearerJson('GET', '/api/v1/terminal', $terminal['token'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/staff/kitchen/tickets', $session)->assertUnauthorized();
        $this->bearerJson('DELETE', '/api/v1/terminal', $terminal['token'])->assertUnauthorized();

        $log = AuditLog::where('action', 'device.revoked')->sole();
        $this->assertSame($terminal['device']->id, (int) $log->model_id);
        $this->assertTrue((bool) ($log->payload_json['self_revoked'] ?? false));
        $this->assertSame($terminal['device']->id, (int) $log->device_id);
        $this->assertSame($this->store->id, (int) $log->store_id);

        // Only itself: another terminal of the store keeps working
        $this->bearerJson('GET', '/api/v1/terminal', $other['token'])->assertOk();
        $this->assertNull(Device::find($other['device']->id)->revoked_at);
    }

    public function test_only_a_terminal_token_may_call_it(): void
    {
        $this->actingAs($this->owner, 'sanctum')->deleteJson('/api/v1/terminal')->assertUnauthorized();
        $this->bearerJson('DELETE', '/api/v1/terminal', null)->assertUnauthorized();

        // A staff PIN session is a user token, not the terminal's
        $terminal = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $session = $this->pinSession($terminal, $this->makeStaff(UserRole::COOK, $this->branch));
        $this->bearerJson('DELETE', '/api/v1/terminal', $session)->assertUnauthorized();
        $this->assertNull(Device::find($terminal['device']->id)->revoked_at);
    }
}
