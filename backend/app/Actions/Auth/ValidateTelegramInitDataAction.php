<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Log;

class ValidateTelegramInitDataAction
{
    /**
     * Executes the Telegram Web App authentication validation.
     * 
     * @param string $initData The raw initData string from Telegram SDK.
     * @param string $botToken The Telegram Bot Token belonging to the Store.
     * @return array|null Returns parsed user array if valid, null otherwise.
     */
    public function execute(string $initData, string $botToken): ?array
    {
        // Developer mock bypass for local testing
        if (config('app.env') === 'local' && str_starts_with($initData, 'mock_')) {
            Log::info('ValidateTelegramInitDataAction: Bypassing validation for local development mock data.');
            parse_str($initData, $parsedData);
            if (isset($parsedData['user'])) {
                return json_decode($parsedData['user'], true);
            }
        }

        // Prevent empty bot token validation bypass
        if (empty($botToken)) {
            Log::error('ValidateTelegramInitDataAction: Empty bot token provided.');
            return null;
        }

        parse_str($initData, $parsedData);
        
        if (!isset($parsedData['hash']) || !isset($parsedData['auth_date'])) {
            Log::warning('ValidateTelegramInitDataAction: Missing hash or auth_date.');
            return null;
        }

        $hash = $parsedData['hash'];
        unset($parsedData['hash']);

        // Check expiration (10 minute tolerance to prevent replay attacks)
        $authDate = (int) $parsedData['auth_date'];
        if (time() - $authDate > 600) {
            Log::warning('ValidateTelegramInitDataAction: auth_date expired.', ['auth_date' => $authDate]);
            return null;
        }

        // Sort data alphabetically
        ksort($parsedData);
        $dataCheckString = [];
        foreach ($parsedData as $key => $value) {
            $dataCheckString[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckString);

        // Calculate secret key via HMAC-SHA256
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        
        // Calculate validation hash
        $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

        // Compare securely
        if (!hash_equals($calculatedHash, $hash)) {
            Log::warning('ValidateTelegramInitDataAction: Hash mismatch.');
            return null;
        }

        // Successfully validated, return user data
        if (isset($parsedData['user'])) {
            return json_decode($parsedData['user'], true);
        }
        
        return null;
    }
}
