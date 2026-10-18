<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTelegramWebhookBindingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'binding-test-secret';

    protected Store $store;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.webhook_secret' => self::SECRET,
            'services.telegram.bot_token' => '123456:TEST-bot-token',
            'services.telegram.webapp_url' => 'https://app.example.uz/',
        ]);
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->store = Store::create(['name' => 'Bound Store', 'slug' => 'bound-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->store->forceFill(['telegram_admin_chat_id' => '-100111'])->save();
        $this->branch = Branch::create(['store_id' => $this->store->id, 'name' => 'Chilonzor']);
    }

    private function member(UserRole $role, string $telegramId, ?int $branchId = null): User
    {
        return User::create(['name' => 'Staff', 'telegram_id' => $telegramId, 'store_id' => $this->store->id, 'branch_id' => $branchId, 'role' => $role]);
    }

    private function update(array $message)
    {
        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => 1, 'message' => $message], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET]);
    }

    private function startStoreLink(int $fromId, int $chatId)
    {
        return $this->update([
            'message_id' => 1,
            'from' => ['id' => $fromId, 'first_name' => 'X'],
            'chat' => ['id' => $chatId, 'type' => $chatId < 0 ? 'group' : 'private'],
            'text' => "/start store_{$this->store->uuid}",
        ]);
    }

    private function contact(int $fromId, ?int $contactUserId, string $phone = '998901234567')
    {
        return $this->update([
            'message_id' => 2,
            'from' => ['id' => $fromId, 'first_name' => 'X'],
            'chat' => ['id' => $fromId, 'type' => 'private'],
            'contact' => array_filter(['phone_number' => $phone, 'first_name' => 'X', 'user_id' => $contactUserId]),
        ]);
    }

    public function test_branch_manager_and_courier_cannot_rebind_the_store_chat(): void
    {
        $this->member(UserRole::MANAGER, '7100001', $this->branch->id);
        $this->member(UserRole::COURIER, '7100002', $this->branch->id);

        $this->startStoreLink(7100001, -100999)->assertOk()->assertJsonPath('status', 'store_link_forbidden');
        $this->startStoreLink(7100002, -100999)->assertOk()->assertJsonPath('status', 'store_link_forbidden');
        $this->startStoreLink(7100099, -100999)->assertOk()->assertJsonPath('status', 'store_link_forbidden');

        $this->assertSame('-100111', (string) $this->store->fresh()->telegram_admin_chat_id);
    }

    public function test_owner_and_store_wide_manager_can_bind_and_the_replaced_chat_is_notified(): void
    {
        $this->member(UserRole::OWNER, '7100003');
        $this->member(UserRole::MANAGER, '7100004');

        $this->startStoreLink(7100003, -100222)->assertOk()->assertJsonPath('status', 'store_linked');
        $this->assertSame('-100222', (string) $this->store->fresh()->telegram_admin_chat_id);
        Http::assertSent(fn (HttpClientRequest $request) => str_ends_with($request->url(), '/sendMessage')
            && (string) $request['chat_id'] === '-100111'
            && str_contains($request['text'], 'boshqa chatga'));

        $this->startStoreLink(7100004, -100333)->assertOk()->assertJsonPath('status', 'store_linked');
        $this->assertSame('-100333', (string) $this->store->fresh()->telegram_admin_chat_id);
    }

    public function test_contact_is_accepted_only_when_it_is_the_senders_own(): void
    {
        $user = User::create(['name' => 'Customer', 'telegram_id' => '7200001', 'role' => UserRole::CUSTOMER]);

        // Forwarded card of someone else, and a card without user_id (e.g. from the address book)
        $this->contact(7200001, 7299999, '998907777777')->assertOk()->assertJsonPath('status', 'ignored');
        $this->contact(7200001, null, '998907777777')->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertNull($user->fresh()->phone);

        $this->contact(7200002, null, '998906666666')->assertOk();
        $this->assertDatabaseMissing('users', ['telegram_id' => '7200002']);

        $this->contact(7200001, 7200001, '998901234567')->assertOk()->assertJsonPath('status', 'contact_linked');
        $this->assertSame('+998901234567', $user->fresh()->phone);
    }
}
