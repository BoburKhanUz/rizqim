<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Command Store', 'slug' => 'command-store', 'commission_rate' => 10, 'status' => 'active']);
    }

    private function customer(string $telegramId): User
    {
        return User::create(['name' => 'Bobur', 'telegram_id' => $telegramId, 'telegram_username' => 'bobur', 'role' => UserRole::CUSTOMER]);
    }

    public function test_assign_owner_role_creates_a_store_seat_and_keeps_the_customer_account(): void
    {
        $customer = $this->customer('1328662483');

        $this->artisan('user:assign-role', ['telegram_id' => '1328662483', 'role' => 'owner', '--store' => $this->store->id])
            ->assertSuccessful();

        $owner = User::where('telegram_id', '1328662483')->where('store_id', $this->store->id)->first();
        $this->assertNotNull($owner);
        $this->assertSame(UserRole::OWNER, $owner->role);
        $this->assertSame('bobur', $owner->telegram_username);
        $this->assertSame(UserRole::CUSTOMER, $customer->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cli_role_assigned', 'model_id' => $owner->id]);
    }

    public function test_assign_role_updates_an_existing_seat_and_accepts_store_uuid_and_branch(): void
    {
        $branch = Branch::create(['store_id' => $this->store->id, 'name' => 'Chilonzor']);
        $courier = User::create(['name' => 'Courier', 'telegram_id' => '4444', 'store_id' => $this->store->id, 'role' => UserRole::COURIER]);

        $this->artisan('user:assign-role', [
            'telegram_id' => '4444',
            'role' => 'manager',
            '--store' => $this->store->uuid,
            '--branch' => $branch->id,
        ])->assertSuccessful();

        $courier->refresh();
        $this->assertSame(UserRole::MANAGER, $courier->role);
        $this->assertSame($branch->id, $courier->branch_id);
        $this->assertSame(1, User::where('telegram_id', '4444')->count());
    }

    public function test_assign_role_adopts_a_storeless_staff_row_left_by_the_old_backdoor(): void
    {
        $orphanOwner = User::create(['name' => 'Bobur', 'telegram_id' => '1328662483', 'role' => UserRole::OWNER]);

        $this->artisan('user:assign-role', ['telegram_id' => '1328662483', 'role' => 'owner', '--store' => $this->store->id])
            ->assertSuccessful();

        $orphanOwner->refresh();
        $this->assertSame($this->store->id, $orphanOwner->store_id);
        $this->assertSame(UserRole::OWNER, $orphanOwner->role);
        $this->assertSame(1, User::where('telegram_id', '1328662483')->count());
    }

    public function test_assign_super_admin_updates_the_global_account(): void
    {
        $customer = $this->customer('2222');

        $this->artisan('user:assign-role', ['telegram_id' => '2222', 'role' => 'super_admin'])->assertSuccessful();

        $this->assertSame(UserRole::SUPER_ADMIN, $customer->fresh()->role);
    }

    public function test_assign_role_rejects_invalid_input(): void
    {
        $this->customer('3333');

        $this->artisan('user:assign-role', ['telegram_id' => '3333', 'role' => 'god'])->assertFailed();
        $this->artisan('user:assign-role', ['telegram_id' => '3333', 'role' => 'owner'])->assertFailed();
        $this->artisan('user:assign-role', ['telegram_id' => '3333', 'role' => 'owner', '--store' => 999999])->assertFailed();
        $this->artisan('user:assign-role', ['telegram_id' => '3333', 'role' => 'super_admin', '--store' => $this->store->id])->assertFailed();
        $this->artisan('user:assign-role', ['telegram_id' => '0000', 'role' => 'owner', '--store' => $this->store->id])->assertFailed();
        $this->artisan('user:assign-role', ['telegram_id' => 'abc', 'role' => 'customer'])->assertFailed();

        $this->assertSame(UserRole::CUSTOMER, User::where('telegram_id', '3333')->first()->role);
        $this->assertSame(1, User::where('telegram_id', '3333')->count());
    }

    /**
     * Fakes the Telegram API and the local webhook; the second getUpdates call throws an \Error
     * (not caught by the command's catch (\Exception)) so the endless polling loop terminates.
     */
    private function runTelegramPollOnce(): ?int
    {
        $getUpdatesCalls = 0;

        Http::fake(function (HttpClientRequest $request) use (&$getUpdatesCalls) {
            if (str_contains($request->url(), '/getUpdates')) {
                if (++$getUpdatesCalls > 1) {
                    throw new \Error('stop polling loop');
                }

                return Http::response(['ok' => true, 'result' => [['update_id' => 42]]]);
            }

            return Http::response(['ok' => true, 'status' => 'ignored']);
        });

        try {
            return Artisan::call('telegram:poll');
        } catch (\Error $e) {
            $this->assertSame('stop polling loop', $e->getMessage());

            return null;
        }
    }

    public function test_telegram_poll_forwards_updates_with_the_webhook_secret_header(): void
    {
        config(['services.telegram.bot_token' => '123456:TEST-bot-token', 'services.telegram.webhook_secret' => 'poll-secret']);

        $this->runTelegramPollOnce();

        Http::assertSent(fn (HttpClientRequest $request) => str_ends_with($request->url(), '/api/v1/telegram/webhook')
            && $request->hasHeader('X-Telegram-Bot-Api-Secret-Token', 'poll-secret'));
    }

    public function test_telegram_poll_refuses_to_start_without_a_webhook_secret(): void
    {
        config(['services.telegram.bot_token' => '123456:TEST-bot-token', 'services.telegram.webhook_secret' => null]);

        $this->assertSame(1, $this->runTelegramPollOnce());

        // Fails before deleteWebhook, so a misconfigured dev box cannot detach the bot's real webhook
        Http::assertNothingSent();
    }

    private function safeProductionConfig(): void
    {
        config(['app.env' => 'production', 'app.debug' => false, 'telegram.allow_mock_auth' => false, 'services.telegram.webhook_secret' => 'valid_secret-1',
            'services.telegram.webapp_url' => 'https://app.rizqim.example/', 'app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);
    }

    public function test_production_check_requires_one_app_key_and_the_mini_app_url(): void
    {
        $this->safeProductionConfig();
        $this->artisan('app:check-production')->assertSuccessful();

        // Every container would otherwise make up its own key
        config(['app.key' => '']);
        $this->artisan('app:check-production')->expectsOutputToContain('APP_KEY')->assertFailed();

        $this->safeProductionConfig();
        config(['services.telegram.webapp_url' => null]);
        $this->artisan('app:check-production')->expectsOutputToContain('TELEGRAM_WEBAPP_URL')->assertFailed();

        config(['services.telegram.webapp_url' => 'http://app.rizqim.example/']);
        $this->artisan('app:check-production')->assertFailed();
    }

    public function test_production_check_fails_without_a_valid_webhook_secret(): void
    {
        $this->safeProductionConfig();
        $this->artisan('app:check-production')->assertSuccessful();

        config(['services.telegram.webhook_secret' => null]);
        $this->artisan('app:check-production')->assertFailed();

        config(['services.telegram.webhook_secret' => 'has spaces & symbols!']);
        $this->artisan('app:check-production')->assertFailed();
    }

    public function test_production_check_refuses_reverb_without_keys_and_warns_on_any_origin(): void
    {
        $this->safeProductionConfig();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.app_id' => '', 'broadcasting.connections.reverb.key' => '',
            'broadcasting.connections.reverb.secret' => '']);
        $this->artisan('app:check-production')->expectsOutputToContain('REVERB_APP_SECRET')->assertFailed();

        config(['broadcasting.connections.reverb.app_id' => 'a1b2c3d4', 'broadcasting.connections.reverb.key' => str_repeat('k', 32)]);
        $this->artisan('app:check-production')->expectsOutputToContain('REVERB_APP_SECRET')->assertFailed();

        config(['broadcasting.connections.reverb.secret' => str_repeat('s', 64), 'reverb.apps.apps.0.allowed_origins' => ['*']]);
        $this->artisan('app:check-production')->expectsOutputToContain('REVERB_ALLOWED_ORIGINS')->assertSuccessful();
        // P2-33: the warning names every screen that opens the socket (docs/DEPLOY.md §9)
        $this->artisan('app:check-production')->expectsOutputToContain('panel.<domen>')->assertSuccessful();
        $this->artisan('app:check-production')->expectsOutputToContain('RIZQIM_API_URL')->assertSuccessful();

        config(['reverb.apps.apps.0.allowed_origins' => ['mini.example.uz']]);
        $this->artisan('app:check-production')->doesntExpectOutputToContain('REVERB_ALLOWED_ORIGINS')->assertSuccessful();

        // Real-time switched off: blank keys are harmless
        config(['broadcasting.default' => 'null', 'broadcasting.connections.reverb.secret' => '']);
        $this->artisan('app:check-production')->assertSuccessful();
    }

    public function test_production_check_fails_while_a_super_admin_keeps_the_default_password(): void
    {
        $this->safeProductionConfig();
        $admin = User::create(['name' => 'Seeded', 'email' => 'admin@gmail.com', 'password' => Hash::make('12345678'), 'role' => UserRole::SUPER_ADMIN]);

        $this->artisan('app:check-production')->assertFailed();

        $admin->update(['password' => Hash::make('rotated-strong-passphrase')]);
        $this->artisan('app:check-production')->assertSuccessful();
    }

    public function test_set_webhook_registers_url_secret_and_allowed_updates(): void
    {
        config([
            'services.telegram.bot_token' => '123456:TEST-bot-token',
            'services.telegram.webhook_secret' => 'valid_secret-1',
            'telegram.webhook_url' => 'https://food.example.uz/api/v1/telegram/webhook',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

        $this->artisan('telegram:set-webhook')->assertSuccessful();

        Http::assertSent(fn (HttpClientRequest $request) => str_ends_with($request->url(), '/bot123456:TEST-bot-token/setWebhook')
            && $request['url'] === 'https://food.example.uz/api/v1/telegram/webhook'
            && $request['secret_token'] === 'valid_secret-1'
            && $request['allowed_updates'] === ['message', 'callback_query']);
    }

    public function test_set_webhook_refuses_bad_configuration_and_never_prints_the_token(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 6 for https://api.telegram.org/bot123456:TEST-bot-token/setWebhook'));

        config(['services.telegram.bot_token' => null]);
        $this->artisan('telegram:set-webhook', ['--if-configured' => true])->assertSuccessful();
        $this->artisan('telegram:set-webhook')->assertFailed();

        config(['services.telegram.bot_token' => '123456:TEST-bot-token', 'services.telegram.webhook_secret' => null]);
        $this->artisan('telegram:set-webhook', ['--url' => 'https://food.example.uz/hook'])->assertFailed();

        config(['services.telegram.webhook_secret' => 'valid_secret-1']);
        $this->artisan('telegram:set-webhook', ['--url' => 'http://food.example.uz/hook'])->assertFailed();
        Http::assertNothingSent();

        $exitCode = Artisan::call('telegram:set-webhook', ['--url' => 'https://food.example.uz/hook']);
        $this->assertSame(1, $exitCode);
        $this->assertStringNotContainsString('TEST-bot-token', Artisan::output());
    }

    public function test_production_check_rejects_unsafe_settings(): void
    {
        $this->safeProductionConfig();
        config(['services.telegram.webhook_secret' => 's']);
        $this->artisan('app:check-production')->assertSuccessful();

        config(['app.env' => 'local']);
        $this->artisan('app:check-production')->assertFailed();

        config(['app.env' => 'production', 'app.debug' => true]);
        $this->artisan('app:check-production')->assertFailed();

        config(['app.debug' => false, 'telegram.allow_mock_auth' => true]);
        $this->artisan('app:check-production')->assertFailed();
    }
}
