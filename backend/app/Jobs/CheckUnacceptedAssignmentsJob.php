<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderAssignment;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\Cache;

class CheckUnacceptedAssignmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $telegram = app(TelegramMessenger::class);

        // Find active courier assignments older than 15 minutes where the order is still in 'courier_assigned' status
        $lateAssignments = OrderAssignment::with(['order.store', 'courier'])
            ->where('is_current', true)
            ->where('assigned_at', '<=', now()->subMinutes(15))
            ->whereHas('order', function ($query) {
                $query->where('status', 'courier_assigned');
            })
            ->get();

        foreach ($lateAssignments as $assignment) {
            $order = $assignment->order;
            $courier = $assignment->courier;
            $store = $order->store;

            if (!$telegram->isConfigured()) {
                continue;
            }

            // Find branch managers (never a blocked one: NotificationRouter::withoutBlocked)
            $managers = app(\App\Services\Notifications\NotificationRouter::class)->withoutBlocked(User::where('store_id', $store->id)
                ->where('role', \App\Enums\UserRole::MANAGER->value)
                ->where('branch_id', $order->branch_id))
                ->get();

            $text = "⚠️ *KURYER KECHIKMOQDA!*\n\n"
                  . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                  . "👤 *Kuryer:* {$courier->name}\n"
                  . "⏳ *Biriktirilgan vaqt:* " . $assignment->assigned_at->format('H:i (d-M)') . "\n\n"
                  . "Kuryer buyurtmani 15 daqiqa ichida qabul qilib yo'lga chiqmadi. Iltimos, tekshiring! 🛵";

            foreach ($managers as $manager) {
                // The job runs every minute: warn each manager once per assignment, not every minute until the courier leaves
                $alreadyWarned = \App\Models\NotificationLog::where('order_id', $order->id)
                    ->where('user_id', $manager->id)
                    ->where('type', 'telegram_manager_courier_delay_warning')
                    ->where('status', 'sent')
                    ->where('sent_at', '>=', $assignment->assigned_at)
                    ->exists();

                // The log appears only once the queued send is done (retries included): until then queue no other
                if ($manager->telegram_id && !$alreadyWarned
                    && Cache::add("courier-delay-warning:{$assignment->id}:{$manager->id}", true, now()->addMinutes(15))) {
                    $telegram->sendMessage($manager->telegram_id, $text, ['parse_mode' => 'Markdown'], [
                        'user_id' => $manager->id,
                        'order_id' => $order->id,
                        'type' => 'telegram_manager_courier_delay_warning',
                    ]);
                }
            }
        }
    }
}
