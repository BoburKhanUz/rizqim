<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Events\Broadcast\PlatformOpsPing;
use App\Filament\Pages\Auth\CustomLogin;
use App\Models\User;
use App\Services\Admin\TwoFactorService;
use Filament\Facades\Filament;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P4-11: the team's live channel platform.ops (admin token, admin.today.view), and the old Filament panels asking for
 * the same 2FA: /superadmin requires it, /admin lets a superadmin in only with it, and the sign-in asks for the code.
 */
class AdminChannelAndPanelTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use AccessFixtures;

    private function join(?string $token, string $channel = 'platform.ops')
    {
        return $this->withBearer($token)->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-{$channel}",
        ]);
    }

    private function useSigningBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');
    }

    public function test_platform_ops_is_for_team_members_with_today_view_on_the_admin_token(): void
    {
        $this->useSigningBroadcaster();
        $this->setUpLifecycleStore();

        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $support = $this->makeMember(PlatformRole::SUPPORT);

        $this->join($this->signIn($operator))->assertOk()->assertJsonStructure(['auth']);
        $this->join($this->signIn($viewer))->assertOk();
        $this->join($this->signIn($support))->assertForbidden();

        // Store and customer tokens never join; a superadmin's store-level token is dead altogether (P4-25: 401)
        $this->join($this->owner->createToken('mini-app')->plainTextToken)->assertForbidden();
        $this->join($this->customer->createToken('mini-app')->plainTextToken)->assertForbidden();
        $superadmin = $this->makeMember();
        $this->join($superadmin->createToken('mini-app')->plainTextToken)->assertUnauthorized();
        $this->join(null)->assertUnauthorized();

        // An admin token joins no store channel either
        $this->join($this->signIn($superadmin), 'store.' . $this->store->id)->assertForbidden();
        $this->join($this->signIn($superadmin))->assertOk();
    }

    public function test_the_test_event_goes_to_platform_ops(): void
    {
        $event = new PlatformOpsPing('salom');

        $this->assertEquals([new PrivateChannel('platform.ops')], $event->broadcastOn());
        $this->assertSame('ops.ping', $event->broadcastAs());
        $this->assertSame('salom', $event->broadcastWith()['message']);
        $this->assertSame('broadcasts', $event->broadcastQueue());
    }

    public function test_the_superadmin_panel_requires_2fa(): void
    {
        $superadmin = User::create([
            'name' => 'Old SA', 'email' => 'old-sa@rizqim.test', 'password' => Hash::make($this->adminPassword), 'role' => UserRole::SUPER_ADMIN,
        ]);

        // Without 2FA: everything sends them to the set-up first
        $this->actingAs($superadmin)->get('/superadmin')->assertRedirectContains('/superadmin/multi-factor-authentication/set-up');
        $this->actingAs($superadmin)->get('/superadmin/global-audit-logs')->assertRedirectContains('multi-factor-authentication');
        $this->actingAs($superadmin)->get('/superadmin/multi-factor-authentication/set-up')->assertOk();

        // The store panel shares the session: no way in there without 2FA either
        $this->assertFalse($superadmin->canAccessPanel(Filament::getPanel('admin')));

        $this->enableTwoFactor($superadmin);
        $superadmin->refresh();
        $this->actingAs($superadmin)->get('/superadmin')->assertOk();
        $this->assertTrue($superadmin->canAccessPanel(Filament::getPanel('admin')));

        // A disabled superadmin is out of both
        $superadmin->forceFill(['platform_disabled_at' => now()])->save();
        $this->assertFalse($superadmin->canAccessPanel(Filament::getPanel('superadmin')));
        $this->assertFalse($superadmin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_the_panel_sign_in_asks_for_the_same_code_and_refuses_a_replay(): void
    {
        $superadmin = User::create([
            'name' => 'Old SA', 'email' => 'old-sa@rizqim.test', 'password' => Hash::make($this->adminPassword), 'role' => UserRole::SUPER_ADMIN,
        ]);
        $this->enableTwoFactor($superadmin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        // The password alone does not sign in: the code is asked for
        $login = Livewire::test(CustomLogin::class)
            ->fillForm(['email' => $superadmin->email, 'password' => $this->adminPassword])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertGuest();
        $this->assertNotNull($login->get('userUndertakingMultiFactorAuthentication'));

        $provider = Filament::getPanel('superadmin')->getMultiFactorAuthenticationProviders()['app'];
        $this->assertInstanceOf(\App\Filament\Auth\PlatformAppAuthentication::class, $provider);
        $this->assertFalse($provider->verifyCode('000000', $this->secrets[$superadmin->id], true));

        // The code the admin API accepted is not accepted again by the panel (one replay rule for both)
        $this->nextStep();
        $code = $this->codeFor($superadmin);
        $this->assertTrue(app(TwoFactorService::class)->verifyForUser($superadmin, $code));
        $provider->getChallengeFormComponents($superadmin->refresh());
        $this->assertFalse($provider->verifyCode($code, $this->secrets[$superadmin->id], true));

        $this->nextStep();
        $this->assertTrue($provider->verifyCode($this->codeFor($superadmin), $this->secrets[$superadmin->id], true));
        // Recovery codes are the same ones too
        $this->assertTrue($provider->verifyRecoveryCode('AAAAA-BBBBB', $superadmin));
        $this->assertSame(1, app(TwoFactorService::class)->recoveryCodesLeft($superadmin->refresh()));
    }
}
