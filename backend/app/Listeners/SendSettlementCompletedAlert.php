<?php

namespace App\Listeners;

use App\Events\SettlementCompleted;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSettlementCompletedAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected TelegramMessenger $telegram
    ) {}

    public function handle(SettlementCompleted $event): void
    {
        $store = $event->store;
        if (!$this->telegram->isConfigured() || !$store->telegram_admin_chat_id) return;

        $msg = "💰 *Pulingiz o'tkazib berildi!*\n\n";
        $msg .= "Platforma hisobidan *" . number_format($event->amount, 0, '.', ' ') . " UZS* sizning hisobingizga muvaffaqiyatli o'tkazildi (Payout).\n";
        $msg .= "Yangi qoldiq (Bizning qarzimiz): *" . number_format((float) $store->wallet_balance, 0, '.', ' ') . " UZS*";

        $this->telegram->sendMessage($store->telegram_admin_chat_id, $msg, ['parse_mode' => 'Markdown']);
    }
}
