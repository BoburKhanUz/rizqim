<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckUnacceptedAssignmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
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

            $botToken = config('services.telegram.bot_token');
            if (!$botToken) {
                continue;
            }

            // Find branch managers
            $managers = User::where('store_id', $store->id)
                ->where('role', \App\Enums\UserRole::MANAGER->value)
                ->where('branch_id', $order->branch_id)
                ->get();

            $text = "⚠️ *KURYER KECHIKMOQDA!*\n\n"
                  . "🔢 *Buyurtma ID:* #{$order->short_code}\n"
                  . "👤 *Kuryer:* {$courier->name}\n"
                  . "⏳ *Biriktirilgan vaqt:* " . $assignment->assigned_at->format('H:i (d-M)') . "\n\n"
                  . "Kuryer buyurtmani 15 daqiqa ichida qabul qilib yo'lga chiqmadi. Iltimos, tekshiring! 🛵";

            foreach ($managers as $manager) {
                if ($manager->telegram_id) {
                    try {
                        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $manager->telegram_id,
                            'text' => $text,
                            'parse_mode' => 'Markdown',
                        ]);

                        \App\Models\NotificationLog::create([
                            'user_id' => $manager->id,
                            'order_id' => $order->id,
                            'type' => 'telegram_manager_courier_delay_warning',
                            'status' => $response->successful() ? 'sent' : 'failed',
                            'sent_at' => now(),
                            'error_message' => $response->successful() ? null : $response->body(),
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to send delay warning to manager {$manager->id}: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
