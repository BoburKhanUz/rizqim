<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Events\Broadcast\PlatformOpsPing;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-11: the team's live channel platform.ops (admin token, admin.today.view).
 */
class AdminOpsChannelTest extends TestCase
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
}
