<?php

namespace App\Services\Auth;

use Illuminate\Http\JsonResponse;

/**
 * P4-31: an account the RIZQIM support team blocked (users.blocked_at, Boshqaruv markazi → Mijozlar va yordam) asked
 * for a token: a Telegram / SMS sign-in, a refresh, a staff PIN session. No token is issued; the answer is 403
 * `account_blocked`. Thrown where tokens are minted (GenerateAuthTokenAction, TokenService::createDeviceToken); tokens
 * issued before the block are refused by the Sanctum check in AccessServiceProvider.
 */
class AccountBlockedException extends \RuntimeException
{
    public const REASON = 'account_blocked';

    public function __construct()
    {
        parent::__construct("Hisobingiz bloklangan. Savollar bo'lsa, RIZQIM yordam xizmatiga murojaat qiling.");
    }

    /**
     * A refusal, not a server error: nothing is reported.
     */
    public function report(): void {}

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'data' => ['reason' => self::REASON],
        ], 403);
    }

    /**
     * @throws self
     */
    public static function guard(mixed $tokenable): void
    {
        if ($tokenable instanceof \App\Models\User && $tokenable->blocked_at !== null) {
            throw new self();
        }
    }
}
