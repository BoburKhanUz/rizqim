<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\Store;
use App\Actions\Auth\ValidateTelegramInitDataAction;
use App\Actions\Auth\AuthenticateTelegramUserAction;
use App\Actions\Auth\GenerateAuthTokenAction;
use App\Services\Auth\PlatformAccountSignInException;

#[Group('Auth', "Kirish: Telegram, SMS kod, refresh token, chiqish, ruxsatlar (docs/AUTH.md).")]
class TelegramAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ValidateTelegramInitDataAction $validateAction,
        protected AuthenticateTelegramUserAction $authenticateAction,
        protected GenerateAuthTokenAction $generateTokenAction,
        protected \App\Services\Auth\DeviceService $devices,
        protected \App\Services\Auth\TokenService $tokens,
    ) {}

    #[ApiDocResponse(403, 'Superadmin yoki RIZQIM jamoasi hisobi: Telegram orqali kirish yopiq (`reason: platform_account`), yoki bu bot orqali kirib bo\'lmaydi', type: 'array{success: false, message: string, data: array{reason?: string}}')]
    #[ApiDocResponse(200, description: '`device` yuborilganda `token` qisqa muddatli bo\'ladi va refresh maydonlari qo\'shiladi.', type: 'array{success: true, message: string, data: array{token: string, user: User, store: Store|null, sms_verification_required: bool, token_expires_at?: string, refresh_token?: string, refresh_token_expires_at?: string, device?: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}}}')]
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'initData' => 'required|string',
            'bot_username' => 'required|string',
            'start_param' => 'nullable|string',
        ] + DeviceRegistrationRules::rules()); // optional `device`: opt-in refresh tokens (the Mini App sends none)

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

        // 3. Store bots keep their legacy single-tenant fallback; the global marketplace bot needs no store: a customer
        // is global (users.store_id stays null) and the Mini App lists the stores itself. Staff get their own store below
        if (!$store && !$isGlobalBot) {
            $store = Store::where('is_active', true)->first();

            // A store bot (or an unknown bot username) without an active store; an inactive or unknown `store_<uuid>`
            // on the global bot just signs in without a store, so the answer does not tell whether that store exists
            if (!$store) {
                return $this->error('Store not found or bot not configured.', [], 404);
            }
        }

        // 4. Find or Create User
        try {
            $user = $this->authenticateAction->execute($telegramUser, $store, $request->start_param, $botStore);
        } catch (PlatformAccountSignInException $e) {
            // P4-25: superadmin and platform accounts sign in through the control center only
            return $this->error($e->getMessage(), ['reason' => PlatformAccountSignInException::REASON], 403);
        }

        if (!$user) {
            return $this->error("Bu bot orqali tizimga kirib bo'lmaydi. Iltimos, asosiy bot orqali kiring.", [], 403);
        }

        // Staff of a deactivated store stay refused (404) on either bot, decided on the account this sign-in returns
        // (not on another row of the same Telegram id, and whichever store link or bot it came through)
        if ($user->store_id !== null && !Store::where('id', $user->store_id)->where('is_active', true)->exists()) {
            return $this->error('Store not found or bot not configured.', [], 404);
        }

        // 5. Generate Token: a client that registers its device gets a short-lived access token and a refresh token
        $deviceTokens = $request->filled('device')
            ? $this->tokens->issuePair($user, $this->devices->register($user, $request->input('device')))
            : null;
        $token = $deviceTokens !== null ? $deviceTokens->accessToken : $this->generateTokenAction->execute($user);

        // 6. Resolve Actual Store (if user is attached to a different store e.g. via invite link)
        // A customer on the global bot without an active `store_<uuid>` link gets `store: null`
        $resolvedStore = $user->store_id ? Store::with('branches')->find($user->store_id) : $store?->load('branches');

        // 7. Return Response
        return $this->success(array_merge([
            'token' => $token,
            'user' => $user,
            'store' => $resolvedStore,
            'sms_verification_required' => setting('sms_driver', 'log') === 'eskiz',
        ], $deviceTokens?->toArray() ?? []), 'Authenticated successfully.');
    }
}
