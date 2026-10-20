<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\DeviceType;
use Illuminate\Validation\Rule;

/**
 * The optional `device` object a login (Telegram, phone) may carry. Sending it opts the client into device-bound
 * sessions: a short-lived access token plus a rotating refresh token. Without it the login answers exactly as before.
 */
final class DeviceRegistrationRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'device.'): array
    {
        $required = $prefix === '' ? 'required' : 'required_with:device';

        return array_merge($prefix === '' ? [] : ['device' => 'sometimes|array'], [
            "{$prefix}type" => [$required, 'string', Rule::in(DeviceType::personalValues())],
            "{$prefix}uuid" => 'nullable|uuid',
            "{$prefix}name" => 'nullable|string|max:100',
            "{$prefix}platform" => 'nullable|string|max:32',
            "{$prefix}app_version" => 'nullable|string|max:32',
            "{$prefix}push_token" => 'nullable|string|max:4096',
        ]);
    }
}
