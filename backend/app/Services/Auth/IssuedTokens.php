<?php

namespace App\Services\Auth;

use App\Models\Device;
use Illuminate\Support\Carbon;

/**
 * An access token and the refresh token that renews it, for one device.
 */
final class IssuedTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly Carbon $accessExpiresAt,
        public readonly string $refreshToken,
        public readonly Carbon $refreshExpiresAt,
        public readonly Device $device,
    ) {}

    /**
     * The fields added to a login response when the client registered a device (and the refresh response).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->accessToken,
            'token_expires_at' => $this->accessExpiresAt->toIso8601String(),
            'refresh_token' => $this->refreshToken,
            'refresh_token_expires_at' => $this->refreshExpiresAt->toIso8601String(),
            'device' => $this->device->toPublicArray(),
        ];
    }
}
