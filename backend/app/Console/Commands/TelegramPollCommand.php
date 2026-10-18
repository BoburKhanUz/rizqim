<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Telegram Updates locally and forward them to the webhook controller';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Starting Telegram Bot Long Polling listener...");
        
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            $this->error("❌ No Telegram bot token configured in services.telegram.bot_token!");
            return Command::FAILURE;
        }

        $botUsername = config('services.telegram.bot_username', 'rizqimbot');
        $this->info("🤖 Bot Username: " . $botUsername);
        $this->info("🔑 Token: " . substr($botToken, 0, 10) . "..." . substr($botToken, -5));
        
        // Remove active webhook first to allow getUpdates to work
        $this->info("🔌 Removing existing Telegram webhook (if any) to allow polling...");
        try {
            Http::get("https://api.telegram.org/bot{$botToken}/deleteWebhook");
            $this->info("✅ Webhook removed successfully.");
        } catch (\Exception $e) {
            $this->warn("⚠️ Could not delete webhook: " . $e->getMessage());
        }

        $offset = 0;
        $this->info("📥 Listening for callback button clicks and messages. Press Ctrl+C to stop.");

        while (true) {
            try {
                // Fetch updates with a long polling timeout of 10 seconds
                $url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset={$offset}&timeout=10";
                $response = Http::timeout(15)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if ($data['ok'] && !empty($data['result'])) {
                        foreach ($data['result'] as $update) {
                            $updateId = $update['update_id'];
                            $offset = $updateId + 1;

                            $this->info("✨ [" . date('H:i:s') . "] Processing update #{$updateId}...");
                            
                            // Forward the update body directly to our local webhook route
                            // Port 8000 is our exact serving port
                            $webhookUrl = "http://127.0.0.1:8000/api/v1/telegram/webhook";
                            
                            $webhookResponse = Http::post($webhookUrl, $update);
                            
                            if ($webhookResponse->successful()) {
                                $this->info("✅ Update #{$updateId} processed successfully: " . $webhookResponse->body());
                            } else {
                                $this->error("❌ Webhook returned error for update #{$updateId}: " . $webhookResponse->status() . " " . $webhookResponse->body());
                            }
                        }
                    }
                } else {
                    $this->error("❌ Telegram API returned error: " . $response->status() . " " . $response->body());
                }
            } catch (\Exception $e) {
                $this->error("💥 Error during polling loop: " . $e->getMessage());
            }

            // Sleep for a short duration to prevent API rate limiting
            sleep(1);
        }
    }
}
