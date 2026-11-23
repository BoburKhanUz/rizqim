<?php

namespace App\Services\Sms;

use App\Support\PiiMask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Wave P: SMS through the owner's gateway sms.onlinegroup.uz (sms_driver = onlinegroup).
 *
 * POST {base}/v1/sms with the `X-API-Key` header (the control-centre secret onlinegroup_api_key; the app's key needs
 * the `sms:send` scope), JSON {phone (+998 and nine digits), text, kind: transactional, ttl_seconds}. Our own codes
 * go through it (PhoneOtpService keeps generating, hashing, limiting and auditing them); the gateway's /v1/otp/*
 * endpoints are not used.
 *
 * Safe retries: every send() gets one Idempotency-Key, reused by its retries, so a request the gateway took but
 * whose answer was lost does not queue a second SMS (the gateway answers 200 with the first message). Only no
 * answer and 5xx are retried (3 tries in all); 4xx never are (401/403 a key problem, 422 bad data, 429 a limit that
 * counts every request). Redirects are not followed. Neither the key nor the text is ever logged; the phone masked.
 */
class OnlinegroupSmsService implements SmsServiceInterface
{
    private const TRIES = 3;

    /** Pause before the 2nd and 3rd try, in milliseconds */
    private const BACKOFF_MS = [300, 1000];

    public function send(string $phone, string $message): bool
    {
        $key = trim((string) setting('onlinegroup_api_key'));
        if ($key === '') {
            Log::error('Onlinegroup SMS is on but its API key (onlinegroup_api_key) is not configured: nothing sent.');

            return false;
        }

        $to = self::normalizePhone($phone);
        if ($to === null) {
            Log::warning('Onlinegroup SMS: not an Uzbek mobile number, nothing sent.', ['phone' => PiiMask::phone($phone)]);

            return false;
        }

        $url = rtrim((string) config('services.onlinegroup.base_url', 'https://sms.onlinegroup.uz'), '/') . '/v1/sms';
        $idempotencyKey = 'rizqim-' . Str::uuid()->toString();
        $body = [
            'phone' => $to,
            'text' => $message,
            'kind' => 'transactional',
            'ttl_seconds' => (int) config('services.onlinegroup.ttl_seconds', 300),
        ];
        $context = ['phone' => PiiMask::phone($to)];

        for ($try = 1; $try <= self::TRIES; $try++) {
            if ($try > 1) {
                Sleep::for(self::BACKOFF_MS[$try - 2])->milliseconds();
            }

            try {
                $response = Http::withHeaders(['X-API-Key' => $key, 'Idempotency-Key' => $idempotencyKey])
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('services.onlinegroup.timeout', 10))
                    ->connectTimeout((int) config('services.onlinegroup.connect_timeout', 5))
                    ->withOptions(['allow_redirects' => false])
                    ->post($url, $body);
            } catch (ConnectionException) {
                Log::warning('Onlinegroup SMS: no answer from the gateway.', $context + ['try' => $try]);

                continue;
            }

            if ($response->successful()) {
                return $this->accepted($response, $context);
            }

            if ($response->serverError()) {
                Log::warning('Onlinegroup SMS: gateway error.', $context + ['try' => $try, 'status' => $response->status()]);

                continue;
            }

            Log::error('Onlinegroup SMS refused.', $context + ['status' => $response->status(), 'detail' => self::detail($response)]);

            return false;
        }

        Log::error('Onlinegroup SMS: gave up, the gateway did not take the message.', $context);

        return false;
    }

    /**
     * A Uzbek number as the gateway stores it: +998 and nine digits. Accepts what the gateway accepts (9 digits,
     * 998 + 9, 8998 + 9, any spacing); null for anything else (a foreign number: the gateway would refuse it, 422).
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        } elseif (strlen($digits) === 13 && str_starts_with($digits, '8998')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^998\d{9}$/', $digits) ? '+' . $digits : null;
    }

    /**
     * 201 queued (200: the same Idempotency-Key, the first message answered). A message already final as failed or
     * expired is not sent.
     *
     * @param  array<string, mixed>  $context
     */
    private function accepted(Response $response, array $context): bool
    {
        $status = $response->json('status');
        $context += ['message_id' => $response->json('id'), 'status' => $status, 'segments' => $response->json('segments')];

        if (in_array($status, ['failed', 'expired'], true)) {
            Log::error('Onlinegroup SMS: the gateway did not send the message.', $context + ['error_code' => $response->json('error_code')]);

            return false;
        }

        Log::info('Onlinegroup SMS queued.', $context);

        return true;
    }

    /**
     * The gateway's own reason when it is a short sentence (401/403/429 answers); a 422 list is left out.
     */
    private static function detail(Response $response): ?string
    {
        $detail = $response->json('detail');

        return is_string($detail) ? Str::limit($detail, 200) : null;
    }
}
