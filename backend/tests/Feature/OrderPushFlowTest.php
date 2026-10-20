<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Services\Notifications\OrderAlertService;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Push\FcmClient;
use App\Services\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Pushes next to the existing Telegram messages, at the same moments, with Firebase faked end to end.
 */
class OrderPushFlowTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPush();
        $this->setUpLifecycleStore();
        config(['services.telegram.bot_token' => '123456:flow-token']);

        $this->customer->update(['telegram_id' => '5009']);
        $this->manager->update(['telegram_id' => '5001']);
        $this->courier->update(['telegram_id' => '5002']);
    }

    /**
     * @return list<string> chat ids that got a Telegram message so far
     */
    protected function telegramChats(): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage'))
            ->map(fn ($pair) => (string) $pair[0]['chat_id'])->values()->all();
    }

    protected function off(NotificationType $type, NotificationChannel $channel, $user): void
    {
        NotificationPreference::create(['user_id' => $user->id, 'type' => $type->value, 'channel' => $channel->value, 'enabled' => false]);
    }

    public function test_a_status_change_pushes_to_the_customers_app_in_addition_to_telegram(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertContains('5009', $this->telegramChats(), 'Telegram is unchanged');

        $this->assertSame(['customer-app-token-1'], $this->fcmTokens());
        $message = $this->fcmRequests()->first()['message'];
        $this->assertSame('Buyurtma tasdiqlandi', $message['notification']['title']);
        $this->assertSame(['type' => 'order_updates', 'order_uuid' => $order->uuid, 'status' => 'accepted'], $message['data']);
        $this->assertSame("order-{$order->uuid}", $message['android']['collapse_key']);
        $this->assertSame("order-{$order->uuid}", $message['apns']['headers']['apns-collapse-id']);
    }

    public function test_every_stage_of_the_order_reaches_the_app_once(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->setStatus($order, $this->manager, 'preparing')->assertOk();
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertOk();
        $this->setStatus($order, $this->courier, 'on_the_way')->assertOk();
        $this->actingAs($this->courier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/complete")->assertOk();

        $customerStatuses = $this->fcmRequests()
            ->filter(fn ($request) => $request['message']['token'] === 'customer-app-token-1')
            ->map(fn ($request) => $request['message']['data']['status'])->values()->all();

        $this->assertSame(['accepted', 'preparing', 'courier_assigned', 'on_the_way', 'delivered'], $customerStatuses);
    }

    public function test_a_cancellation_pushes_too(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'rejected')->assertOk();

        $message = $this->fcmRequests()->first()['message'];
        $this->assertSame('Buyurtma bekor qilindi', $message['notification']['title']);
        $this->assertSame('rejected', $message['data']['status']);
    }

    public function test_a_customer_without_telegram_still_gets_the_push(): void
    {
        $this->fakeFirebase();
        $this->customer->update(['telegram_id' => null]);
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertSame(['customer-app-token-1'], $this->fcmTokens());
        $this->assertNotContains('5009', $this->telegramChats());
    }

    public function test_the_push_works_while_the_telegram_bot_is_off(): void
    {
        $this->fakeFirebase();
        config(['services.telegram.bot_token' => null]);
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertSame(['customer-app-token-1'], $this->fcmTokens());
        $this->assertSame([], $this->telegramChats());
    }

    public function test_switching_telegram_off_for_order_updates_leaves_only_the_push(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $this->off(NotificationType::ORDER_UPDATES, NotificationChannel::TELEGRAM, $this->customer);
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertNotContains('5009', $this->telegramChats());
        $this->assertSame(['customer-app-token-1'], $this->fcmTokens());
    }

    public function test_the_in_app_feed_still_gets_the_entry_when_telegram_is_switched_off(): void
    {
        $this->fakeFirebase();
        $this->off(NotificationType::ORDER_UPDATES, NotificationChannel::TELEGRAM, $this->customer);
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertSame(
            ['telegram_customer_accepted'],
            \App\Models\NotificationLog::where('user_id', $this->customer->id)->where('order_id', $order->id)->pluck('type')->all()
        );
        $this->assertNotContains('5009', $this->telegramChats());
    }

    public function test_switching_push_off_for_order_updates_leaves_only_telegram(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $this->off(NotificationType::ORDER_UPDATES, NotificationChannel::PUSH, $this->customer);
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertContains('5009', $this->telegramChats());
        $this->assertSame([], $this->fcmTokens());
    }

    public function test_without_firebase_credentials_orders_work_and_nothing_is_sent_to_fcm(): void
    {
        $this->setUpPush(configured: false);
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertContains('5009', $this->telegramChats());
        $this->assertCount(0, $this->fcmRequests());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com'));
    }

    public function test_firebase_failing_never_breaks_the_status_change(): void
    {
        Log::spy();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $outages = [
            'fcm down' => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'down']], 503),
            'fcm refuses' => $this->fcmError(403, 'PERMISSION_DENIED', 'no access'),
            'network' => fn () => throw new ConnectionException('cannot connect'),
        ];

        foreach (['accepted' => 'fcm down', 'preparing' => 'fcm refuses'] as $status => $outage) {
            Http::fake([
                'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
                'fcm.googleapis.com/*' => $outages[$outage],
                '*' => Http::response(['ok' => true, 'result' => true]),
            ]);
            \Illuminate\Support\Facades\Cache::flush();

            $this->setStatus($order, $this->manager, $status)->assertOk();
            $this->assertSame($status, $order->fresh()->status->value, $outage);
        }

        Http::fake([
            'oauth2.googleapis.com/*' => fn () => throw new ConnectionException('cannot connect'),
            '*' => Http::response(['ok' => true, 'result' => true]),
        ]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->setStatus($order, $this->manager, 'canceled')->assertOk();
        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Push notification failed')->atLeast()->once();
    }

    public function test_a_push_sender_that_blows_up_does_not_break_the_status_change(): void
    {
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $this->fakeFirebase();
        $this->app->bind(PushSender::class, fn () => new class(new FcmClient()) extends PushSender {
            public function isConfigured(): bool
            {
                throw new \RuntimeException('push exploded');
            }
        });
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->checkout($this->customer, type: 'delivery');

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
        $this->assertContains('5009', $this->telegramChats(), 'Telegram still went');
    }

    public function test_a_dead_token_is_cleared_when_the_customer_is_notified(): void
    {
        $this->fakeFirebase($this->fcmError(404, 'UNREGISTERED'));
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'uninstalled-token-1');
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertNull($device->fresh()->push_token);
    }

    public function test_a_new_order_pushes_to_the_managers_who_may_act_on_it_and_only_those(): void
    {
        $this->fakeFirebase();
        $branchApp = $this->makeDevice($this->manager, DeviceType::PARTNER_MOBILE, 'branch-manager-token');
        $otherBranchManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->makeDevice($otherBranchManager, DeviceType::PARTNER_MOBILE, 'other-branch-token1');
        $storeWide = $this->makeStaff(UserRole::MANAGER);
        $this->makeDevice($storeWide, DeviceType::PARTNER_MOBILE, 'store-wide-token123');
        $this->makeDevice($this->owner, DeviceType::PARTNER_MOBILE, 'owner-token-12345678');
        // Another store's manager
        $outsider = $this->makeStaff(UserRole::MANAGER, null, $this->makeStore('Other Store'));
        $this->makeDevice($outsider, DeviceType::PARTNER_MOBILE, 'outsider-token-12345');

        $order = $this->checkout($this->customer, type: 'delivery');

        $this->assertEqualsCanonicalizing(['branch-manager-token', 'store-wide-token123'], $this->fcmTokens());
        $message = $this->fcmRequests()->first()['message'];
        $this->assertSame('Yangi buyurtma', $message['notification']['title']);
        $this->assertSame($order->uuid, $message['data']['order_uuid']);
        $this->assertSame('staff_new_order', $message['data']['type']);
        $this->assertNotNull($branchApp->fresh()->push_token);
    }

    public function test_a_store_without_managers_pushes_to_the_owner(): void
    {
        $this->fakeFirebase();
        $this->manager->delete();
        $this->makeDevice($this->owner, DeviceType::PARTNER_MOBILE, 'owner-token-12345678');

        $this->checkout($this->customer, type: 'delivery');

        $this->assertSame(['owner-token-12345678'], $this->fcmTokens());
    }

    public function test_staff_can_turn_the_new_order_push_off_and_keep_telegram_or_the_other_way_round(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->manager, DeviceType::PARTNER_MOBILE, 'branch-manager-token');
        $this->off(NotificationType::STAFF_NEW_ORDER, NotificationChannel::PUSH, $this->manager);

        $order = $this->checkout($this->customer, type: 'delivery');
        $this->assertSame([], $this->fcmTokens());
        $this->assertContains('5001', app(TelegramNotificationService::class)->orderCardChatIds($order));

        NotificationPreference::query()->delete();
        $this->off(NotificationType::STAFF_NEW_ORDER, NotificationChannel::TELEGRAM, $this->manager);
        $order = $this->checkout($this->customer, type: 'delivery');
        $this->assertSame(['branch-manager-token'], $this->fcmTokens());
        $this->assertNotContains('5001', app(TelegramNotificationService::class)->orderCardChatIds($order));
    }

    public function test_a_courier_hears_about_an_assignment_and_a_ready_order_on_the_courier_app(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->courier, DeviceType::COURIER, 'courier-app-token-12');
        $order = $this->makeOrder(OrderStatus::PREPARING);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertOk();

        $request = $this->fcmRequests()->first();
        $this->assertSame('courier-app-token-12', $request['message']['token']);
        $this->assertSame('Buyurtma sizga biriktirildi', $request['message']['notification']['title']);
        $this->assertSame('courier_tasks', $request['message']['data']['type']);
        $this->assertContains('5002', $this->telegramChats());

        // Ready, with no courier on the order: every courier of the branch
        $unassigned = $this->makeOrder(OrderStatus::PREPARING);
        $this->setStatus($unassigned, $this->manager, 'ready')->assertOk();

        $ready = $this->fcmRequests()->last();
        $this->assertSame('Buyurtma tayyor', $ready['message']['notification']['title']);
        $this->assertSame($unassigned->uuid, $ready['message']['data']['order_uuid']);
    }

    public function test_a_courier_with_only_the_app_is_still_reached(): void
    {
        $this->fakeFirebase();
        $appOnly = $this->makeStaff(UserRole::COURIER, $this->branch, attributes: ['telegram_id' => null]);
        $this->makeDevice($appOnly, DeviceType::COURIER, 'courier-app-token-12');
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $appOnly->id]);

        app(OrderAlertService::class)->courierAssigned($order, $appOnly);

        $this->assertSame(['courier-app-token-12'], $this->fcmTokens());
        $this->assertSame([], $this->telegramChats());
    }

    public function test_one_customers_settings_do_not_affect_another_customers_order(): void
    {
        $this->fakeFirebase();
        $other = $this->makeCustomer(attributes: ['telegram_id' => '5010']);
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'customer-app-token-1');
        $this->makeDevice($other, DeviceType::CUSTOMER_MOBILE, 'other-customer-token');
        $this->off(NotificationType::ORDER_UPDATES, NotificationChannel::PUSH, $other);

        $order = $this->makeOrder(OrderStatus::NEW);
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $this->assertSame(['customer-app-token-1'], $this->fcmTokens());
        $this->assertInstanceOf(Order::class, $order);
    }
}
