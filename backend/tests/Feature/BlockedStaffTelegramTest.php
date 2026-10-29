<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\OrderAlertService;
use App\Services\Notifications\TelegramContent;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Review W3 S#2 / S#3: a blocked owner or manager cannot route the store's Telegram chat or claim an employee invite,
 * and no blocked staff member gets an order card (customer name, phone, address) or any other notification. The
 * store's own order chat still gets the card when the owner is blocked: the chat belongs to the store.
 */
class BlockedStaffTelegramTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'binding-test-secret';

    private Store $store;

    private Branch $branch;

    private int $seen = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.webhook_secret' => self::SECRET,
            'services.telegram.bot_token' => '123456:TEST-bot-token',
            'services.telegram.webapp_url' => 'https://app.example.uz/',
        ]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->store = Store::create(['name' => 'Bound Store', 'slug' => 'bound-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->branch = Branch::create(['store_id' => $this->store->id, 'name' => 'Markaz']);
    }

    public function test_a_blocked_owner_or_store_wide_manager_cannot_route_the_store_chat(): void
    {
        $this->store->forceFill(['telegram_admin_chat_id' => '-100111'])->save();
        $manager = $this->staff(UserRole::MANAGER, '7100001', null, blocked: true);
        $owner = $this->staff(UserRole::OWNER, '7100002', null, blocked: true);

        foreach ([$manager, $owner] as $person) {
            $this->bindFrom((int) $person->telegram_id, -100999)->assertOk()->assertJsonPath('status', 'store_link_forbidden');
        }
        $this->assertSame('-100111', (string) $this->store->fresh()->telegram_admin_chat_id);

        // Unblocked, the same person may
        $owner->forceFill(['blocked_at' => null])->save();
        $this->bindFrom((int) $owner->telegram_id, -100999)->assertOk()->assertJsonPath('status', 'store_linked');
    }

    public function test_a_blocked_account_cannot_claim_an_invite(): void
    {
        // A blocked placeholder's invite is no invite
        $blockedInvite = $this->staff(UserRole::MANAGER, null, $this->branch, blocked: true, attributes: ['telegram_invite_token' => 'emp_blocked1']);
        $this->start(7200001, 'invite_emp_blocked1')->assertOk()->assertJsonMissing(['status' => 'employee_linked']);
        $this->assertNull($blockedInvite->fresh()->telegram_id);
        $this->assertSame('emp_blocked1', $blockedInvite->fresh()->telegram_invite_token);

        // Someone blocked in this store does not get a fresh account (or a new role) through a new invite
        $blocked = $this->staff(UserRole::COURIER, '7200002', $this->branch, blocked: true);
        $invite = $this->staff(UserRole::MANAGER, null, null, attributes: ['telegram_invite_token' => 'emp_fresh1']);
        $this->start(7200002, 'invite_emp_fresh1')->assertOk()->assertJsonPath('status', 'employee_link_blocked');
        $this->assertSame(UserRole::COURIER, $blocked->fresh()->role);
        $this->assertNotNull($invite->fresh(), 'the invite was not used up');
        $this->assertSame('emp_fresh1', $invite->fresh()->telegram_invite_token);
        $this->assertNull($invite->fresh()->telegram_id);

        // Anyone else claims it as before
        $this->start(7200003, 'invite_emp_fresh1')->assertOk()->assertJsonPath('status', 'employee_linked');
        $this->assertSame('7200003', (string) $invite->fresh()->telegram_id);
    }

    public function test_the_mini_app_invite_is_refused_to_someone_blocked_in_the_store(): void
    {
        config(['services.telegram.bot_username' => 'rizqim_test_bot']);
        $this->store->forceFill(['is_active' => true])->save();
        $blocked = $this->staff(UserRole::COURIER, '7210001', $this->branch, blocked: true);
        $invite = $this->staff(UserRole::MANAGER, null, null, attributes: ['telegram_invite_token' => 'emp_mini1']);

        $this->miniAppLogin(7210001, 'invite_emp_mini1')->assertForbidden()->assertJsonPath('data.reason', 'account_blocked');
        $this->assertSame(UserRole::COURIER, $blocked->fresh()->role);
        $this->assertSame('emp_mini1', $invite->fresh()->telegram_invite_token);

        // A blocked placeholder's invite is no invite: the person signs in as who they are (here a new customer)
        $this->staff(UserRole::MANAGER, null, $this->branch, blocked: true, attributes: ['telegram_invite_token' => 'emp_mini2']);
        $this->miniAppLogin(7210002, 'invite_emp_mini2')->assertOk()->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
    }

    public function test_blocked_staff_get_no_order_card_but_the_store_chat_does(): void
    {
        $this->store->forceFill(['telegram_admin_chat_id' => '-100555'])->save();
        $blockedManager = $this->staff(UserRole::MANAGER, '7300001', $this->branch, blocked: true);
        $storeWide = $this->staff(UserRole::MANAGER, '7300002', null);
        $owner = $this->staff(UserRole::OWNER, '7300003', null, blocked: true);
        $order = $this->order();
        $cards = app(TelegramNotificationService::class);

        $this->assertSame(['-100555', '7300002'], $cards->orderCardChatIds($order));

        // Every manager blocked: the owners stand in, a blocked owner too is left out; the store chat stays
        $storeWide->forceFill(['blocked_at' => now()])->save();
        $this->assertSame(['-100555'], $cards->orderCardChatIds($order->fresh()));
        $this->staff(UserRole::OWNER, '7300004', null);
        $this->assertSame(['-100555', '7300004'], $cards->orderCardChatIds($order->fresh()));

        $cards->sendOrderCreated($order->fresh());
        $sent = $this->sentTo();
        $this->assertNotContains($blockedManager->telegram_id, $sent);
        $this->assertNotContains($owner->telegram_id, $sent);
        $this->assertContains('-100555', $sent);
    }

    public function test_a_delivery_card_skips_blocked_couriers_managers_and_owners(): void
    {
        $courier = $this->staff(UserRole::COURIER, '7400001', $this->branch, blocked: true);
        $manager = $this->staff(UserRole::MANAGER, '7400002', $this->branch, blocked: true);
        $standIn = $this->staff(UserRole::MANAGER, '7400003', null);
        $order = $this->order(['status' => 'delivery', 'courier_id' => $courier->id]);

        app(TelegramNotificationService::class)->sendOrderStatusChanged($order->fresh());

        $sent = $this->sentTo();
        $this->assertNotContains('7400001', $sent, 'a blocked courier got the customer\'s phone and address');
        $this->assertNotContains('7400002', $sent);
        $this->assertContains('7400003', $sent, 'the order still reaches someone who may finish it');
        $this->assertContains('7400099', $sent, 'the customer still hears about their order');
        unset($standIn);
    }

    public function test_the_router_never_reaches_a_blocked_account(): void
    {
        $router = app(NotificationRouter::class);
        $blocked = $this->staff(UserRole::COURIER, '7500001', $this->branch, blocked: true);
        $active = $this->staff(UserRole::COURIER, '7500002', $this->branch);

        $this->assertFalse($router->reachable($blocked));
        $this->assertSame([$active->id], $router->onlyReachable(User::where('store_id', $this->store->id)->where('role', UserRole::COURIER->value))->pluck('id')->all());
        $this->assertSame([], $router->send($blocked, new NotificationMessage(NotificationType::COURIER_TASKS, telegram: new TelegramContent('Salom'))));
        $this->assertSame([], $this->sentTo());

        // Ready for delivery, nobody assigned: only the active courier of the branch hears
        $order = $this->order(['status' => 'ready']);
        app(OrderAlertService::class)->statusChanged($order->fresh(), 'ready');
        $this->assertSame(['7500002'], $this->sentTo());

        // A new order to the branch managers (OrderAlertService)
        $this->staff(UserRole::MANAGER, '7500003', $this->branch, blocked: true);
        app(OrderAlertService::class)->newOrder($order->fresh());
        $this->assertNotContains('7500003', $this->sentTo());
    }

    private function staff(UserRole $role, ?string $telegramId, ?Branch $branch, bool $blocked = false, array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::forceCreate([
            'name' => ucfirst($role->value) . " {$n}",
            'telegram_id' => $telegramId,
            'store_id' => $this->store->id,
            'branch_id' => $branch?->id,
            'role' => $role->value,
            'blocked_at' => $blocked ? now() : null,
            'blocked_reason' => $blocked ? 'Firibgarlik' : null,
        ] + $attributes);
    }

    private function order(array $attributes = []): Order
    {
        $customer = User::firstOrCreate(['telegram_id' => '7400099'], ['name' => 'Mijoz', 'role' => UserRole::CUSTOMER, 'phone' => '+998901234567']);

        return Order::forceCreate($attributes + [
            'user_id' => $customer->id, 'store_id' => $this->store->id, 'branch_id' => $this->branch->id,
            'status' => 'new', 'total_price' => 10000, 'payment_method' => 'cash', 'payment_status' => 'pending',
            'order_type' => 'delivery',
            'shipping_address' => ['address' => 'Chilonzor 1', 'latitude' => 41.3, 'longitude' => 69.2],
        ]);
    }

    private function miniAppLogin(int $telegramId, string $startParam): \Illuminate\Testing\TestResponse
    {
        $fields = ['auth_date' => (string) time(), 'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc', 'user' => json_encode(['id' => $telegramId, 'first_name' => 'Ali'])];
        ksort($fields);
        $check = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', '123456:TEST-bot-token', 'WebAppData', true));

        return $this->postJson('/api/v1/auth/telegram', ['initData' => http_build_query($fields), 'bot_username' => 'rizqim_test_bot', 'start_param' => $startParam]);
    }

    private function start(int $from, string $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1_000_000), 'message' => [
            'message_id' => 1,
            'from' => ['id' => $from, 'first_name' => 'X'],
            'chat' => ['id' => $from, 'type' => 'private'],
            'text' => "/start {$payload}",
        ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET]);
    }

    private function bindFrom(int $from, int $chat): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1_000_000), 'message' => [
            'message_id' => 1,
            'from' => ['id' => $from, 'first_name' => 'X'],
            'chat' => ['id' => $chat, 'type' => 'group'],
            'text' => "/start store_{$this->store->uuid}",
        ]], ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET]);
    }

    /**
     * The chats Telegram messages went to since the last call.
     *
     * @return list<string>
     */
    private function sentTo(): array
    {
        $recorded = Http::recorded(fn (Request $request) => str_contains($request->url(), '/sendMessage'));
        $chats = $recorded->slice($this->seen)->map(fn ($pair) => (string) $pair[0]['chat_id'])->values()->all();
        $this->seen = $recorded->count();

        return $chats;
    }
}
