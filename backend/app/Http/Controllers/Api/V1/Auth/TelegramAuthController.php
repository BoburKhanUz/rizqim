<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Store;
use App\Actions\Auth\ValidateTelegramInitDataAction;
use App\Actions\Auth\AuthenticateTelegramUserAction;
use App\Actions\Auth\GenerateAuthTokenAction;

class TelegramAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ValidateTelegramInitDataAction $validateAction,
        protected AuthenticateTelegramUserAction $authenticateAction,
        protected GenerateAuthTokenAction $generateTokenAction
    ) {}

    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'initData' => 'required|string',
            'bot_username' => 'required|string',
            'start_param' => 'nullable|string',
        ]);

        $globalBotUsername = config('services.telegram.bot_username');
        $isGlobalBot = $request->bot_username === $globalBotUsername;

        // 1. Determine Bot Token
        $botToken = null;
        $store = null;
        $botStore = null;

        if ($isGlobalBot) {
            $botToken = config('services.telegram.bot_token');
            if ($request->start_param && str_starts_with($request->start_param, 'store_')) {
                $uuid = str_replace('store_', '', $request->start_param);
                $store = Store::where('uuid', $uuid)->where('is_active', true)->first();
            }
        } else {
            $store = Store::where('telegram_bot_username', $request->bot_username)
                ->where('is_active', true)
                ->first();
            $botToken = $store->telegram_bot_token ?? config('services.telegram.bot_token');

            // The store owner holds this token and could sign initData for any Telegram id
            if ($store && filled($store->telegram_bot_token) && $store->telegram_bot_token !== config('services.telegram.bot_token')) {
                $botStore = $store;
            }
        }

        if (empty($botToken)) {
            return $this->error('Bot token is not configured.', [], 404);
        }

        // 2. Validate InitData first
        $telegramUser = $this->validateAction->execute(
            $request->initData, 
            $botToken
        );

        if (!$telegramUser) {
            return $this->error('Invalid Telegram authentication data.', [], 401);
        }

        // 3. Fallback to user's existing store if store is still null
        if (!$store) {
            $existingUser = \App\Models\User::where('telegram_id', $telegramUser['id'])->first();
            if ($existingUser && $existingUser->store_id) {
                $store = Store::where('id', $existingUser->store_id)->where('is_active', true)->first();
            } else {
                // If no user or user has no store, fallback to first active store (useful for single-tenant mode via global bot)
                $store = Store::where('is_active', true)->first();
            }
        }

        if (!$store) {
            return $this->error('Store not found or bot not configured.', [], 404);
        }

        // 3. Find or Create User
        $user = $this->authenticateAction->execute($telegramUser, $store, $request->start_param, $botStore);

        if (!$user) {
            return $this->error("Bu bot orqali tizimga kirib bo'lmaydi. Iltimos, asosiy bot orqali kiring.", [], 403);
        }

        // 4. Generate Token
        $token = $this->generateTokenAction->execute($user);
        
        // 5. Resolve Actual Store (if user is attached to a different store e.g. via invite link)
        $resolvedStore = $user->store_id ? Store::with('branches')->find($user->store_id) : $store->load('branches');

        // 6. Return Response
        return $this->success([
            'token' => $token,
            'user' => $user,
            'store' => $resolvedStore,
            'sms_verification_required' => setting('sms_driver', 'log') === 'eskiz',
        ], 'Authenticated successfully.');
    }
}
