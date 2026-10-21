<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Models\PosApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * P3-18: manager approvals: a manager's PIN on the terminal gives a one-time token for one permission on that
 * terminal for 60 seconds; the PIN lockout applies; expired, reused, wrong terminal and wrong permission tokens are
 * refused; both people are audited; the contract is bound for the other POS areas.
 */
class PosApprovalsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    private function consume(string $token, string $permission, ?int $deviceId): int
    {
        Auth::guard('sanctum')->setUser($this->cashier);

        return app(ManagerApprovals::class)->consume($token, $permission, $deviceId);
    }

    private function refusal(callable $call): string
    {
        try {
            $call();
        } catch (PosActionException $e) {
            $this->assertSame(403, $e->status);

            return $e->reason;
        }

        $this->fail('The approval was accepted.');
    }

    public function test_a_managers_pin_gives_a_one_time_token_for_one_permission_on_this_terminal(): void
    {
        $this->assertInstanceOf(ManagerApprovalService::class, app(ManagerApprovals::class));

        $response = $this->pos('POST', '/approvals', ['permission' => 'orders.void_item', 'staff_code' => $this->manager->staff_code, 'pin' => self::MANAGER_PIN])->assertCreated();
        $response->assertJsonPath('data.permission', 'orders.void_item')->assertJsonPath('data.approved_by.id', $this->manager->id);
        $token = $response->json('data.token');

        $row = PosApproval::firstOrFail();
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        $this->assertSame(60, (int) round($row->created_at->diffInSeconds($row->expires_at)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.approval_granted', 'user_id' => $this->manager->id, 'device_id' => $this->till['device']->id]);

        $this->assertSame($this->manager->id, $this->consume($token, 'orders.void_item', $this->till['device']->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.approval_used', 'user_id' => $this->cashier->id]);
        $this->assertSame('approval_used', $this->refusal(fn () => $this->consume($token, 'orders.void_item', $this->till['device']->id)));
    }

    public function test_expired_wrong_terminal_and_wrong_permission_tokens_are_refused(): void
    {
        $token = $this->approval('orders.discount');
        $device = $this->till['device']->id;

        $this->assertSame('approval_wrong_device', $this->refusal(fn () => $this->consume($token, 'orders.discount', $device + 1000)));
        $this->assertSame('approval_wrong_device', $this->refusal(fn () => $this->consume($token, 'orders.discount', null)));
        $this->assertSame('approval_wrong_permission', $this->refusal(fn () => $this->consume($token, 'orders.void_item', $device)));
        $this->assertSame('approval_invalid', $this->refusal(fn () => $this->consume('nonsense', 'orders.discount', $device)));

        $this->travel(61)->seconds();
        $this->assertSame('approval_expired', $this->refusal(fn () => $this->consume($token, 'orders.discount', $device)));
    }

    public function test_another_stores_token_is_unknown(): void
    {
        $token = $this->approval('orders.discount');
        $foreign = $this->makeStore('Boshqa');
        $stranger = $this->makeStaff(\App\Enums\UserRole::CASHIER, null, $foreign);
        Auth::guard('sanctum')->setUser($stranger);

        $this->assertSame('approval_invalid', $this->refusal(fn () => app(ManagerApprovals::class)->consume($token, 'orders.discount', $this->till['device']->id)));
    }

    public function test_the_pin_lockout_and_the_approvers_own_permission_apply(): void
    {
        $data = ['permission' => 'pos.refund', 'user_id' => $this->manager->id];
        for ($i = 0; $i < 4; $i++) {
            $this->pos('POST', '/approvals', $data + ['pin' => '1111'])->assertStatus(422)->assertJsonPath('data.reason', 'pin_invalid');
        }
        $this->pos('POST', '/approvals', $data + ['pin' => '1111'])->assertStatus(429)->assertJsonPath('data.reason', 'pin_locked');
        $this->pos('POST', '/approvals', $data + ['pin' => self::MANAGER_PIN])->assertStatus(429);

        // A waiter knows their PIN but cannot approve a refund
        $this->givePin($this->waiter, '8642');
        $this->pos('POST', '/approvals', ['permission' => 'pos.refund', 'user_id' => $this->waiter->id, 'pin' => '8642'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approver_not_allowed');

        // Settings and staff are never approved on a terminal
        $this->pos('POST', '/approvals', ['permission' => 'settings.manage', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])->assertStatus(422);
        $this->assertSame(0, PosApproval::count());
    }

    public function test_approvals_are_given_on_a_terminal_only(): void
    {
        $this->posAs($this->cashier, 'POST', '/approvals', ['permission' => 'pos.refund', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertStatus(422)->assertJsonPath('data.reason', 'terminal_required');

        // A manager of another branch cannot sign in on this terminal, so cannot approve on it
        $otherManager = $this->makeStaff(\App\Enums\UserRole::MANAGER, $this->otherBranch);
        $this->givePin($otherManager, '3971');
        $this->pos('POST', '/approvals', ['permission' => 'pos.refund', 'user_id' => $otherManager->id, 'pin' => '3971'])->assertStatus(422);
        $this->assertInstanceOf(User::class, $otherManager);
    }
}
