<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\SettlementCompleted;
use App\Events\StoreBalanceLow;
use App\Jobs\CheckUnacceptedAssignmentsJob;
use App\Jobs\SendOrderReport;
use App\Jobs\SendTelegramRequest;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\TelegramAction;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every Telegram Bot API call leaves the request (and any transaction) as a queued job that retries outages.
 */
class TelegramQueuedDispatchTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const TOKEN = '123456:queue-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => self::TOKEN,
            'services.telegram.webhook_secret' => 'queue-secret',
        ]);

        $this->setUpLifecycleStore();
        $this->manager->update(['telegram_id' => '5001']);
        $this->courier->update(['telegram_id' => '5002']);
        $this->owner->update(['telegram_id' => '5003']);
        $this->customer->update(['telegram_id' => '5009']);
    }

    protected function fakeTelegram(mixed $response = null): void
    {
        Http::fake(['*' => $response ?? Http::response(['ok' => true, 'result' => true])]);
    }

    protected function webhook(array $update)
    {
        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1000000)] + $update, [
            'X-Telegram-Bot-Api-Secret-Token' => 'queue-secret',
        ]);
    }

    protected function customerAlert(): SendTelegramRequest
    {
        return (new SendTelegramRequest('sendMessage', ['chat_id' => '5009', 'text' => 'Salom', 'parse_mode' => 'Markdown'], [
            'user_id' => $this->customer->id,
            'order_id' => null,
            'type' => 'telegram_customer_accepted',
        ]))->withFakeQueueInteractions();
    }

    protected function feedEntry(): ?NotificationLog
    {
        return NotificationLog::where('user_id', $this->customer->id)->where('type', 'telegram_customer_accepted')->sole();
    }

    public function test_a_status_change_queues_its_alerts_instead_of_calling_telegram(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendTelegramRequest::class]);
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        Http::assertNothingSent();
        // The OrderStatusUpdated listener's customer alert, carrying the customer's feed entry
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->method === 'sendMessage'
            && $job->params['chat_id'] === '5009'
            && str_contains($job->params['text'], 'Buyurtmangiz qabul qilindi!')
            && $job->notification === ['user_id' => $this->customer->id, 'order_id' => $order->id, 'type' => 'telegram_customer_accepted']);
        // The feed entry is written by the job, with the outcome of the send
        $this->assertSame(0, NotificationLog::where('order_id', $order->id)->count());
    }

    public function test_checkout_queues_the_managers_alert_and_the_order_card(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendTelegramRequest::class]);

        $orderUuid = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ])->assertCreated()->json('data.order_uuid');
        $order = Order::where('uuid', $orderUuid)->sole();

        Http::assertNothingSent();
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '5001'
            && $job->notification === ['user_id' => $this->manager->id, 'order_id' => $order->id, 'type' => 'telegram_manager_new_order']);
        // The card's action buttons (TelegramAction tokens) exist before the worker sends it
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '5001'
            && $job->notification === null
            && str_contains($job->params['reply_markup'] ?? '', TelegramAction::where('order_id', $order->id)->where('action', 'accept')->value('token')));
    }

    public function test_a_send_waits_for_its_transaction_and_is_dropped_with_its_rollback(): void
    {
        $this->fakeTelegram();
        $telegram = app(TelegramMessenger::class);

        try {
            DB::transaction(function () use ($telegram) {
                $telegram->sendMessage('5001', 'rolled back');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        DB::transaction(function () use ($telegram) {
            $telegram->sendMessage('5001', 'committed');
            Http::assertNothingSent();
        });

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && $request['text'] === 'committed');
    }

    public function test_nothing_is_queued_without_a_bot_token(): void
    {
        config(['services.telegram.bot_token' => null]);
        Queue::fake([SendTelegramRequest::class]);

        app(TelegramMessenger::class)->sendMessage('5001', 'Salom');
        $this->setStatus($this->makeOrder(OrderStatus::NEW), $this->manager, 'accepted')->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_a_sent_message_is_recorded_once_as_sent(): void
    {
        $this->fakeTelegram();
        $job = $this->customerAlert();

        $job->handle(app(TelegramBotApi::class));

        $job->assertNotReleased();
        $this->assertSame(['sent', null], [$this->feedEntry()->status, $this->feedEntry()->error_message]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage'
            && $request['chat_id'] === '5009' && $request['parse_mode'] === 'Markdown');
    }

    public function test_outages_and_rate_limits_are_retried_later_and_recorded_only_at_the_end(): void
    {
        $this->fakeTelegram(Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Gateway'], 502)
            ->push(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 7]], 429)
            ->push(['ok' => false, 'description' => 'Bad Gateway'], 502));

        $outage = $this->customerAlert();
        $outage->handle(app(TelegramBotApi::class));
        $outage->assertReleased(10);

        $rateLimited = $this->customerAlert();
        $rateLimited->job->attempts = 2;
        $rateLimited->handle(app(TelegramBotApi::class));
        $rateLimited->assertReleased(7);

        $this->assertSame(0, NotificationLog::count());

        $lastAttempt = $this->customerAlert();
        $lastAttempt->job->attempts = $lastAttempt->tries;
        $lastAttempt->handle(app(TelegramBotApi::class));

        $lastAttempt->assertNotReleased();
        $this->assertSame('failed', $this->feedEntry()->status);
        $this->assertStringContainsString('Bad Gateway', $this->feedEntry()->error_message);
    }

    public function test_connection_errors_are_retried_and_never_log_the_bot_token(): void
    {
        $this->fakeTelegram(fn () => throw new ConnectionException('cURL error 28: timed out for https://api.telegram.org/bot' . self::TOKEN . '/sendMessage'));

        $first = $this->customerAlert();
        $first->handle(app(TelegramBotApi::class));
        $first->assertReleased(10);

        $last = $this->customerAlert();
        $last->job->attempts = $last->tries;
        $last->handle(app(TelegramBotApi::class));

        $error = $this->feedEntry()->error_message;
        $this->assertStringContainsString('cURL error 28', $error);
        $this->assertStringNotContainsString(self::TOKEN, $error);
    }

    public function test_a_call_telegram_refuses_is_not_retried(): void
    {
        $this->fakeTelegram(Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403));
        $job = $this->customerAlert();

        $job->handle(app(TelegramBotApi::class));

        $job->assertNotReleased();
        $this->assertSame('failed', $this->feedEntry()->status);
        $this->assertStringContainsString('bot was blocked by the user', $this->feedEntry()->error_message);
    }

    public function test_a_telegram_outage_never_fails_the_request_on_the_sync_queue(): void
    {
        $this->fakeTelegram(Http::response(['ok' => false], 502));
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertSame('failed', NotificationLog::where('order_id', $order->id)->where('type', 'telegram_customer_accepted')->value('status'));
    }

    public function test_a_button_press_queues_its_answer_and_the_refreshed_card(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendTelegramRequest::class]);
        $order = $this->makeOrder(OrderStatus::NEW);
        $token = Str::random(32);
        TelegramAction::create(['order_id' => $order->id, 'action' => 'accept', 'token' => $token, 'expires_at' => now()->addHour()]);

        $this->webhook(['callback_query' => [
            'id' => 'cb-1',
            'from' => ['id' => 5001, 'first_name' => 'Ali'],
            'message' => ['message_id' => 42, 'chat' => ['id' => -100500]],
            'data' => $token,
        ]])->assertOk()->assertJsonPath('status', 'success');

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->method === 'editMessageText'
            && $job->params['chat_id'] === '-100500'
            && $job->params['message_id'] === '42'
            && str_contains($job->params['text'], 'Buyurtma qabul qilindi'));
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->method === 'answerCallbackQuery'
            && $job->params === ['callback_query_id' => 'cb-1', 'text' => 'Buyurtma holati: Qabul qilindi!', 'show_alert' => false]);
    }

    public function test_contact_replies_are_chained_so_they_arrive_in_order(): void
    {
        $this->fakeTelegram();
        Bus::fake();

        $this->webhook(['message' => [
            'message_id' => 2,
            'from' => ['id' => 8300, 'first_name' => 'Ali'],
            'chat' => ['id' => 8300],
            'contact' => ['phone_number' => '998901234567', 'user_id' => 8300],
        ]])->assertJsonPath('status', 'contact_linked');

        Http::assertNothingSent();
        Bus::assertChained([
            fn (SendTelegramRequest $job) => str_contains($job->params['text'], 'tasdiqlandi')
                && $job->params['reply_markup'] === json_encode(['remove_keyboard' => true]),
            fn (SendTelegramRequest $job) => str_contains($job->params['text'], 'Buyurtma berishni boshlash')
                && $job->params['parse_mode'] === 'Markdown',
        ]);
    }

    public function test_report_export_is_built_and_sent_by_a_worker(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendOrderReport::class]);

        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'weekly'])
            ->assertOk()
            ->assertJsonPath('message', 'Hisobot Telegramingizga muvaffaqiyatli yuborildi! 🚀');

        Http::assertNothingSent();
        Queue::assertPushed(SendOrderReport::class, fn (SendOrderReport $job) => $job->storeId === $this->store->id
            && $job->branchId === $this->branch->id
            && $job->params['chat_id'] === '5001'
            && $job->timeframe === 'weekly'
            && $job->asOf->diffInSeconds(now(), true) < 5);
    }

    public function test_a_report_telegram_refuses_is_logged_by_the_worker_instead_of_failing_the_request(): void
    {
        $this->fakeTelegram(Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400));
        Log::spy();

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'today'])->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendDocument'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Telegram sendDocument failed'
            && str_contains($context['error'], 'chat not found'));
    }

    public function test_store_and_staff_alerts_are_queued_too(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendTelegramRequest::class]);
        $this->store->forceFill(['telegram_admin_chat_id' => '-100777', 'wallet_balance' => 0])->save();
        $invited = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => null, 'telegram_invite_token' => 'emp_invite1']);

        event(new SettlementCompleted($this->store, 150000));
        event(new StoreBalanceLow($this->store));
        app(TelegramNotificationService::class)->sendEmployeeInvite($invited, $this->owner);

        Http::assertNothingSent();
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '-100777'
            && $job->params['parse_mode'] === 'Markdown'
            && str_contains($job->params['text'], 'Moliyaviy ogohlantirish'));
        $this->assertTrue(NotificationLog::where('user_id', $this->owner->id)->where('type', 'store_payment_required')->exists());
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '-100777'
            && str_contains($job->params['text'], '150 000 UZS'));
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '5003'
            && $job->params['parse_mode'] === 'HTML'
            && str_contains($job->params['text'], 'start=invite_emp_invite1'));
    }

    public function test_order_buttons_open_the_configured_mini_app(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendTelegramRequest::class]);
        config(['services.telegram.webapp_url' => 'https://app.rizqim.example/']);
        $this->store->forceFill(['telegram_admin_chat_id' => '-100777'])->save();
        $order = $this->makeOrder(OrderStatus::NEW);

        app(TelegramNotificationService::class)->sendOrderCreated($order);
        app(TelegramNotificationService::class)->sendOrderStatusChanged($order);

        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '-100777'
            && str_contains($job->params['reply_markup'], json_encode('https://app.rizqim.example/manager')));
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '5009'
            && str_contains($job->params['reply_markup'], json_encode('https://app.rizqim.example/')));
    }

    public function test_a_late_courier_warning_is_queued_once_while_it_is_still_being_sent(): void
    {
        $this->fakeTelegram();
        Queue::fake([SendTelegramRequest::class]);
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);
        OrderAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'assigned_by' => $this->manager->id,
            'assigned_branch_id' => $this->branch->id,
            'is_current' => true,
            'assigned_at' => now()->subMinutes(20),
        ]);

        // A minute apart, the first warning still waiting in the queue (so not in the feed yet)
        (new CheckUnacceptedAssignmentsJob)->handle();
        (new CheckUnacceptedAssignmentsJob)->handle();

        Queue::assertPushed(SendTelegramRequest::class, 1);
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params['chat_id'] === '5001'
            && $job->notification === ['user_id' => $this->manager->id, 'order_id' => $order->id, 'type' => 'telegram_manager_courier_delay_warning']);
    }
}
