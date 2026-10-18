<?php

namespace App\Listeners;

use App\Events\StoreBalanceLow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class SendOwnerFinanceAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StoreBalanceLow $event): void
    {
        $store = $event->store;
        $botToken = config('services.telegram.bot_token');
        if (!$botToken || !$store->telegram_admin_chat_id) return;

        $msg = "⚠️ *Diqqat! Moliyaviy ogohlantirish*\n\n";
        $msg .= "Hurmatli hamkor, sizning qarzingiz belgilangan limitdan oshib ketdi:\n";
        $msg .= "Joriy balans: *" . number_format($store->balance, 0, '.', ' ') . " UZS*\n\n";
        $msg .= "Faoliyatingiz to'xtatilmasligi uchun zudlik bilan hisobingizni to'ldirishingizni so'raymiz.";

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $store->telegram_admin_chat_id,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);

        // Create NotificationLog in app for the owner
        if ($owner = \App\Models\User::where('store_id', $store->id)->where('role', 'owner')->first()) {
            \App\Models\NotificationLog::create([
                'user_id' => $owner->id,
                'type' => 'store_payment_required',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }
}
