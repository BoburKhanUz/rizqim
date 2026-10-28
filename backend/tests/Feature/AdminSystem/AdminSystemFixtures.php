<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\User;
use Tests\Feature\AdminFixtures;

/**
 * P4-31 Tizim: members and sign-in (AdminFixtures) and the checks every Tizim write shares: superadmin only
 * (admin.system.manage), a step-up and a reason.
 */
trait AdminSystemFixtures
{
    use AdminFixtures;

    protected ?User $superadmin = null;

    protected ?string $superToken = null;

    /**
     * A confirmed (stepped-up) superadmin session.
     */
    protected function superToken(): string
    {
        if ($this->superToken === null) {
            $this->superadmin = $this->makeMember(PlatformRole::SUPERADMIN);
            $this->superToken = $this->confirmedSession($this->superadmin);
        }

        return $this->superToken;
    }

    /**
     * Every other role is refused (no admin.system.manage), a superadmin without a step-up gets reauth_required and
     * without a reason a 422; nothing is audited for any of them.
     *
     * @param  array<string, mixed>  $body  a valid body with a `reason`
     */
    protected function assertGuardedWrite(string $method, string $uri, array $body): void
    {
        $written = fn () => AuditLog::query()->where('action', 'like', 'admin.system.%')->count();
        $before = $written();

        foreach ([PlatformRole::VIEWER, PlatformRole::OPERATOR, PlatformRole::FINANCE] as $role) {
            $this->adminJson($method, $uri, $this->confirmedSession($this->makeMember($role)), $body)->assertForbidden()->assertJsonPath('code', 'forbidden');
        }

        $member = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->signIn($member);
        $this->adminJson($method, $uri, $token, $body)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($member, $token);
        $withoutReason = $body;
        unset($withoutReason['reason']);
        $this->adminJson($method, $uri, $token, $withoutReason)->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertSame($before, $written());
    }
}
