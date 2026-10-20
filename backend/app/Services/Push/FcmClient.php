<?php

namespace App\Services\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging HTTP v1 without a Google package: the OAuth2 access token comes from a JWT signed with the
 * service account's private key (RS256, openssl) and exchanged at Google; it is cached until shortly before it expires.
 * Without credentials isConfigured() is false and nothing here is called.
 */
class FcmClient
{
    private const TOKEN_CACHE_PREFIX = 'push:fcm:access-token:';

    // The "disabled" notice is logged once per process, not once per message
    private static bool $loggedDisabled = false;

    private ?FcmCredentials $credentials = null;

    private bool $credentialsLoaded = false;

    public function isConfigured(): bool
    {
        if ($this->credentials() !== null) {
            return true;
        }

        if (!self::$loggedDisabled) {
            self::$loggedDisabled = true;
            Log::info('Push notifications are off: no usable Firebase credentials (FIREBASE_CREDENTIALS / FIREBASE_CREDENTIALS_JSON).');
        }

        return false;
    }

    public function credentials(): ?FcmCredentials
    {
        if (!$this->credentialsLoaded) {
            $this->credentials = FcmCredentials::fromConfig();
            $this->credentialsLoaded = true;
        }

        return $this->credentials;
    }

    /**
     * Forget the "already logged" state (tests).
     */
    public static function resetLoggedState(): void
    {
        self::$loggedDisabled = false;
    }

    /**
     * The FCM v1 message for one device token.
     *
     * @return array{message: array<string, mixed>}
     */
    public function payload(PushContent $content, string $token): array
    {
        $android = [
            'priority' => 'HIGH',
            'notification' => [
                'sound' => 'default',
                'channel_id' => (string) config('push.android_channel_id'),
            ],
        ];
        $apns = [
            'headers' => ['apns-priority' => '10', 'apns-push-type' => 'alert'],
            'payload' => ['aps' => ['sound' => 'default']],
        ];

        // A newer push about the same order replaces the older one on the phone
        if ($content->collapseKey) {
            $android['collapse_key'] = $content->collapseKey;
            $android['notification']['tag'] = $content->collapseKey;
            $apns['headers']['apns-collapse-id'] = substr($content->collapseKey, 0, 64);
            $apns['payload']['aps']['thread-id'] = $content->collapseKey;
        }

        $message = [
            'token' => $token,
            'notification' => ['title' => $content->title, 'body' => $content->body],
            'android' => $android,
            'apns' => $apns,
        ];

        if ($content->stringData() !== []) {
            $message['data'] = $content->stringData();
        }

        return ['message' => $message];
    }

    /**
     * Send to one device token. Never throws: every way it can go wrong is a PushOutcome.
     */
    public function send(PushContent $content, string $token): PushOutcome
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return new PushOutcome(PushStatus::DISABLED);
        }

        try {
            $payload = $this->payload($content, $token);
            $response = $this->post($credentials, $payload);

            // The cached access token was revoked or expired early: get a fresh one and try once more
            if ($response->status() === 401) {
                Cache::forget($this->cacheKey($credentials));
                $response = $this->post($credentials, $payload);
            }
        } catch (ConnectionException $e) {
            return new PushOutcome(PushStatus::RETRY, 'FCM connection: ' . $e->getMessage());
        } catch (FcmAuthException $e) {
            return new PushOutcome($e->retryable ? PushStatus::RETRY : PushStatus::FAILED, $e->getMessage());
        } catch (Throwable $e) {
            return new PushOutcome(PushStatus::RETRY, get_class($e) . ': ' . $e->getMessage());
        }

        return $this->classify($response);
    }

    /**
     * The OAuth2 access token for FCM, from the cache or freshly exchanged.
     */
    public function accessToken(FcmCredentials $credentials): string
    {
        $key = $this->cacheKey($credentials);
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->request()->asForm()->post((string) config('push.token_uri'), [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->signedJwt($credentials),
        ]);

        $token = $response->successful() ? $response->json('access_token') : null;

        if (!is_string($token) || $token === '') {
            // 4xx: the key itself is wrong (revoked, clock skew…) and waiting will not fix it; Google's own trouble will pass
            throw new FcmAuthException(
                'Google token exchange failed (' . $response->status() . '): ' . mb_substr($response->body(), 0, 300),
                retryable: $response->status() === 429 || $response->status() >= 500,
            );
        }

        $lifetime = (int) $response->json('expires_in', 3600);
        Cache::put($key, $token, max(60, $lifetime - 120));

        return $token;
    }

    /**
     * RS256 JWT for the service account (https://developers.google.com/identity/protocols/oauth2/service-account).
     */
    public function signedJwt(FcmCredentials $credentials): string
    {
        $now = time();
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url((string) json_encode([
            'iss' => $credentials->clientEmail,
            'scope' => config('push.scope'),
            'aud' => config('push.token_uri'),
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        $key = openssl_pkey_get_private($credentials->privateKey);

        if ($key === false || !openssl_sign("{$header}.{$claims}", $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new FcmAuthException('The Firebase private key cannot sign (is FIREBASE_CREDENTIALS a service account key?)', retryable: false);
        }

        return "{$header}.{$claims}." . $this->base64Url($signature);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function post(FcmCredentials $credentials, array $payload): Response
    {
        $url = str_replace('{project}', rawurlencode($credentials->projectId), (string) config('push.endpoint'));

        return $this->request()
            ->withToken($this->accessToken($credentials))
            ->acceptJson()
            ->post($url, $payload);
    }

    /**
     * Timeouts and retries on connection errors, 429 and 5xx; the last response is returned as it is.
     */
    protected function request(): PendingRequest
    {
        return Http::connectTimeout((int) config('push.connect_timeout'))
            ->timeout((int) config('push.timeout'))
            ->retry(
                max(1, (int) config('push.http_retries')),
                (int) config('push.http_retry_sleep_ms'),
                function (Throwable $e): bool {
                    if ($e instanceof RequestException) {
                        $status = $e->response->status();

                        return $status === 429 || $status >= 500;
                    }

                    return $e instanceof ConnectionException;
                },
                throw: false,
            );
    }

    protected function classify(Response $response): PushOutcome
    {
        if ($response->successful()) {
            return new PushOutcome(PushStatus::SENT);
        }

        $status = $response->status();
        $error = (array) $response->json('error', []);
        $code = $this->fcmErrorCode($error);
        $detail = "FCM {$status}" . ($code ? " {$code}" : '') . ': ' . mb_substr((string) ($error['message'] ?? $response->body()), 0, 300);

        // The token is dead: the app was uninstalled, or the token belongs to another Firebase project
        if (in_array($code, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true) || $this->rejectsTheToken($error)) {
            return new PushOutcome(PushStatus::INVALID_TOKEN, $detail);
        }

        if ($status === 429 || $status >= 500) {
            return new PushOutcome(PushStatus::RETRY, $detail);
        }

        return new PushOutcome(PushStatus::FAILED, $detail);
    }

    /**
     * FcmError.errorCode from the error details (UNREGISTERED, QUOTA_EXCEEDED…), else the plain status (NOT_FOUND).
     *
     * @param array<string, mixed> $error
     */
    protected function fcmErrorCode(array $error): ?string
    {
        foreach ((array) ($error['details'] ?? []) as $detail) {
            if (is_array($detail) && isset($detail['errorCode'])) {
                return (string) $detail['errorCode'];
            }
        }

        return isset($error['status']) ? (string) $error['status'] : null;
    }

    /**
     * INVALID_ARGUMENT is also what a broken payload looks like, so it only counts as a bad token when FCM points at
     * the token field (or says "registration token"): a payload bug must not wipe every device's token.
     *
     * @param array<string, mixed> $error
     */
    protected function rejectsTheToken(array $error): bool
    {
        if (($error['status'] ?? null) !== 'INVALID_ARGUMENT') {
            return false;
        }

        foreach ((array) ($error['details'] ?? []) as $detail) {
            foreach ((array) ($detail['fieldViolations'] ?? []) as $violation) {
                if (is_array($violation) && str_contains((string) ($violation['field'] ?? ''), 'token')) {
                    return true;
                }
            }
        }

        return (bool) preg_match('/registration token|not a valid FCM/i', (string) ($error['message'] ?? ''));
    }

    protected function cacheKey(FcmCredentials $credentials): string
    {
        return self::TOKEN_CACHE_PREFIX . sha1($credentials->projectId . '|' . $credentials->clientEmail);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
