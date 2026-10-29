<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Domain\Pos\Billing\PosActionException;
use App\Enums\PlatformRole;
use App\Models\PosApproval;
use App\Services\Admin\Customers\UserBlocking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\AdminFixtures;
use Tests\TestCase;

/**
 * Review W3 S#1: a blocked manager's (or owner's) PIN is no PIN at all on a terminal: it approves nothing, signs in
 * nowhere and the person is not on the terminal's staff list. Blocking ends their PIN sessions and the approvals they
 * gave that are still unused.
 */
class BlockedStaffPinTest extends TestCase
{
    use AdminFixtures;
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_a_blocked_managers_pin_approves_nothing(): void
    {
        $this->manager->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Firibgarlik'])->save();

        // The same answer as a wrong PIN: the terminal cannot tell a blocked person from a wrong code
        $this->tillAs('POST', '/pos/approvals', ['permission' => 'orders.cancel', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertStatus(422)->assertJsonPath('data.reason', 'pin_invalid');
        $this->assertSame(0, PosApproval::count());
    }

    public function test_a_blocked_manager_is_not_on_the_terminal_and_cannot_sign_in(): void
    {
        $device = $this->till['device'];
        $listed = fn () => collect($this->bearerJson('GET', '/api/v1/terminal/staff', $this->till['token'])->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($this->manager->id, $listed());

        $this->manager->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Firibgarlik'])->save();

        $this->assertNotContains($this->manager->id, $listed());
        $this->bearerJson('POST', "/api/v1/devices/{$device->uuid}/staff-session", $this->till['token'], ['user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertStatus(422)->assertJsonPath('data.reason', 'pin_invalid');
        $this->bearerJson('POST', "/api/v1/devices/{$device->uuid}/staff-session", $this->till['token'], ['staff_code' => $this->manager->fresh()->staff_code, 'pin' => self::MANAGER_PIN])
            ->assertStatus(422)->assertJsonPath('data.reason', 'pin_invalid');
    }

    public function test_blocking_ends_the_pin_sessions_and_the_unused_approvals(): void
    {
        $device = $this->till['device'];
        $session = (string) $this->bearerJson('POST', "/api/v1/devices/{$device->uuid}/staff-session", $this->till['token'], ['user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertOk()->json('data.token');
        // The cashier's session on the till was replaced by the manager's: sign the cashier in again for the approval
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $approval = (string) $this->tillAs('POST', '/pos/approvals', ['permission' => 'orders.cancel', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertCreated()->json('data.token');

        app(UserBlocking::class)->block($this->manager->fresh(), $this->makeMember(PlatformRole::SUPPORT), 'Firibgarlik');

        $this->assertNull(PersonalAccessToken::findToken($session));
        try {
            app(ManagerApprovalService::class)->consume($approval, 'orders.cancel', $device->id);
            $this->fail('An approval of a blocked manager was used');
        } catch (PosActionException $e) {
            $this->assertSame('approval_expired', $e->reason);
        }
    }
}
