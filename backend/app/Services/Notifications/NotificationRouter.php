<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Jobs\SendPushNotification;
use App\Models\Device;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Push\PushSender;
use App\Services\Sms\SmsServiceInterface;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fan-out of one message to one recipient over the channels the person has switched on (and can be reached on):
 * Telegram (queued SendTelegramRequest), push (a SendPushNotification job per registered device) and, for a critical
 * message nobody else took, SMS. Everything is queued or fire-and-forget and wrapped here, so a notification problem
 * never breaks the order flow that triggered it.
 *
 * Review W3 S#3: a blocked account (users.blocked_at, Mijozlar va yordam) gets nothing from here, on any channel, and
 * is never picked as a recipient: send() drops it, reachable() / onlyReachable() leave it out, and code that picks
 * staff for a message it sends itself (an order card to the managers' chats) narrows its query with withoutBlocked().
 * A store's own Telegram chat (telegram_admin_chat_id) is the store's, not a person's: it is not filtered.
 */
class NotificationRouter
{
    public function __construct(
        protected TelegramMessenger $telegram,
        protected PushSender $push,
        protected NotificationPreferenceService $preferences,
        protected SmsServiceInterface $sms,
    ) {}

    /**
     * @return list<NotificationChannel> the channels the message was handed to
     */
    public function send(User $recipient, NotificationMessage $message): array
    {
        if ($recipient->isBlocked()) {
            return [];
        }

        $used = [];

        foreach ([NotificationChannel::TELEGRAM, NotificationChannel::PUSH] as $channel) {
            try {
                if ($this->enabled($recipient, $message, $channel) && $this->deliver($recipient, $message, $channel)) {
                    $used[] = $channel;
                }
            } catch (Throwable $e) {
                Log::error("Failed to route {$message->type->value} to user {$recipient->id} over {$channel->value}: {$e->getMessage()}");
            }
        }

        $this->keepInTheFeed($recipient, $message);

        // The last resort for something that must not be missed (nothing sends one yet)
        if ($used === [] && $message->critical && $message->sms !== null && filled($recipient->phone)) {
            try {
                $this->sms->send((string) $recipient->phone, $message->sms);
                $used[] = NotificationChannel::SMS;
            } catch (Throwable $e) {
                Log::error("Failed to send the critical SMS to user {$recipient->id}: {$e->getMessage()}");
            }
        }

        return $used;
    }

    /**
     * The in-app notification feed is made of the rows a sent Telegram message leaves behind. Someone who switched
     * Telegram off for this type still has the entry in their feed.
     */
    protected function keepInTheFeed(User $recipient, NotificationMessage $message): void
    {
        $log = $message->telegram?->log;

        if ($log === null || $this->preferences->allows($recipient, $message->type, NotificationChannel::TELEGRAM)) {
            return;
        }

        try {
            // A savepoint when inside a transaction: a refused insert must not poison the caller's transaction
            DB::transaction(fn () => NotificationLog::create($log + ['status' => 'sent', 'sent_at' => now()]));
        } catch (Throwable $e) {
            Log::error("Failed to record the feed entry for user {$recipient->id}: {$e->getMessage()}");
        }
    }

    /**
     * Whether the person has Telegram or a push-ready app, for picking recipients that can be reached at all.
     */
    public function reachable(User $user): bool
    {
        return !$user->isBlocked() && ($user->hasTelegram()
            || Device::where('user_id', $user->id)->active()->whereNotNull('push_token')->exists());
    }

    /**
     * Narrow a users query to those who may be sent a notification at all: not blocked.
     *
     * @template TBuilder of QueryBuilder
     * @param TBuilder $query
     * @return TBuilder
     */
    public function withoutBlocked($query)
    {
        return $query->whereNull('users.blocked_at');
    }

    /**
     * Narrow a users query to those the router can reach (not blocked; Telegram linked, or an app with a push token).
     *
     * @template TBuilder of QueryBuilder
     * @param TBuilder $query
     * @return TBuilder
     */
    public function onlyReachable($query)
    {
        return $this->withoutBlocked($query)->where(fn ($query) => $query
            ->where(fn ($query) => $query->whereNotNull('telegram_id')->where('telegram_id', 'not like', 'temp\\_%'))
            ->orWhereExists(fn ($devices) => $devices->selectRaw('1')->from('devices')
                ->whereColumn('devices.user_id', 'users.id')
                ->whereNull('devices.revoked_at')
                ->whereNotNull('devices.push_token')));
    }

    protected function enabled(User $recipient, NotificationMessage $message, NotificationChannel $channel): bool
    {
        $content = match ($channel) {
            NotificationChannel::TELEGRAM => $message->telegram,
            NotificationChannel::PUSH => $message->push,
            default => null,
        };

        return $content !== null && $this->preferences->allows($recipient, $message->type, $channel);
    }

    protected function deliver(User $recipient, NotificationMessage $message, NotificationChannel $channel): bool
    {
        return match ($channel) {
            NotificationChannel::TELEGRAM => $this->deliverTelegram($recipient, $message),
            NotificationChannel::PUSH => $this->deliverPush($recipient, $message),
            default => false,
        };
    }

    protected function deliverTelegram(User $recipient, NotificationMessage $message): bool
    {
        $content = $message->telegram;

        // An unclaimed invite's 'temp_…' placeholder is no chat (P1-36: app-only staff get the push instead)
        if ($content === null || !$recipient->hasTelegram() || !$this->telegram->isConfigured()) {
            return false;
        }

        $this->telegram->sendMessage((string) $recipient->telegram_id, $content->text, $content->options, $content->log);

        return true;
    }

    protected function deliverPush(User $recipient, NotificationMessage $message): bool
    {
        if ($message->push === null || !$this->push->isConfigured()) {
            return false;
        }

        $deviceIds = Device::where('user_id', $recipient->id)
            ->active()
            ->whereNotNull('push_token')
            ->whereIn('type', array_map(fn ($type) => $type->value, $message->type->deviceTypes()))
            ->pluck('id');

        foreach ($deviceIds as $deviceId) {
            Bus::dispatch(new SendPushNotification((int) $deviceId, $message->push, $message->type->value));
        }

        return $deviceIds->isNotEmpty();
    }
}
