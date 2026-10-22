<?php

namespace App\Domain\Pos\Accounts;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\TelegramContent;
use App\Services\Push\PushContent;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alerts to the store's owner about money decisions made at the till (research report R16, R18, R20; POS_SPEC 2.10):
 * a new credit (nasiya) above the threshold, a repayment, a deposit refunded or retained, a void after sending, a
 * waived charge, a cancelled order, a room open too long. Sent to the store's admin chat and to every owner through
 * NotificationRouter as NotificationType::POS_ALERTS (Telegram and push, as each owner set it in
 * /me/notification-preferences), as plain text (names typed by staff never break Markdown), only after the change
 * commits. A notification problem never fails the action.
 */
class PosOwnerAlerts
{
    public function __construct(
        protected TelegramMessenger $telegram,
        protected NotificationRouter $router,
    ) {}

    public function send(int $storeId, string $text): void
    {
        DB::afterCommit(function () use ($storeId, $text) {
            try {
                $store = Store::find($storeId);
                $chats = [];

                // The store's admin chat is a group, not a person: it has no preferences of its own
                if ($store?->telegram_admin_chat_id && $this->telegram->isConfigured()) {
                    $chats[] = (string) $store->telegram_admin_chat_id;
                    $this->telegram->sendMessage((string) $store->telegram_admin_chat_id, $text);
                }

                $message = new NotificationMessage(
                    NotificationType::POS_ALERTS,
                    telegram: new TelegramContent($text),
                    push: new PushContent('Kassa ogohlantirishi', mb_substr($text, 0, 240), ['type' => NotificationType::POS_ALERTS->value], 'pos-alert'),
                );

                $owners = User::where('store_id', $storeId)->where('role', UserRole::OWNER->value)->orderBy('id')->get();
                foreach ($owners as $owner) {
                    // An owner who is also the admin chat (a private chat with the bot) hears it once
                    if (in_array((string) $owner->telegram_id, $chats, true)) {
                        continue;
                    }
                    $this->router->send($owner, $message);
                }
            } catch (Throwable $e) {
                Log::error("Failed to send the POS owner alert of store {$storeId}: {$e->getMessage()}");
            }
        });
    }

    public static function sum(Money|string $amount): string
    {
        return number_format(Money::of($amount)->roundTo(0)->toFloat(), 0, '.', ' ') . " so'm";
    }
}
