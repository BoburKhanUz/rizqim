<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Keshbek review H1, the two other places that delete a users row: an employee invite accepted by someone who already
 * has an account in the store (the bot's /start invite_… and the Mini App's start_param) merges the invite into that
 * account and deletes the unclaimed invite row. ReferralService takes any user id as a referrer (ref_<id>, ids are
 * sequential), so an invite row can have referred friends and the bonuses they earned. Owner decision A: its balance
 * moves to the account with a record (KeshbekTransfer) and its friends name the account as referrer, so the invite
 * row holds nothing any more and is deleted, through either channel (the record and the report:
 * Tests\Feature\Referral\InviteMergeKeshbekTransferTest). One without keshbek is deleted as before.
 */
class InvitePlaceholderKeshbekTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'placeholder-test-secret';

    private const BOT_TOKEN = '123456:TEST-bot-token';

    private const BOT_USERNAME = 'rizqim_test_bot';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.webhook_secret' => self::SECRET,
            'services.telegram.bot_token' => self::BOT_TOKEN,
            'services.telegram.bot_username' => self::BOT_USERNAME,
            'services.telegram.webapp_url' => 'https://app.example.uz/',
        ]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->store = Store::create(['name' => 'Placeholder Store', 'slug' => 'placeholder-store', 'commission_rate' => 10, 'status' => 'active', 'is_active' => true]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'bot, invite holds keshbek' => ['bot', 'balance'],
            'bot, invite referred a friend' => ['bot', 'referral'],
            'mini app, invite holds keshbek' => ['mini_app', 'balance'],
            'mini app, invite referred a friend' => ['mini_app', 'referral'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function test_an_invite_rows_keshbek_goes_to_the_account_and_the_row_is_deleted(string $via, string $what): void
    {
        $existing = User::create(['name' => 'Kassir', 'telegram_id' => '8181818', 'store_id' => $this->store->id, 'role' => UserRole::CASHIER]);
        $invite = $this->invite('emp_keshbek' . $via);
        $friend = null;
        if ($what === 'balance') {
            User::whereKey($invite->id)->update(['balance' => '5000.00']);
        } else {
            $friend = User::create(['name' => 'Do\'st', 'telegram_id' => '8181819', 'role' => UserRole::CUSTOMER]);
            $friend->forceFill(['referred_by' => $invite->id])->save();
        }

        $this->accept($via, 8181818, 'emp_keshbek' . $via);

        $this->assertSame(UserRole::MANAGER, $existing->fresh()->role, 'the existing account took the invite');
        $this->assertNull(User::find($invite->id), 'holding nothing any more, the invite row is deleted');
        $this->assertSame($what === 'balance' ? '5000.00' : '0.00', (string) $existing->fresh()->balance);
        $this->assertSame($what === 'balance' ? 1 : 0, \App\Models\KeshbekTransfer::where('from_user_id', $invite->id)->where('to_user_id', $existing->id)->count());
        if ($friend !== null) {
            $this->assertSame($existing->id, (int) $friend->fresh()->referred_by);
        }
    }

    /**
     * Review fix, still: no token issued to the invite row signs in after the merge (it would authenticate as a
     * customer); its balance is the account's now.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_a_merged_invite_rows_sessions_end_and_its_balance_moves(string $via): void
    {
        $existing = User::create(['name' => 'Kassir', 'telegram_id' => '8383838', 'store_id' => $this->store->id, 'role' => UserRole::CASHIER]);
        $invite = $this->invite('emp_session' . $via);
        $invite->forceFill(['balance' => '7000.00', 'staff_code' => '4321', 'pin_hash' => bcrypt('1234'), 'pin_set_at' => now()])->save();
        $token = $invite->createToken('device-access')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/user')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withoutToken();

        $this->accept($via, 8383838, 'emp_session' . $via);

        $this->assertNull(User::find($invite->id));
        $this->assertSame('7000.00', (string) $existing->fresh()->balance);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function channels(): array
    {
        return ['bot' => ['bot'], 'mini app' => ['mini_app']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channels')]
    public function test_an_invite_row_without_keshbek_is_deleted_as_before(string $via): void
    {
        $existing = User::create(['name' => 'Kassir', 'telegram_id' => '8282828', 'store_id' => $this->store->id, 'role' => UserRole::CASHIER]);
        $invite = $this->invite('emp_plain' . $via);

        $this->accept($via, 8282828, 'emp_plain' . $via);

        $this->assertSame(UserRole::MANAGER, $existing->fresh()->role);
        $this->assertNull(User::find($invite->id));
    }

    private function invite(string $token): User
    {
        return User::create([
            'name' => 'Yangi menejer',
            'telegram_id' => 'temp_' . uniqid(),
            'store_id' => $this->store->id,
            'role' => UserRole::MANAGER,
            'telegram_invite_token' => $token,
        ]);
    }

    private function accept(string $via, int $telegramId, string $token): void
    {
        if ($via === 'bot') {
            $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1_000_000), 'message' => [
                'message_id' => 1,
                'from' => ['id' => $telegramId, 'first_name' => 'X'],
                'chat' => ['id' => $telegramId, 'type' => 'private'],
                'text' => "/start invite_{$token}",
            ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET])->assertOk()->assertJsonPath('status', 'employee_linked');

            return;
        }

        $fields = ['auth_date' => (string) time(), 'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc', 'user' => json_encode(['id' => $telegramId, 'first_name' => 'X'])];
        ksort($fields);
        $check = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true));
        $this->postJson('/api/v1/auth/telegram', ['initData' => http_build_query($fields), 'bot_username' => self::BOT_USERNAME, 'start_param' => "invite_{$token}"])
            ->assertOk()->assertJsonPath('data.user.role', UserRole::MANAGER->value);
    }
}
