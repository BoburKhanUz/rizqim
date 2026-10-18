<?php

namespace App\Listeners;

use App\Events\SettlementCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class SendSettlementCompletedAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SettlementCompleted $event): void
    {
        $store = $event->store;
        $botToken = config('services.telegram.bot_token');
        if (!$botToken || !$store->telegram_admin_chat_id) return;

        $msg = "💰 *Pulingiz o'tkazib berildi!*\n\n";
        $msg .= "Platforma hisobidan *" . number_format($event->amount, 0, '.', ' ') . " UZS* sizning hisobingizga muvaffaqiyatli o'tkazildi (Payout).\n";
        $msg .= "Yangi qoldiq (Bizning qarzimiz): *" . number_format($store->wallet_balance, 0, '.', ' ') . " UZS*";

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $store->telegram_admin_chat_id,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }
}
