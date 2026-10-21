<?php

namespace App\Domain\Pos\Accounts;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram alerts to the store's owner about money decisions made at the till (research report R16, R18, R20): a new
 * credit (nasiya) above the threshold, a repayment, a deposit refunded or retained. Sent to the store's admin chat and
 * every owner with Telegram, as plain text (names typed by staff never break Markdown), only after the change commits.
 * A Telegram problem never fails the action.
 */
class PosOwnerAlerts
{
    public function __construct(
        protected TelegramMessenger $telegram,
    ) {}

    public function send(int $storeId, string $text): void
    {
        DB::afterCommit(function () use ($storeId, $text) {
            try {
                if (!$this->telegram->isConfigured()) {
                    return;
                }

                $store = Store::find($storeId);
                $chats = $store?->telegram_admin_chat_id ? [(string) $store->telegram_admin_chat_id] : [];
                User::where('store_id', $storeId)
                    ->where('role', UserRole::OWNER->value)
                    ->whereNotNull('telegram_id')
                    ->where('telegram_id', 'not like', 'temp\\_%')
                    ->pluck('telegram_id')
                    ->each(function ($chat) use (&$chats) {
                        $chats[] = (string) $chat;
                    });

                foreach (array_unique($chats) as $chat) {
                    $this->telegram->sendMessage($chat, $text);
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
