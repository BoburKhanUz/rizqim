<?php

namespace Tests\Feature\AdminCustomers;

use App\Auth\AdminTokenGuard;
use App\Enums\DeviceType;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\AccountBlockedException;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * P4-31: blocking, unblocking and closing a user's sessions from the user card: support.manage, a step-up and a
 * reason, audited; a blocked account signs in nowhere and its tokens stop working; admin sessions and team accounts
 * are never touched.
 */
class AdminUserBlockTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;
    use AccessFixtures;

    private const BOT_TOKEN = '123456:TEST-bot-token';
    private const BOT_USERNAME = 'rizqim_test_bot';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        config(['services.telegram.bot_token' => self::BOT_TOKEN, 'services.telegram.bot_username' => self::BOT_USERNAME]);
    }

    public function test_block_needs_support_manage_a_step_up_and_a_reason_and_is_audited(): void
    {
        $customer = $this->makeCustomer(0, ['telegram_id' => '5550001']);
        $miniApp = $customer->createToken('mobile-api')->plainTextToken;
        $this->bearerJson('GET', '/api/v1/user', $miniApp)->assertOk();

        // The viewer reads the card but cannot block
        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $this->adminJson('POST', "/users/{$customer->id}/block", $this->confirmedSession($viewer), ['reason' => 'Firibgarlik'])->assertForbidden();

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($support);
        $this->adminJson('POST', "/users/{$customer->id}/block", $token, ['reason' => 'Firibgarlik'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($support, $token);
        $this->adminJson('POST', "/users/{$customer->id}/block", $token)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/users/{$customer->id}/block", $token, ['reason' => 'ab'])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->adminJson('POST', "/users/{$customer->id}/block", $token, ['reason' => 'Firibgarlik: 5 ta soxta buyurtma'])
            ->assertOk()
            ->assertJsonPath('data.block.blocked', true)
            ->assertJsonPath('data.block.reason', 'Firibgarlik: 5 ta soxta buyurtma')
            ->assertJsonPath('data.block.by.id', $support->id)
            ->assertJsonPath('data.actions.block', false)
            ->assertJsonPath('data.actions.unblock', true)
            ->assertJsonPath('data.sessions.active_tokens', 0);

        $customer->refresh();
        $this->assertTrue($customer->isBlocked());
        $audit = AuditLog::where('action', 'admin.users.blocked')->sole();
        $this->assertSame($support->id, (int) $audit->user_id);
        $this->assertSame(User::class, $audit->model_type);
        $this->assertSame($customer->id, (int) $audit->model_id);
        $this->assertSame(['blocked' => false], $audit->before);
        $this->assertSame(['blocked' => true], $audit->after);
        $this->assertSame('Firibgarlik: 5 ta soxta buyurtma', $audit->payload_json['reason']);
        $this->assertSame(['tokens' => 1, 'refresh_tokens' => 0], $audit->payload_json['sessions_closed']);

        // The token issued before is gone (and would be refused anyway); blocked again is 409
        $this->bearerJson('GET', '/api/v1/user', $miniApp)->assertUnauthorized();
        $this->adminJson('POST', "/users/{$customer->id}/block", $token, ['reason' => 'Yana bir bor'])->assertStatus(409)->assertJsonPath('code', 'already_blocked');

        // The blocked fields never reach the person's own answers
        $this->assertArrayNotHasKey('blocked_reason', $customer->toArray());

        // Unblock, the same rules
        $this->adminJson('POST', "/users/{$customer->id}/unblock", $token, ['reason' => 'Tushunmovchilik ekan'])
            ->assertOk()->assertJsonPath('data.block.blocked', false)->assertJsonPath('data.block.reason', null);
        $this->assertFalse($customer->fresh()->isBlocked());
        $this->assertSame(['blocked' => true], AuditLog::where('action', 'admin.users.unblocked')->sole()->before);
        $this->adminJson('POST', "/users/{$customer->id}/unblock", $token, ['reason' => 'Yana bir bor'])->assertStatus(409)->assertJsonPath('code', 'not_blocked');
    }

    public function test_a_blocked_account_signs_in_nowhere_and_its_tokens_stop_working(): void
    {
        $customer = $this->makeCustomer(0, ['telegram_id' => '5550001']);
        // A token the blocking itself would not see (made after it, as if by a path that skipped the guard)
        $customer->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Firibgarlik'])->save();
        $late = PersonalAccessToken::forceCreate([
            'tokenable_type' => $customer->getMorphClass(), 'tokenable_id' => $customer->id, 'name' => 'mobile-api',
            'token' => hash('sha256', 'late-token'), 'abilities' => ['*'],
        ]);
        $this->bearerJson('GET', '/api/v1/user', $late->id . '|late-token')->assertUnauthorized();

        // Telegram sign-in: refused with the reason, no token
        $this->withBearer(null)->postJson('/api/v1/auth/telegram', ['initData' => $this->initData(5550001), 'bot_username' => self::BOT_USERNAME])
            ->assertForbidden()->assertJsonPath('data.reason', 'account_blocked');
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $customer->id)->count());

        // App sessions and staff PIN sessions are minted by TokenService: refused there too
        $device = Device::create(['user_id' => $customer->id, 'type' => DeviceType::CUSTOMER_MOBILE->value, 'name' => 'Pixel']);
        $this->expectException(AccountBlockedException::class);
        app(TokenService::class)->issuePair($customer, $device);
    }

    public function test_a_blocked_staff_member_is_refused_by_the_token_check(): void
    {
        $token = $this->courier->createToken('mobile-api')->plainTextToken;
        $this->bearerJson('GET', '/api/v1/user', $token)->assertOk();
        $this->courier->forceFill(['blocked_at' => now()])->save();
        $this->bearerJson('GET', '/api/v1/user', $token)->assertUnauthorized();
    }

    public function test_close_sessions_ends_every_app_session_but_never_an_admin_one(): void
    {
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->confirmedSession($support);
        $device = Device::create(['user_id' => $this->owner->id, 'type' => DeviceType::PARTNER_MOBILE->value, 'name' => 'iPhone', 'push_token' => 'push-1']);
        $pair = app(TokenService::class)->issuePair($this->owner, $device);
        $miniApp = $this->owner->createToken('mobile-api')->plainTextToken;
        $this->bearerJson('GET', '/api/v1/user', $pair->accessToken)->assertOk();

        $this->adminJson('POST', "/users/{$this->owner->id}/close-sessions", $token, ['reason' => "Telefoni o'g'irlangan"])
            ->assertOk()->assertJsonPath('data.sessions.active_tokens', 0)->assertJsonPath('data.sessions.refresh_sessions', 0);

        $this->bearerJson('GET', '/api/v1/user', $pair->accessToken)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/user', $miniApp)->assertUnauthorized();
        $this->assertSame(0, RefreshToken::where('user_id', $this->owner->id)->whereNull('revoked_at')->count());
        $this->assertNull($device->fresh()->push_token);
        $this->assertFalse($this->owner->fresh()->isBlocked(), 'closing sessions is no block: the person signs in again');
        $entry = AuditLog::where('action', 'admin.users.sessions_closed')->sole();
        $this->assertSame(['tokens' => 2, 'refresh_tokens' => 1], $entry->payload_json['sessions_closed']);
        $this->assertSame($this->store->id, (int) $entry->store_id);

        // The member's own admin session still works
        $this->adminJson('GET', '/auth/me', $token)->assertOk();
        $this->assertSame(1, PersonalAccessToken::where('name', AdminTokenGuard::NAME)->count());
    }

    public function test_a_team_account_is_never_acted_on(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $operatorSession = $this->signIn($operator);
        $token = $this->confirmedSession($this->makeMember());

        foreach (['block', 'unblock', 'close-sessions'] as $action) {
            $this->adminJson('POST', "/users/{$operator->id}/{$action}", $token, ['reason' => 'Sinab ko\'ramiz'])
                ->assertForbidden()->assertJsonPath('code', 'platform_account');
        }
        $this->assertFalse($operator->fresh()->isBlocked());
        $this->adminJson('GET', '/auth/me', $operatorSession)->assertOk();
        $this->adminJson('POST', '/users/999999/block', $token, ['reason' => 'Sinab ko\'ramiz'])->assertNotFound();
    }

    private function initData(int $telegramId): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Ali']),
        ];
        ksort($fields);
        $check = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true));

        return http_build_query($fields);
    }
}
