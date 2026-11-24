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
 *
 * Review 17: success is only 200/201 with the gateway's message (an `id` and a known status); the key only goes over
 * https (plain http only to a loopback gateway in local/testing); a missing or refused key (401/403) is reported
 * (SmsGatewayException, no key in it) and every failure is counted for the `sms_failures` alert (SmsFailures).
 * `ttl_seconds` is the caller's (a code: its 300 s lifetime) or services.onlinegroup.message_ttl_seconds.
 *
 * Not done here (documented in docs/OPERATIONS.md §28): a caller that retries a failed send itself (the booking job,
 * a resent code) gets a new Idempotency-Key, so a message the gateway took without our seeing the answer can arrive
 * twice; only the retries inside one send() share a key.
 */
class OnlinegroupSmsService implements SmsServiceInterface
{
    private const TRIES = 3;

    /** Pause before the 2nd and 3rd try, in milliseconds */
    private const BACKOFF_MS = [300, 1000];

    /** The message states of the gateway (docs: "Xabar holatlari"): on the way or delivered */
    public const ACCEPTED_STATUSES = ['queued', 'claimed', 'dispatched', 'sent', 'delivered'];

    /** Final states that mean the message will not be sent */
    public const FAILED_STATUSES = ['failed', 'expired'];

    public function send(string $phone, string $message, ?int $ttlSeconds = null): bool
    {
        $key = trim((string) setting('onlinegroup_api_key'));
        if ($key === '') {
            Log::error('Onlinegroup SMS is on but its API key (onlinegroup_api_key) is not configured: nothing sent.');

            return $this->cannotSend('Onlinegroup SMS is on but its API key (onlinegroup_api_key) is not configured.');
        }

        $base = rtrim((string) config('services.onlinegroup.base_url', 'https://sms.onlinegroup.uz'), '/');
        if (!self::safeBaseUrl($base)) {
            Log::error('Onlinegroup SMS: the gateway base URL (ONLINEGROUP_SMS_BASE_URL) is not https: nothing sent.');

            return $this->cannotSend('Onlinegroup SMS: the gateway base URL (ONLINEGROUP_SMS_BASE_URL) is not https.');
        }

        $to = self::normalizePhone($phone);
        if ($to === null) {
            Log::warning('Onlinegroup SMS: not an Uzbek mobile number, nothing sent.', ['phone' => PiiMask::phone($phone)]);
            $this->failures()->record(SmsFailures::REFUSED);

            return false;
        }

        $url = $base . '/v1/sms';
        $idempotencyKey = 'rizqim-' . Str::uuid()->toString();
        $body = [
            'phone' => $to,
            'text' => $message,
            'kind' => 'transactional',
            'ttl_seconds' => $ttlSeconds ?? (int) config('services.onlinegroup.message_ttl_seconds', 3600),
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

            $status = $response->status();
            Log::error('Onlinegroup SMS refused.', $context + ['status' => $status, 'detail' => self::detail($response)]);

            if (in_array($status, [401, 403], true)) {
                // A revoked key or one without the sms:send scope stops every sign-in code
                return $this->cannotSend("Onlinegroup SMS gateway refused the API key (HTTP {$status}): replace onlinegroup_api_key or give it the sms:send scope.");
            }
            $this->failures()->record($status === 429 ? SmsFailures::RATE_LIMITED : SmsFailures::REFUSED);

            return false;
        }

        Log::error('Onlinegroup SMS: gave up, the gateway did not take the message.', $context);
        $this->failures()->record(SmsFailures::UNAVAILABLE);

        return false;
    }

    /**
     * The key only travels over https; plain http is for a gateway on this machine in local development / tests.
     */
    public static function safeBaseUrl(string $base): bool
    {
        $scheme = strtolower((string) parse_url($base, PHP_URL_SCHEME));
        if ($scheme === 'https') {
            return true;
        }
        $host = strtolower(trim((string) parse_url($base, PHP_URL_HOST), '[]'));

        return $scheme === 'http' && app()->environment('local', 'testing') && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * A Uzbek number as the gateway stores it: +998 and nine digits. Accepts what the gateway accepts (9 digits,
     * 998 + 9, 8998 + 9, any spacing); null for anything else (a foreign number: the gateway would refuse it, 422).
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        // Review 17 L3: a number written with "+" is already international: only +998 is ours
        if (str_starts_with(ltrim($phone), '+')) {
            return preg_match('/^998\d{9}$/', $digits) ? '+' . $digits : null;
        }

        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        } elseif (strlen($digits) === 13 && str_starts_with($digits, '8998')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^998\d{9}$/', $digits) ? '+' . $digits : null;
    }

    /**
     * 201 queued (200: the same Idempotency-Key, the first message answered), with the message's `id` and a known
     * status. Any other 2xx, a body that is not the gateway's message, or a message already final as failed /
     * expired is not "sent".
     *
     * @param  array<string, mixed>  $context
     */
    private function accepted(Response $response, array $context): bool
    {
        $id = $response->json('id');
        $status = $response->json('status');
        $context += ['http_status' => $response->status(), 'message_id' => is_scalar($id) ? (string) $id : null, 'status' => is_string($status) ? $status : null, 'segments' => $response->json('segments')];

        if (in_array($status, self::FAILED_STATUSES, true)) {
            Log::error('Onlinegroup SMS: the gateway did not send the message.', $context + ['error_code' => $response->json('error_code')]);
            $this->failures()->record(SmsFailures::UNEXPECTED);

            return false;
        }

        if (!in_array($response->status(), [200, 201], true) || !is_string($id) || $id === '' || !in_array($status, self::ACCEPTED_STATUSES, true)) {
            Log::error('Onlinegroup SMS: the answer is not the gateway\'s message, not counted as sent.', $context);
            $this->failures()->record(SmsFailures::UNEXPECTED);

            return false;
        }

        Log::info('Onlinegroup SMS queued.', $context);

        return true;
    }

    /**
     * The driver cannot send at all (no key, a refused key, an unsafe URL): reported for Sentry, counted for the
     * `sms_failures` alert.
     */
    private function cannotSend(string $reason): bool
    {
        report(new SmsGatewayException($reason));
        $this->failures()->record(SmsFailures::CONFIG);

        return false;
    }

    private function failures(): SmsFailures
    {
        return app(SmsFailures::class);
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
