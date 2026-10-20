<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\User;
use App\Services\Push\FcmClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A Firebase service account made on the spot (a real RSA key, nothing from Google) and fakes for Google's token
 * endpoint and the FCM send endpoint.
 */
trait PushFixtures
{
    protected string $fcmPublicKey = '';

    protected function setUpPush(bool $configured = true): void
    {
        FcmClient::resetLoggedState();
        Cache::flush();
        config(['push.http_retry_sleep_ms' => 0]);

        if (!$configured) {
            config(['push.credentials_json' => null, 'push.credentials_path' => null]);

            return;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->fcmPublicKey = openssl_pkey_get_details($key)['key'];

        config(['push.credentials_json' => base64_encode(json_encode([
            'type' => 'service_account',
            'project_id' => 'rizqim-test',
            'client_email' => 'push@rizqim-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]))]);
    }

    /**
     * Google's token endpoint hands out a token, FCM accepts every message (or answers $fcm).
     *
     * @param mixed $fcm a response, or a list of responses served one after another
     */
    protected function fakeFirebase(mixed $fcm = null): void
    {
        $fcm ??= Http::response(['name' => 'projects/rizqim-test/messages/1'], 200);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            'fcm.googleapis.com/*' => is_array($fcm) ? Http::sequence($fcm) : $fcm,
            // Anything else (the Telegram Bot API in order flows)
            '*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    protected function fcmError(int $status, string $code, string $message = 'error', ?array $extra = null): \Illuminate\Http\Client\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface
    {
        $error = [
            'code' => $status,
            'message' => $message,
            'status' => $code,
            'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $code]],
        ];

        return Http::response(['error' => array_merge($error, $extra ?? [])], $status);
    }

    protected function makeDevice(User $user, DeviceType $type = DeviceType::PARTNER_MOBILE, ?string $token = 'fcm-token-1234567890'): Device
    {
        return Device::create([
            'user_id' => $user->id,
            'store_id' => $user->store_id,
            'branch_id' => $user->branch_id,
            'type' => $type->value,
            'name' => $type->label(),
            'push_token' => $token,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Request> the messages sent to FCM so far
     */
    protected function fcmRequests(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'))
            ->map(fn ($pair) => $pair[0])
            ->values();
    }

    /**
     * @return list<string> the device tokens FCM was asked to deliver to
     */
    protected function fcmTokens(): array
    {
        return $this->fcmRequests()->map(fn (Request $request) => $request['message']['token'])->all();
    }
}
