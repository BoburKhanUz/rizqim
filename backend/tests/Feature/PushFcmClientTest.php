<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Push\FcmClient;
use App\Services\Push\PushContent;
use App\Services\Push\PushSender;
use App\Services\Push\PushStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PushFcmClientTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPush();
        $this->setUpLifecycleStore();
    }

    protected function content(): PushContent
    {
        return new PushContent('Salom', 'Matn', ['order_uuid' => 'abc', 'count' => 3, 'skipped' => null], 'order-abc');
    }

    public function test_the_access_token_is_a_signed_rs256_jwt_exchanged_at_google_and_cached(): void
    {
        $this->fakeFirebase();
        $client = app(FcmClient::class);

        $this->assertSame(PushStatus::SENT, $client->send($this->content(), 'token-1')->status);
        $this->assertSame(PushStatus::SENT, $client->send($this->content(), 'token-2')->status);

        $exchanges = Http::recorded(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com'));
        $this->assertCount(1, $exchanges, 'the access token is cached until it expires');

        $form = $exchanges->first()[0]->data();
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type']);

        [$header, $claims, $signature] = explode('.', $form['assertion']);
        $decode = fn (string $part) => base64_decode(strtr($part, '-_', '+/'));

        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($decode($header), true));

        $claims = json_decode($decode($claims), true);
        $this->assertSame('push@rizqim-test.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $claims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertSame(3600, $claims['exp'] - $claims['iat']);

        $this->assertSame(1, openssl_verify("{$header}." . explode('.', $form['assertion'])[1], $decode($signature), $this->fcmPublicKey, OPENSSL_ALGO_SHA256));
        $this->assertStringNotContainsString('=', $form['assertion'], 'base64url, no padding');

        $send = $this->fcmRequests()->first();
        $this->assertSame('https://fcm.googleapis.com/v1/projects/rizqim-test/messages:send', $send->url());
        $this->assertSame('Bearer ya29.test-token', $send->header('Authorization')[0]);
    }

    public function test_the_message_has_android_and_apns_blocks_with_a_collapse_key_and_string_data(): void
    {
        $message = app(FcmClient::class)->payload($this->content(), 'device-token')['message'];

        $this->assertSame('device-token', $message['token']);
        $this->assertSame(['title' => 'Salom', 'body' => 'Matn'], $message['notification']);
        $this->assertSame(['order_uuid' => 'abc', 'count' => '3'], $message['data']);

        $this->assertSame('HIGH', $message['android']['priority']);
        $this->assertSame('order-abc', $message['android']['collapse_key']);
        $this->assertSame('default', $message['android']['notification']['sound']);
        $this->assertSame('orders', $message['android']['notification']['channel_id']);
        $this->assertSame('order-abc', $message['android']['notification']['tag']);

        $this->assertSame('order-abc', $message['apns']['headers']['apns-collapse-id']);
        $this->assertSame('10', $message['apns']['headers']['apns-priority']);
        $this->assertSame('default', $message['apns']['payload']['aps']['sound']);
        $this->assertSame('order-abc', $message['apns']['payload']['aps']['thread-id']);
    }

    public function test_a_message_without_a_collapse_key_or_data_leaves_them_out(): void
    {
        $message = app(FcmClient::class)->payload(new PushContent('A', 'B'), 't')['message'];

        $this->assertArrayNotHasKey('data', $message);
        $this->assertArrayNotHasKey('collapse_key', $message['android']);
        $this->assertArrayNotHasKey('apns-collapse-id', $message['apns']['headers']);
    }

    public function test_credentials_can_be_a_file_or_plain_json(): void
    {
        $json = base64_decode(config('push.credentials_json'));
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, $json);

        config(['push.credentials_json' => null, 'push.credentials_path' => $path]);
        $this->assertSame('rizqim-test', (new FcmClient())->credentials()?->projectId);

        config(['push.credentials_path' => null, 'push.credentials_json' => $json, 'push.project_id' => 'override']);
        $this->assertSame('override', (new FcmClient())->credentials()?->projectId);

        config(['push.credentials_json' => base64_encode('{"project_id": "x"}'), 'push.project_id' => null]);
        $this->assertNull((new FcmClient())->credentials(), 'a key without client_email and private_key is unusable');

        unlink($path);
    }

    public function test_a_dead_token_is_cleared_from_the_device(): void
    {
        $this->fakeFirebase($this->fcmError(404, 'UNREGISTERED', 'Requested entity was not found.'));
        $device = $this->makeDevice($this->customer, token: 'dead-token-123456');

        $outcome = app(PushSender::class)->send($device, $this->content());

        $this->assertSame(PushStatus::INVALID_TOKEN, $outcome->status);
        $this->assertNull($device->fresh()->push_token);
    }

    public function test_invalid_argument_about_the_token_clears_it_but_a_payload_error_does_not(): void
    {
        $tokenError = $this->fcmError(400, 'INVALID_ARGUMENT', 'The registration token is not a valid FCM registration token', [
            'details' => [['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => [['field' => 'message.token', 'description' => 'Invalid registration token']]]],
        ]);
        $payloadError = $this->fcmError(400, 'INVALID_ARGUMENT', 'Invalid value at message.data (map): too large');

        $device = $this->makeDevice($this->customer, token: 'garbled-token-1234');

        $this->fakeFirebase([$payloadError, $tokenError]);
        $this->assertSame(PushStatus::FAILED, app(PushSender::class)->send($device, $this->content())->status);
        $this->assertSame('garbled-token-1234', $device->fresh()->push_token, 'a payload bug must not wipe tokens');

        $this->assertSame(PushStatus::INVALID_TOKEN, app(PushSender::class)->send($device, $this->content())->status);
        $this->assertNull($device->fresh()->push_token);
    }

    public function test_a_token_the_app_refreshed_meanwhile_is_not_cleared(): void
    {
        $this->fakeFirebase($this->fcmError(404, 'UNREGISTERED'));
        $device = $this->makeDevice($this->customer, token: 'old-token-1234567');
        $stale = Device::find($device->id);

        Device::whereKey($device->id)->update(['push_token' => 'new-token-1234567']);
        app(PushSender::class)->send($stale, $this->content());

        $this->assertSame('new-token-1234567', $device->fresh()->push_token);
    }

    public function test_server_errors_and_rate_limits_are_retried_inside_the_request_then_sent(): void
    {
        $this->fakeFirebase([
            $this->fcmError(503, 'UNAVAILABLE'),
            $this->fcmError(429, 'QUOTA_EXCEEDED'),
            Http::response(['name' => 'ok'], 200),
        ]);

        $this->assertSame(PushStatus::SENT, app(FcmClient::class)->send($this->content(), 'token-1')->status);
        $this->assertCount(3, $this->fcmRequests());
    }

    public function test_a_persistent_server_error_asks_the_job_to_retry_later(): void
    {
        $this->fakeFirebase(Http::response(['error' => ['code' => 500, 'status' => 'INTERNAL', 'message' => 'boom']], 500));

        $outcome = app(FcmClient::class)->send($this->content(), 'token-1');

        $this->assertSame(PushStatus::RETRY, $outcome->status);
        $this->assertCount(3, $this->fcmRequests());
    }

    public function test_a_connection_error_is_an_outcome_not_an_exception(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        $this->assertSame(PushStatus::RETRY, app(FcmClient::class)->send($this->content(), 'token-1')->status);
    }

    public function test_an_expired_access_token_is_replaced_once(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::sequence()
                ->push(['access_token' => 'stale', 'expires_in' => 3600])
                ->push(['access_token' => 'fresh', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401)
                ->push(['name' => 'ok'], 200),
        ]);

        $this->assertSame(PushStatus::SENT, app(FcmClient::class)->send($this->content(), 'token-1')->status);
        $this->assertSame('Bearer fresh', $this->fcmRequests()->last()->header('Authorization')[0]);
    }

    public function test_a_failed_token_exchange_is_reported_without_throwing(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        $outcome = app(FcmClient::class)->send($this->content(), 'token-1');

        $this->assertSame(PushStatus::FAILED, $outcome->status);
        $this->assertStringContainsString('invalid_grant', (string) $outcome->detail);
    }

    public function test_a_private_key_that_cannot_sign_is_reported_without_throwing(): void
    {
        config(['push.credentials_json' => base64_encode(json_encode([
            'project_id' => 'p', 'client_email' => 'e@x', 'private_key' => 'not a key',
        ]))]);
        Http::fake();

        $this->assertSame(PushStatus::FAILED, app(FcmClient::class)->send($this->content(), 'token-1')->status);
        Http::assertNothingSent();
    }

    public function test_without_credentials_the_feature_is_off_and_logs_once_at_info(): void
    {
        $this->setUpPush(configured: false);
        Http::fake();
        Log::spy();

        $client = app(FcmClient::class);
        $this->assertFalse($client->isConfigured());
        $this->assertFalse($client->isConfigured());
        $this->assertFalse(app(PushSender::class)->isConfigured());

        $this->assertSame(PushStatus::DISABLED, app(FcmClient::class)->send($this->content(), 'token-1')->status);
        $device = $this->makeDevice($this->customer);
        $this->assertSame(PushStatus::DISABLED, app(PushSender::class)->send($device, $this->content())->status);

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once();
    }
}
