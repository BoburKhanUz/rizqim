<?php

namespace Tests\Feature;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\StoreTransaction;
use App\Models\TelegramAction;
use App\Models\User;
use App\Services\Auth\ReferralService;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderLifecycleTelegramTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.webhook_secret' => 'lifecycle-secret',
            'services.telegram.bot_token' => '123456:fake-token',
        ]);
        Http::fake();

        $this->setUpLifecycleStore();
        $this->manager->update(['telegram_id' => '5001']);
        $this->courier->update(['telegram_id' => '5002']);
        $this->owner->update(['telegram_id' => '5003']);
    }

    protected function webhook(array $update)
    {
        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1000000)] + $update, [
            'X-Telegram-Bot-Api-Secret-Token' => 'lifecycle-secret',
        ]);
    }

    protected function button(Order $order, string $action): string
    {
        $token = Str::random(32);
        TelegramAction::create([
            'order_id' => $order->id,
            'action' => $action,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        return $token;
    }

    protected function press(string $token, string $telegramUserId, ?string $chatId = null)
    {
        return $this->webhook(['callback_query' => [
            'id' => 'cb-' . Str::random(8),
            'from' => ['id' => (int) $telegramUserId, 'first_name' => 'Tester'],
            'message' => ['message_id' => 42, 'chat' => ['id' => (int) ($chatId ?? $telegramUserId)]],
            'data' => $token,
        ]]);
    }

    protected function start(string $payload, string $telegramUserId, ?string $chatId = null)
    {
        return $this->webhook(['message' => [
            'message_id' => 1,
            'from' => ['id' => (int) $telegramUserId, 'first_name' => 'New', 'username' => 'new_user'],
            'chat' => ['id' => (int) ($chatId ?? $telegramUserId)],
            'text' => trim("/start {$payload}"),
        ]]);
    }

    public function test_manager_accept_button_goes_through_the_status_service(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->press($this->button($order, 'accept'), '5001')->assertJsonPath('status', 'success');

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertSame($this->manager->id, $order->statusHistories()->where('status', 'accepted')->value('user_id'));
    }

    public function test_unknown_presser_is_refused_and_the_button_stays_usable(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $token = $this->button($order, 'cancel');
        $this->makeCustomer(0, ['telegram_id' => '6001']);

        // A group member without an account, and a customer, both pressing "Rad etish"
        $this->press($token, '9999', '-100500')->assertJsonPath('status', 'forbidden');
        $this->press($token, '6001')->assertJsonPath('status', 'forbidden');
        // Staff of another store with the same chat
        $otherStore = $this->makeStore('Other Store');
        $this->makeStaff(UserRole::MANAGER, null, $otherStore, ['telegram_id' => '6002']);
        $this->press($token, '6002')->assertJsonPath('status', 'forbidden');

        // ...and the reason buttons behind it are refused the same way
        $this->press("{$token}:out_of_stock", '9999', '-100500')->assertJsonPath('status', 'forbidden');
        $this->press("{$token}:out_of_stock", '6002')->assertJsonPath('status', 'forbidden');

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
        $this->assertNull(TelegramAction::where('token', $token)->value('used_at'));

        $this->press($token, '5001')->assertJsonPath('status', 'choose_reason');
        $this->press("{$token}:out_of_stock", '5001')->assertJsonPath('status', 'success');
        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
        $this->assertSame(CancelReason::OUT_OF_STOCK, $order->fresh()->cancel_reason);
    }

    public function test_cancel_button_pressed_twice_restores_stock_once(): void
    {
        $order = $this->checkout($this->customer, 3);
        $first = $this->button($order, 'cancel');
        $second = $this->button($order, 'cancel'); // same card sent to the admin chat and to a manager

        $this->press($first, '5001')->assertJsonPath('status', 'choose_reason');
        $this->press("{$first}:store_closed", '5001')->assertJsonPath('status', 'success');
        $this->press("{$first}:store_closed", '5001')->assertJsonPath('status', 'error');
        $this->press($first, '5001')->assertJsonPath('status', 'error');
        $this->press($second, '5003')->assertJsonPath('status', 'no_change');
        $this->press("{$second}:out_of_stock", '5003')->assertJsonPath('status', 'no_change');

        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_old_reject_button_cannot_cancel_an_accepted_order(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $reject = $this->button($order, 'cancel');

        $this->press($reject, '5001')->assertJsonPath('status', 'choose_reason');
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->press($reject, '5001')->assertJsonPath('status', 'cannot_cancel');
        $this->press("{$reject}:out_of_stock", '5001')->assertJsonPath('status', 'cannot_cancel');

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertNull($order->fresh()->cancel_reason);
    }

    /**
     * The reason buttons Telegram was asked to show on the card (editMessageReplyMarkup), as text => callback_data.
     *
     * @return array<string, string>
     */
    protected function shownReasonButtons(): array
    {
        $request = Http::recorded()->map(fn ($pair) => $pair[0])
            ->filter(fn ($request) => str_ends_with($request->url(), '/editMessageReplyMarkup'))
            ->last();
        $this->assertNotNull($request, 'No reason keyboard was shown');

        $buttons = [];
        foreach (json_decode($request['reply_markup'], true)['inline_keyboard'] as $row) {
            foreach ($row as $button) {
                $buttons[$button['text']] = $button['callback_data'];
            }
        }

        return $buttons;
    }

    public function test_reject_button_shows_the_reasons_within_telegrams_callback_limit_and_changes_nothing(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $token = $this->button($order, 'cancel');

        $this->press($token, '5001')->assertJsonPath('status', 'choose_reason');

        $buttons = $this->shownReasonButtons();
        foreach ($buttons as $data) {
            $this->assertLessThanOrEqual(64, strlen($data));
            $this->assertStringStartsWith("{$token}:", $data);
        }
        $reasons = array_map(fn ($data) => substr($data, 33), array_values($buttons));
        // A new delivery order: no "customer never came", and never the system's payment timeout
        $this->assertEqualsCanonicalizing(['customer_request', 'out_of_stock', 'store_closed', 'courier_unavailable', 'other', 'back'], $reasons);
        $this->assertSame("{$token}:courier_unavailable", $buttons["Kuryer yo'q"]);

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
        $this->assertNull(TelegramAction::where('token', $token)->value('used_at'));

        // A pickup order is never offered "no courier"
        $pickup = $this->makeOrder(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->press($this->button($pickup, 'cancel'), '5001')->assertJsonPath('status', 'choose_reason');
        $this->assertNotContains("Kuryer yo'q", array_keys($this->shownReasonButtons()));
    }

    public function test_reason_buttons_only_accept_the_offered_reasons(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW, ['order_type' => 'pickup']);
        $token = $this->button($order, 'cancel');

        foreach (['payment_timeout', 'no_show', 'courier_unavailable', 'bogus', ''] as $reason) {
            $this->press("{$token}:{$reason}", '5001')->assertJsonPath('status', 'invalid_reason');
        }
        // A reason appended to another button's token, or to an unknown one
        $this->press($this->button($order, 'accept') . ':out_of_stock', '5001')->assertJsonPath('status', 'error');
        $this->press(Str::random(32) . ':out_of_stock', '5001')->assertJsonPath('status', 'error');

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
        $this->assertNull(TelegramAction::where('token', $token)->value('used_at'));

        // "Orqaga" brings the card back untouched
        $this->press("{$token}:back", '5001')->assertJsonPath('status', 'back');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/editMessageText') && str_contains($request['text'], "#{$order->short_code}"));
        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
    }

    public function test_cancel_after_acceptance_offers_no_show_and_records_who_cancelled(): void
    {
        $order = $this->checkout($this->customer, 2);
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->setStatus($order, $this->manager, 'preparing')->assertOk();

        // The card of a preparing pickup order carries the "Bekor qilish" button
        [, $keyboard] = app(TelegramNotificationService::class)->buildMessageAndKeyboard($order->fresh());
        $abort = collect($keyboard['inline_keyboard'])->flatten(1)->firstWhere('text', '❌ Bekor qilish');
        $this->assertSame('abort', TelegramAction::where('token', $abort['callback_data'])->value('action'));

        $this->press($abort['callback_data'], '5001')->assertJsonPath('status', 'choose_reason');
        $this->assertSame("{$abort['callback_data']}:no_show", $this->shownReasonButtons()['Mijoz kelmadi']);

        $this->press("{$abort['callback_data']}:no_show", '5001')->assertJsonPath('status', 'success');

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertSame(CancelReason::NO_SHOW, $order->cancel_reason);
        $this->assertSame($this->manager->id, $order->cancelled_by);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
        $this->assertTrue(Order::cashPickupBanApplies($this->customer->id));

        // The refreshed card names the reason
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/editMessageText')
            && str_contains($request['text'], 'Bekor qilindi') && str_contains($request['text'], 'Mijoz kelmadi'));
    }

    public function test_cancelling_an_accepted_delivery_order_never_offers_no_show(): void
    {
        $order = $this->makeOrder(OrderStatus::PREPARING, ['order_type' => 'delivery']);
        $token = $this->button($order, 'abort');

        $this->press($token, '5001')->assertJsonPath('status', 'choose_reason');
        $this->assertNotContains('Mijoz kelmadi', array_keys($this->shownReasonButtons()));

        $this->press("{$token}:no_show", '5001')->assertJsonPath('status', 'invalid_reason');
        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
    }

    public function test_courier_buttons_work_only_for_their_own_delivery(): void
    {
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);
        $otherCourier = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => '5004']);

        $this->press($this->button($order, 'take'), '5004')->assertJsonPath('status', 'forbidden');
        $this->press($this->button($order, 'prepare'), '5002')->assertJsonPath('status', 'forbidden');
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status);

        $this->press($this->button($order, 'take'), '5002')->assertJsonPath('status', 'success');
        $this->press($this->button($order, 'complete'), '5002')->assertJsonPath('status', 'success');

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)->count());
        $this->assertSame($this->courier->id, StoreTransaction::where('order_id', $order->id)->value('performed_by'));
    }

    public function test_deliver_button_auto_assigns_and_pickup_ready_button_completes(): void
    {
        $delivery = $this->makeOrder(OrderStatus::PREPARING);
        $pickup = $this->makeOrder(OrderStatus::PREPARING, ['order_type' => 'pickup']);

        $this->press($this->button($delivery, 'deliver'), '5001')->assertJsonPath('status', 'success');
        $this->press($this->button($pickup, 'complete'), '5001')->assertJsonPath('status', 'success');

        $delivery->refresh();
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $delivery->status);
        $this->assertSame($this->courier->id, $delivery->courier_id);
        $this->assertSame(OrderStatus::DELIVERED, $pickup->fresh()->status);
    }

    public function test_order_cards_only_reach_managers_who_may_act_on_the_order(): void
    {
        $storeWideManager = $this->makeStaff(UserRole::MANAGER, null, null, ['telegram_id' => '5005']);
        $otherBranchManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch, null, ['telegram_id' => '5006']);
        $sentTo = fn () => Http::recorded()->map(fn ($pair) => (string) ($pair[0]['chat_id'] ?? ''))->filter()->values()->all();
        $service = app(TelegramNotificationService::class);

        $service->sendOrderCreated($this->makeOrder(OrderStatus::NEW));

        $this->assertEqualsCanonicalizing(['5001', '5005'], $sentTo());
        $this->assertNotContains($otherBranchManager->telegram_id, $sentTo());

        // Every card a manager gets can be acted on
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->press($this->button($order, 'accept'), '5005')->assertJsonPath('status', 'success');
        $this->press($this->button($order, 'prepare'), '5006')->assertJsonPath('status', 'forbidden');

        // A branch without an eligible manager falls back to the owner, not to other branches' managers
        $storeWideManager->delete();
        $this->manager->update(['telegram_id' => null]);
        Http::fake();
        $service->sendOrderCreated($this->makeOrder(OrderStatus::NEW));

        $this->assertSame(['5003'], $sentTo());
    }

    public function test_only_the_stores_owner_or_store_wide_manager_can_bind_its_order_chat(): void
    {
        $store = $this->store;

        $this->start("store_{$store->uuid}", '7001', '-100700')->assertJsonPath('status', 'store_link_forbidden');
        $this->start("store_{$store->uuid}", '5002', '-100700')->assertJsonPath('status', 'store_link_forbidden'); // courier
        $this->start("store_{$store->uuid}", '5001', '-100700')->assertJsonPath('status', 'store_link_forbidden'); // branch manager
        $otherStore = $this->makeStore('Other Store');
        $this->makeStaff(UserRole::OWNER, null, $otherStore, ['telegram_id' => '7002']);
        $this->start("store_{$store->uuid}", '7002', '-100700')->assertJsonPath('status', 'store_link_forbidden');
        $this->assertNull($store->fresh()->telegram_admin_chat_id);

        Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', 'faqat shu restoranning egasi yoki filialga biriktirilmagan menejeri'));

        $this->start("store_{$store->uuid}", '5003', '-100700')->assertJsonPath('status', 'store_linked');
        $this->assertSame('-100700', (string) $store->fresh()->telegram_admin_chat_id);

        // A store-wide manager (no branch) may move it, and the chat that stops receiving orders is told
        $this->makeStaff(UserRole::MANAGER, null, null, ['telegram_id' => '5005']);
        $this->start("store_{$store->uuid}", '5005', '-100701')->assertJsonPath('status', 'store_linked');
        $this->assertSame('-100701', (string) $store->fresh()->telegram_admin_chat_id);
        Http::assertSent(fn ($request) => (string) ($request['chat_id'] ?? '') === '-100700'
            && str_contains($request['text'] ?? '', 'boshqa chatga'));

        $this->start('store_not-a-uuid', '5003', '-100800')->assertOk();
        $this->assertSame('-100701', (string) $store->fresh()->telegram_admin_chat_id);
    }

    public function test_referral_link_is_parked_for_a_new_telegram_user_only(): void
    {
        $referrals = app(ReferralService::class);
        $referrer = $this->makeCustomer(0, ['telegram_id' => '8001']);

        $this->start("rizqim_{$referrer->id}", '8100')->assertJsonPath('status', 'request_contact_sent');
        $this->assertSame($referrer->id, Cache::get($referrals->pendingKey('8100')));

        $this->start("ref_{$referrer->id}", '8101');
        $this->assertSame($referrer->id, Cache::get($referrals->pendingKey('8101')));

        // Existing account, self-referral, unknown referrer and malformed ids park nothing
        $this->makeCustomer(0, ['telegram_id' => '8102']);
        $this->start("rizqim_{$referrer->id}", '8102');
        $this->start("rizqim_{$referrer->id}", '8001');
        $this->start('rizqim_999999', '8103');
        $this->start('rizqim_abc', '8104');

        foreach (['8102', '8001', '8103', '8104'] as $telegramId) {
            $this->assertNull(Cache::get($referrals->pendingKey($telegramId)), $telegramId);
        }
    }

    public function test_contact_share_registration_applies_the_parked_referral_once(): void
    {
        $referrals = app(ReferralService::class);
        $referrer = $this->makeCustomer(0, ['telegram_id' => '8001']);
        $contact = fn (string $telegramId) => $this->webhook(['message' => [
            'message_id' => 2,
            'from' => ['id' => (int) $telegramId, 'first_name' => 'Ali'],
            'chat' => ['id' => (int) $telegramId],
            'contact' => ['phone_number' => '998901234567', 'user_id' => (int) $telegramId],
        ]]);

        $this->start("rizqim_{$referrer->id}", '8200');
        $contact('8200')->assertJsonPath('status', 'contact_linked');

        $newUser = User::where('telegram_id', '8200')->sole();
        $this->assertSame($referrer->id, $newUser->referred_by);
        $this->assertNull(Cache::get($referrals->pendingKey('8200')));

        // An existing account sharing its contact again is never re-referred, even with a parked key
        $existing = $this->makeCustomer(0, ['telegram_id' => '8201']);
        $referrals->storePendingReferral('8201', $referrer->id);
        $contact('8201')->assertJsonPath('status', 'contact_linked');

        $this->assertNull($existing->fresh()->referred_by);
        $this->assertSame('+998901234567', $existing->fresh()->phone);
    }
}
