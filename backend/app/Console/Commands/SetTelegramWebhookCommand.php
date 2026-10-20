<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook
        {--url= : Public HTTPS webhook URL (default: TELEGRAM_WEBHOOK_URL, else APP_URL + /api/v1/telegram/webhook)}
        {--if-configured : Do nothing (exit 0) when TELEGRAM_BOT_TOKEN is not set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register the bot webhook with Telegram, including the secret_token the webhook route requires';

    // The update types TelegramWebhookController handles
    private const ALLOWED_UPDATES = ['message', 'callback_query'];

    public function handle(): int
    {
        $botToken = (string) config('services.telegram.bot_token');

        if ($botToken === '') {
            if ($this->option('if-configured')) {
                $this->line('TELEGRAM_BOT_TOKEN o\'rnatilmagan — webhook ro\'yxatdan o\'tkazilmadi.');

                return self::SUCCESS;
            }

            $this->error('TELEGRAM_BOT_TOKEN o\'rnatilmagan.');

            return self::FAILURE;
        }

        $secret = (string) config('services.telegram.webhook_secret');

        if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
            $this->error('TELEGRAM_WEBHOOK_SECRET bo\'sh yoki noto\'g\'ri: faqat A-Z, a-z, 0-9, "_" va "-" (1-256 belgi).');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url')
            ?: config('telegram.webhook_url')
            ?: rtrim((string) config('app.url'), '/') . '/api/v1/telegram/webhook');

        if (!str_starts_with($url, 'https://')) {
            $this->error("Webhook URL HTTPS bo'lishi shart (TELEGRAM_WEBHOOK_URL ni o'rnating): {$url}");

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => self::ALLOWED_UPDATES,
            ]);
        } catch (\Throwable $e) {
            // Connection errors embed the request URL, which contains the bot token
            $this->error('Telegram API ga ulanib bo\'lmadi: ' . str_replace($botToken, '***', $e->getMessage()));

            return self::FAILURE;
        }

        if (!$response->successful() || $response->json('ok') !== true) {
            $this->error('setWebhook rad etildi: ' . ($response->json('description') ?? 'HTTP ' . $response->status()));

            return self::FAILURE;
        }

        $this->info("Webhook o'rnatildi: {$url} (allowed_updates: " . implode(', ', self::ALLOWED_UPDATES) . ')');

        return self::SUCCESS;
    }
}
