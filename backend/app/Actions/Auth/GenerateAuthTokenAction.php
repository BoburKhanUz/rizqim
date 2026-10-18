<?php

namespace App\Actions\Auth;

use App\Models\User;

class GenerateAuthTokenAction
{
    public function execute(User $user, string $tokenName = 'mobile-api'): string
    {
        // Delete previous tokens to keep 1 active session per user (Optional, uncomment if needed)
        // $user->tokens()->where('name', $tokenName)->delete();

        return $user->createToken($tokenName)->plainTextToken;
    }
}
