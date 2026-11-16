<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\Broadcasting\RevisionLogBroadcaster;
use App\Domain\Realtime\Broadcasting\RevisionPusherBroadcaster;
use App\Domain\Realtime\ChangeRevision;
use App\Domain\Realtime\Health\RealtimeHealth;
use App\Domain\Realtime\Heartbeat\SendRealtimeHeartbeat;
use App\Enums\OrderStatus;
use App\Events\Broadcast\OrderCreatedBroadcast;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Pusher\Pusher;
use RuntimeException;
use Tests\Feature\StaffOrderFixtures;
use Tests\TestCase;

/**
 * Wave M2: every broadcast carries `rev` (the channel's change token after the change), the heartbeat reaches every
 * occupied staff channel with its token and proves the path for RealtimeHealth, GET /api/v1/realtime tells clients
 * whether to rely on real-time, and a repeated channel subscription is signed from the cache without the database.
 */
class RealtimeChannelTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private const KEY = 'test-app-key';

    private const SECRET = 'test-app-secret';

    /** What the fake Reverb client was asked: triggered, batches; what it answers: channels, fail */
    private \stdClass $calls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => self::KEY,
            'broadcasting.connections.reverb.secret' => self::SECRET,
            'broadcasting.connections.reverb.app_id' => '1001',
        ]);

        $this->calls = (object) ['triggered' => [], 'batches' => [], 'channels' => [], 'fail' => false];
        $calls = $this->calls;
        $pusher = new class(self::KEY, self::SECRET, '1001', $calls) extends Pusher
        {
            public function __construct(string $key, string $secret, string $id, private \stdClass $calls)
            {
                parent::__construct($key, $secret, $id);
            }

            public function trigger($channels, string $event, $data, array $params = [], bool $already_encoded = false): object
            {
                $this->calls->triggered[] = ['channels' => (array) $channels, 'event' => $event, 'data' => $data];

                return (object) [];
            }

            public function triggerBatch(array $batch = [], bool $already_encoded = false): object
            {
                $this->calls->batches[] = $batch;

                return (object) [];
            }

            public function getChannels(array $params = []): object
            {
                if ($this->calls->fail) {
                    throw new RuntimeException('Reverb is down');
                }
                $prefix = (string) ($params['filter_by_prefix'] ?? '');
                $channels = [];
                foreach ($this->calls->channels as $name) {
                    if (str_starts_with($name, $prefix)) {
                        $channels[$name] = (object) [];
                    }
                }

                return (object) ['channels' => $channels];
            }
        };

        $manager = app(BroadcastManager::class);
        $manager->purge('reverb');
        $manager->extend('reverb', fn () => new RevisionPusherBroadcaster($pusher));
        // The channels were registered at boot on the tests' null driver: register them on this one too
        require base_path('routes/channels.php');
    }

    public function test_the_reverb_and_log_drivers_stamp_rev(): void
    {
        // A freshly resolved manager (as in every request) builds the stamping drivers
        $this->app->forgetInstance(BroadcastManager::class);
        $manager = $this->app->make(BroadcastManager::class);
        $this->assertInstanceOf(RevisionPusherBroadcaster::class, $manager->connection('reverb'));
        $this->assertInstanceOf(RevisionLogBroadcaster::class, $manager->connection('log'));
    }

    public function test_an_order_event_carries_each_channels_token_after_the_change(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        event(new OrderCreatedBroadcast($order));

        $sent = collect($this->calls->triggered)->firstWhere('event', 'order.created');
        $this->assertNotNull($sent, 'the event reached the broadcaster');
        $revisions = app(ChangeRevision::class);
        $rev = $sent['data']['rev'];
        $this->assertSame($revisions->branch($this->branch->id), $rev["pos-branch.{$this->branch->id}"]);
        $this->assertSame($revisions->branch($this->branch->id), $rev["branch.{$this->branch->id}"]);
        $this->assertSame($revisions->store($this->store->id), $rev["store.{$this->store->id}"]);
        $channels = array_map(fn ($c) => preg_replace('/^private-/', '', (string) $c), $sent['channels']);
        $this->assertEqualsCanonicalizing($channels, array_keys($rev), 'one token per channel the event goes to');
        $this->assertSame((string) $order->uuid, $sent['data']['order']['uuid'], 'the original payload is kept');
    }

    public function test_the_heartbeat_reaches_occupied_staff_channels_with_their_token_and_proves_the_path(): void
    {
        $this->assertSame('degraded', app(RealtimeHealth::class)->status()['status'], 'no heartbeat yet');

        $this->calls->channels = ["private-pos-branch.{$this->branch->id}", "private-store.{$this->store->id}", "private-user.{$this->customer->id}"];
        (new SendRealtimeHeartbeat)->handle();

        $events = array_merge(...$this->calls->batches);
        $this->assertSame(["private-pos-branch.{$this->branch->id}", "private-store.{$this->store->id}"], array_column($events, 'channel'), 'staff channels only');
        $first = json_decode($events[0]['data'], true);
        $this->assertSame(SendRealtimeHeartbeat::EVENT, $events[0]['name']);
        $this->assertSame(30, $first['interval']);
        $this->assertSame([("pos-branch.{$this->branch->id}") => app(ChangeRevision::class)->branch($this->branch->id)], $first['rev']);

        $status = app(RealtimeHealth::class)->status();
        $this->assertTrue($status['live']);
        $this->assertSame(2, $status['channels']);

        // A backed-up broadcasts queue is not live, whatever the heartbeat says
        config(['realtime.queue_backlog_threshold' => -1]);
        $this->assertFalse(app(RealtimeHealth::class)->status()['live']);
        config(['realtime.queue_backlog_threshold' => 500]);

        // Reverb down: nothing recorded, so the status goes stale
        $this->calls->fail = true;
        $this->travel(3)->minutes();
        (new SendRealtimeHeartbeat)->handle();
        $this->assertSame('degraded', app(RealtimeHealth::class)->status()['status']);
    }

    public function test_the_status_endpoint_is_public_has_no_database_and_says_when_not_to_rely_on_real_time(): void
    {
        $this->calls->channels = [];
        (new SendRealtimeHeartbeat)->handle();

        DB::enableQueryLog();
        $this->getJson('/api/v1/realtime')->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.live', true)
            ->assertJsonPath('data.heartbeat_seconds', 30)
            ->assertJsonPath('data.silence_seconds', 90)
            ->assertJsonPath('data.poll_seconds', ['live' => 60, 'fallback' => 15])
            ->assertJsonPath('data.reverb.key', self::KEY)
            ->assertJsonPath('data.reverb.host', null);
        $this->assertSame([], DB::getQueryLog(), 'no database');

        config(['broadcasting.default' => 'null']);
        \Illuminate\Support\Facades\Cache::store('array')->forget('realtime:status');
        $this->getJson('/api/v1/realtime')->assertOk()
            ->assertJsonPath('data.status', 'off')
            ->assertJsonPath('data.live', false)
            ->assertJsonPath('data.reverb', null);
    }

    private function subscribe(string $token, string $channel, string $socket)
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->postJson('/api/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => $socket]);
    }

    public function test_a_repeated_subscription_is_signed_from_the_cache_and_a_revoked_token_loses_it_at_once(): void
    {
        $token = $this->owner->createToken('test')->plainTextToken;
        $channel = "private-store.{$this->store->id}";

        $this->subscribe($token, $channel, '111.222')->assertOk()
            ->assertJsonPath('auth', self::KEY . ':' . hash_hmac('sha256', '111.222:' . $channel, self::SECRET));

        DB::enableQueryLog();
        $this->subscribe($token, $channel, '333.444')->assertOk()
            ->assertJsonPath('auth', self::KEY . ':' . hash_hmac('sha256', '333.444:' . $channel, self::SECRET));
        $this->assertSame([], DB::getQueryLog(), 'a repeat costs no query');
        DB::disableQueryLog();

        // Another channel of the same token is checked in full (and refused: not this owner's store)
        $this->subscribe($token, 'private-store.' . ($this->store->id + 1000), '333.444')->assertForbidden();
        $this->subscribe($token, 'private-store.' . ($this->store->id + 1000), '333.444')->assertForbidden();

        // Revoked: the remembered grants are dropped with it
        PersonalAccessToken::query()->delete();
        $this->subscribe($token, $channel, '555.666')->assertUnauthorized();

        // A token's ordinary use (last_used_at) keeps them; the TTL ends them
        $token = $this->owner->createToken('test')->plainTextToken;
        $this->subscribe($token, $channel, '111.222')->assertOk();
        PersonalAccessToken::query()->update(['last_used_at' => now()]);
        DB::enableQueryLog();
        $this->subscribe($token, $channel, '111.333')->assertOk();
        $this->assertSame([], DB::getQueryLog(), 'still remembered');
        DB::disableQueryLog();
        $this->travel(61)->seconds();
        DB::enableQueryLog();
        $this->subscribe($token, $channel, '111.444')->assertOk();
        $this->assertNotSame([], DB::getQueryLog(), 'checked in full after the TTL');
        DB::disableQueryLog();
    }

    public function test_a_balance_change_keeps_the_grants_and_a_role_change_drops_them(): void
    {
        $token = $this->manager->createToken('test')->plainTextToken;
        $channel = "private-branch.{$this->branch->id}";
        $this->subscribe($token, $channel, '1.1')->assertOk();

        DB::table('users')->where('id', $this->manager->id)->update(['balance' => 1000]);
        DB::enableQueryLog();
        $this->subscribe($token, $channel, '1.2')->assertOk();
        $this->assertSame([], DB::getQueryLog(), 'a balance change takes no channel away');
        DB::disableQueryLog();

        $this->manager->update(['branch_id' => $this->otherBranch->id]);
        $this->subscribe($token, $channel, '1.3')->assertForbidden();
    }

    public function test_without_the_cache_a_revoked_token_is_refused_at_once(): void
    {
        config(['realtime.auth_cache_seconds' => 0]);
        $token = $this->owner->createToken('test')->plainTextToken;
        $channel = "private-store.{$this->store->id}";

        $this->subscribe($token, $channel, '111.222')->assertOk();
        PersonalAccessToken::query()->delete();
        $this->subscribe($token, $channel, '111.333')->assertUnauthorized();
    }
}
