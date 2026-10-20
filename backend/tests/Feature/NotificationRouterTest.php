<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Jobs\SendTelegramRequest;
use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\TelegramContent;
use App\Services\Push\PushContent;
use App\Services\Push\PushSender;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NotificationRouterTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPush();
        $this->setUpLifecycleStore();
        config(['services.telegram.bot_token' => '123456:router-token']);
        Bus::fake();
    }

    protected function message(NotificationType $type = NotificationType::ORDER_UPDATES, bool $telegram = true, bool $push = true, array $extra = []): NotificationMessage
    {
        return new NotificationMessage(...[
            'type' => $type,
            'telegram' => $telegram ? new TelegramContent('Salom', ['parse_mode' => 'Markdown'], ['user_id' => $this->customer->id, 'order_id' => 1, 'type' => 'telegram_customer_accepted']) : null,
            'push' => $push ? new PushContent('Salom', 'Matn', ['order_uuid' => 'u'], 'order-u') : null,
        ] + $extra);
    }

    protected function off(NotificationType $type, NotificationChannel $channel, $user = null): void
    {
        NotificationPreference::create(['user_id' => ($user ?? $this->customer)->id, 'type' => $type->value, 'channel' => $channel->value, 'enabled' => false]);
    }

    public function test_a_message_fans_out_to_telegram_and_to_every_push_device_of_the_person(): void
    {
        $phone = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'phone-token-12345');
        $tablet = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'tablet-token-1234');
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, null);
        $revoked = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'revoked-token-123');
        $revoked->forceFill(['revoked_at' => now()])->save();
        $this->makeDevice($this->makeCustomer(), DeviceType::CUSTOMER_MOBILE, 'stranger-token-123');
        $this->customer->update(['telegram_id' => '5009']);

        $used = app(NotificationRouter::class)->send($this->customer, $this->message());

        $this->assertSame([NotificationChannel::TELEGRAM, NotificationChannel::PUSH], $used);
        Bus::assertDispatched(SendTelegramRequest::class, fn ($job) => $job->params['chat_id'] === '5009' && $job->params['text'] === 'Salom');
        Bus::assertDispatchedTimes(SendPushNotification::class, 2);
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->deviceId === $phone->id && $job->type === 'order_updates');
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->deviceId === $tablet->id);
    }

    public function test_the_default_is_everything_on_for_order_updates_and_off_for_promotions(): void
    {
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE);
        $this->customer->update(['telegram_id' => '5009']);
        $router = app(NotificationRouter::class);

        $this->assertCount(2, $router->send($this->customer, $this->message(NotificationType::ORDER_UPDATES)));
        $this->assertSame([], $router->send($this->customer, $this->message(NotificationType::PROMOTIONS)));

        NotificationPreference::create(['user_id' => $this->customer->id, 'type' => 'promotions', 'channel' => 'push', 'enabled' => true]);
        $this->assertSame([NotificationChannel::PUSH], $router->send($this->customer, $this->message(NotificationType::PROMOTIONS)));
    }

    public function test_a_channel_the_person_switched_off_is_skipped_and_the_other_still_works(): void
    {
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE);
        $this->customer->update(['telegram_id' => '5009']);
        $router = app(NotificationRouter::class);

        $this->off(NotificationType::ORDER_UPDATES, NotificationChannel::TELEGRAM);
        $this->assertSame([NotificationChannel::PUSH], $router->send($this->customer, $this->message()));
        Bus::assertNotDispatched(SendTelegramRequest::class);

        $this->off(NotificationType::ORDER_UPDATES, NotificationChannel::PUSH);
        $this->assertSame([], $router->send($this->customer, $this->message()));
    }

    public function test_a_channel_without_content_or_an_address_is_not_used(): void
    {
        $router = app(NotificationRouter::class);

        // No Telegram account, no app
        $this->assertSame([], $router->send($this->customer, $this->message()));

        $this->customer->update(['telegram_id' => '5009']);
        $this->assertSame([NotificationChannel::TELEGRAM], $router->send($this->customer, $this->message()));
        $this->assertSame([], $router->send($this->customer, $this->message(telegram: false)), 'push-only message, no app');

        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE);
        $this->assertSame([NotificationChannel::PUSH], $router->send($this->customer, $this->message(telegram: false)));
        $this->assertSame([NotificationChannel::TELEGRAM], $router->send($this->customer, $this->message(push: false)));
    }

    public function test_staff_alerts_go_to_staff_apps_not_to_a_customer_app(): void
    {
        $partner = $this->makeDevice($this->manager, DeviceType::PARTNER_MOBILE, 'partner-token-1234');
        $this->makeDevice($this->manager, DeviceType::CUSTOMER_MOBILE, 'personal-token-1234');

        app(NotificationRouter::class)->send($this->manager, $this->message(NotificationType::STAFF_NEW_ORDER, telegram: false));

        Bus::assertDispatchedTimes(SendPushNotification::class, 1);
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->deviceId === $partner->id);
    }

    public function test_without_firebase_credentials_no_push_is_queued_and_telegram_still_goes(): void
    {
        $this->setUpPush(configured: false);
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE);
        $this->customer->update(['telegram_id' => '5009']);

        $used = app(NotificationRouter::class)->send($this->customer, $this->message());

        $this->assertSame([NotificationChannel::TELEGRAM], $used);
        Bus::assertNotDispatched(SendPushNotification::class);
    }

    public function test_sms_is_only_the_last_resort_of_a_critical_message(): void
    {
        $sms = new class implements SmsServiceInterface {
            public array $sent = [];

            public function send(string $phone, string $message): bool
            {
                $this->sent[] = [$phone, $message];

                return true;
            }
        };
        $this->app->instance(SmsServiceInterface::class, $sms);
        $router = app(NotificationRouter::class);

        // Not critical: never
        $this->assertSame([], $router->send($this->customer, $this->message(extra: ['sms' => 'Matn'])));
        // Critical but delivered another way
        $this->customer->update(['telegram_id' => '5009']);
        $router->send($this->customer, $this->message(extra: ['sms' => 'Matn', 'critical' => true]));
        $this->assertSame([], $sms->sent);

        // Critical and nothing else works
        $this->customer->update(['telegram_id' => null]);
        $used = $router->send($this->customer, $this->message(extra: ['sms' => 'Muhim xabar', 'critical' => true]));
        $this->assertSame([NotificationChannel::SMS], $used);
        $this->assertSame([[$this->customer->phone, 'Muhim xabar']], $sms->sent);
    }

    public function test_a_failing_channel_never_throws_and_does_not_stop_the_others(): void
    {
        $this->customer->update(['telegram_id' => '5009']);
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE);

        // Push blows up while the router asks whether it is on; Telegram, which comes first, still went
        $broken = new class(app(\App\Services\Telegram\TelegramMessenger::class), app(\App\Services\Notifications\NotificationPreferenceService::class), app(SmsServiceInterface::class)) extends NotificationRouter {
            public function __construct($telegram, $preferences, $sms)
            {
                parent::__construct($telegram, new class(new \App\Services\Push\FcmClient()) extends PushSender {
                    public function isConfigured(): bool
                    {
                        throw new \RuntimeException('push is broken');
                    }
                }, $preferences, $sms);
            }
        };

        $used = $broken->send($this->customer, $this->message());

        $this->assertSame([NotificationChannel::TELEGRAM], $used);
    }
}
