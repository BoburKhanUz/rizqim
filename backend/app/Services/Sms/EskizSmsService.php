<?php

namespace App\Services\Sms;

use App\Support\PiiMask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS through Eskiz (sms_driver = eskiz): a token from the email and password (cached 20 days), then the message.
 *
 * An expired token (401 on send) is forgotten and the send tried once more with a fresh one, never more. Every request
 * has a timeout. The log has the masked phone and the HTTP status only: never the password, the token, the text or
 * Eskiz's answer (it repeats the phone). Every failure is recorded for SmsFailures (the `sms_failures` alert and the
 * control centre's test SMS): CONFIG when Eskiz refuses the email / password, UNAVAILABLE for no answer or a 5xx,
 * RATE_LIMITED for 429, REFUSED for another 4xx.
 */
class EskizSmsService implements SmsServiceInterface
{
    private const LOGIN_URL = 'https://notify.eskiz.uz/api/auth/login';

    private const SEND_URL = 'https://notify.eskiz.uz/api/message/sms/send';

    private const TOKEN_KEY = 'eskiz_token';

    public function send(string $phone, string $message, ?int $ttlSeconds = null): bool
    {
        return $this->attempt($phone, $message, $ttlSeconds, true);
    }

    private function attempt(string $phone, string $message, ?int $ttlSeconds, bool $mayRetry): bool
    {
        $driver = setting('sms_driver', 'log');
        $context = ['phone' => PiiMask::phone($phone)];

        if ($driver === 'log') {
            Log::info('Eskiz SMS mocked (sms_driver = log): nothing sent.', $context);

            return true;
        }

        if ($driver !== 'eskiz') {
            return false;
        }

        $email = setting('eskiz_email');
        $password = setting('eskiz_password');
        $from = setting('eskiz_from', '4546');

        if (empty($email) || empty($password)) {
            Log::error('Eskiz SMS service is enabled but email or password is not configured.');
            $this->failures()->record(SmsFailures::CONFIG);

            return false;
        }

        $token = $this->token((string) $email, (string) $password);
        if ($token === null) {
            return false;
        }

        // Eskiz expects the number without "+" (998901234567)
        $cleanPhone = ltrim($phone, '+');

        try {
            $response = $this->http()->withToken($token)->post(self::SEND_URL, [
                'mobile_phone' => $cleanPhone,
                'message' => $message,
                'from' => $from,
            ]);
        } catch (ConnectionException) {
            Log::warning('Eskiz SMS: no answer from Eskiz.', $context);
            $this->failures()->record(SmsFailures::UNAVAILABLE);

            return false;
        }

        if ($response->successful()) {
            Log::info('Eskiz SMS sent.', $context);

            return true;
        }

        // An expired or revoked token: forget it and try once more with a fresh one
        if ($response->status() === 401 && $mayRetry) {
            Cache::forget(self::TOKEN_KEY);

            return $this->attempt($phone, $message, $ttlSeconds, false);
        }

        Log::error('Eskiz SMS send failed.', $context + ['status' => $response->status()]);
        $this->failures()->record($this->reason($response));

        return false;
    }

    /**
     * The cached token, or a fresh one from the email and password; null (the reason recorded) when Eskiz gives none.
     */
    private function token(string $email, string $password): ?string
    {
        $cached = Cache::get(self::TOKEN_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http()->post(self::LOGIN_URL, ['email' => $email, 'password' => $password]);
        } catch (ConnectionException) {
            Log::warning('Eskiz login: no answer from Eskiz.');
            $this->failures()->record(SmsFailures::UNAVAILABLE);

            return null;
        }

        $token = $response->successful() ? $response->json('data.token') : null;
        if (is_string($token) && $token !== '') {
            Cache::put(self::TOKEN_KEY, $token, now()->addDays(20));

            return $token;
        }

        Log::error('Eskiz login failed.', ['status' => $response->status()]);
        // Refused email / password (or a 2xx without a token): the settings are wrong; a 5xx is Eskiz being down
        $this->failures()->record($response->serverError() ? SmsFailures::UNAVAILABLE : SmsFailures::CONFIG);

        return null;
    }

    private function reason(Response $response): string
    {
        return match (true) {
            $response->status() === 401, $response->status() === 403 => SmsFailures::CONFIG,
            $response->status() === 429 => SmsFailures::RATE_LIMITED,
            $response->serverError() => SmsFailures::UNAVAILABLE,
            default => SmsFailures::REFUSED,
        };
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.eskiz.timeout', 10))
            ->connectTimeout((int) config('services.eskiz.connect_timeout', 5));
    }

    private function failures(): SmsFailures
    {
        return app(SmsFailures::class);
    }
}
