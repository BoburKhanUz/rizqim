<?php

namespace App\Actions\Auth;

use App\Models\User;

class GenerateAuthTokenAction
{
    public const MAX_ACTIVE_TOKENS = 5;

    public function execute(User $user, string $tokenName = 'mobile-api'): string
    {
        // P4-31: a blocked account signs in nowhere
        \App\Services\Auth\AccountBlockedException::guard($user);

        $expiration = (int) config('sanctum.expiration');

        if ($expiration > 0) {
            $user->tokens()
                ->where(function ($query) use ($expiration) {
                    $query->where('expires_at', '<=', now())
                        ->orWhere('created_at', '<=', now()->subMinutes($expiration));
                })
                ->delete();
        }

        $plainTextToken = $user->createToken(
            $tokenName,
            ['*'],
            $expiration > 0 ? now()->addMinutes($expiration) : null
        )->plainTextToken;

        // Keep only the most recent sessions (one per device is typical for the Mini App). Device-bound tokens (staff
        // PIN sessions, the apps' refreshable sessions) are managed per device by TokenService and left alone
        $keepIds = $user->tokens()->whereNull('device_id')->orderByDesc('id')->limit(self::MAX_ACTIVE_TOKENS)->pluck('id');
        $user->tokens()->whereNull('device_id')->whereNotIn('id', $keepIds)->delete();

        return $plainTextToken;
    }
}
