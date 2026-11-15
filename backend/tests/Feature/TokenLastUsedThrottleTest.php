<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Wave M1 (docs/scale/RESULTS.md finding 7): personal_access_tokens.last_used_at is written at most once per token per
 * sanctum.last_used_at_throttle_minutes (default 5) by the `sanctum` and `device` guards, instead of on every request;
 * 0 keeps Sanctum's write on every request. The terminal heartbeat (devices.last_seen_at) stays once a minute.
 */
class TokenLastUsedThrottleTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        Carbon::setTestNow('2026-11-15 12:00:00');
    }

    public function test_a_user_token_is_touched_at_most_once_per_interval(): void
    {
        $plain = $this->customer->createToken('mini-app')->plainTextToken;
        $token = PersonalAccessToken::findToken($plain);
        $this->assertNull($token->last_used_at);

        // The first use is written
        $this->bearerJson('GET', '/api/v1/user', $plain)->assertOk();
        $this->assertEquals('2026-11-15 12:00:00', $token->refresh()->last_used_at);

        // Within 5 minutes: no write (no UPDATE of the token at all)
        Carbon::setTestNow('2026-11-15 12:04:59');
        DB::enableQueryLog();
        $this->bearerJson('GET', '/api/v1/user', $plain)->assertOk();
        $updates = collect(DB::getQueryLog())->filter(fn (array $query) => str_starts_with($query['query'], 'update "personal_access_tokens"'));
        DB::disableQueryLog();
        $this->assertCount(0, $updates);
        $this->assertEquals('2026-11-15 12:00:00', $token->refresh()->last_used_at);

        Carbon::setTestNow('2026-11-15 12:05:00');
        $this->bearerJson('GET', '/api/v1/user', $plain)->assertOk();
        $this->assertEquals('2026-11-15 12:05:00', $token->refresh()->last_used_at);
    }

    public function test_zero_writes_on_every_request_as_before(): void
    {
        config(['sanctum.last_used_at_throttle_minutes' => 0]);
        $plain = $this->customer->createToken('mini-app')->plainTextToken;
        $token = PersonalAccessToken::findToken($plain);

        $this->bearerJson('GET', '/api/v1/user', $plain)->assertOk();
        Carbon::setTestNow('2026-11-15 12:00:10');
        $this->bearerJson('GET', '/api/v1/user', $plain)->assertOk();
        $this->assertEquals('2026-11-15 12:00:10', $token->refresh()->last_used_at);
    }

    public function test_a_terminal_keeps_its_minute_heartbeat_and_its_token_is_touched_per_interval(): void
    {
        ['device' => $device, 'token' => $plain] = $this->pairTerminal($this->owner, $this->branch);
        $token = PersonalAccessToken::findToken($plain);

        // Paired just now (last seen at pairing): the first heartbeat a minute later writes both
        Carbon::setTestNow('2026-11-15 12:01:30');
        $this->bearerJson('GET', '/api/v1/terminal', $plain)->assertOk();
        $this->assertEquals('2026-11-15 12:01:30', Device::find($device->id)->last_seen_at);
        $this->assertEquals('2026-11-15 12:01:30', $token->refresh()->last_used_at);

        // A minute later: the heartbeat moves, the token does not
        Carbon::setTestNow('2026-11-15 12:03:00');
        $this->bearerJson('GET', '/api/v1/terminal', $plain)->assertOk();
        $this->assertEquals('2026-11-15 12:03:00', Device::find($device->id)->last_seen_at);
        $this->assertEquals('2026-11-15 12:01:30', $token->refresh()->last_used_at);

        Carbon::setTestNow('2026-11-15 12:06:30');
        $this->bearerJson('GET', '/api/v1/terminal', $plain)->assertOk();
        $this->assertEquals('2026-11-15 12:06:30', Device::find($device->id)->last_seen_at);
        $this->assertEquals('2026-11-15 12:06:30', $token->refresh()->last_used_at);
    }
}
