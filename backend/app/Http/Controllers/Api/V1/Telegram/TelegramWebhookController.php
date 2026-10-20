<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use App\Services\Telegram\TelegramCallbackHandler;
use App\Services\Telegram\TelegramMessageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

#[Group('Webhooks')]
class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramMessageHandler $messages,
        protected TelegramCallbackHandler $callbacks
    ) {}

    /**
     * Telegram bot updates
     *
     * Telegram Bot API webhook: messages (/start, shared contacts) and inline-button callbacks. Requires the
     * `X-Telegram-Bot-Api-Secret-Token` header set when the webhook was registered.
     */
    public function handle(Request $request)
    {
        $update = $request->all();

        // Log incoming update for debug
        Log::info('Telegram Webhook Update Received', ['update' => $update]);

        // Handle incoming text messages (e.g. /start) and shared contacts
        if (isset($update['message'])) {
            return response()->json(['status' => $this->messages->handle((array) $update['message'])]);
        }

        // We only care about callback queries
        if (!isset($update['callback_query'])) {
            return response()->json(['status' => 'ignored']);
        }

        return response()->json($this->callbacks->handle((array) $update['callback_query']));
    }
}
