<?php

namespace Tests\Feature\Sms;

use App\Models\Setting;
use App\Services\Sms\OnlinegroupSmsService;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Wave P: SMS through the owner's gateway sms.onlinegroup.uz (sms_driver = onlinegroup). Our own codes go through
 * POST /v1/sms with the X-API-Key header; a retry (no answer, or a 5xx) reuses the same Idempotency-Key so the gateway
 * never queues a second SMS; 4xx (401/403/422/429) are never retried. Neither the key nor the text is logged.
 */
class OnlinegroupSmsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://sms.onlinegroup.uz/v1/sms';

    private string $apiKey;

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Built at runtime: no key-shaped literal in the repository
        $this->apiKey = 'sk_' . bin2hex(random_bytes(16));
        config([
            'settings.sms_driver' => null,
            'settings.onlinegroup_api_key' => null,
            'services.onlinegroup.base_url' => 'https://sms.onlinegroup.uz',
        ]);
        Setting::create(['key' => 'sms_driver', 'value' => 'onlinegroup']);
        Setting::create(['key' => 'onlinegroup_api_key', 'value' => $this->apiKey]);
        Cache::flush();
        Sleep::fake();

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event->message . ' ' . json_encode($event->context);
        });
    }

    private function queued(int $status = 201): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['id' => '9c1e4d77-8f3a-4b10-bb2f-6d0c9e2a7f43', 'phone' => '+998901234567', 'kind' => 'transactional', 'status' => 'queued', 'segments' => 1], $status);
    }

    private function assertNothingSecretLogged(string ...$secrets): void
    {
        $all = implode("\n", $this->logged);
        foreach ([$this->apiKey, ...$secrets] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
    }

    public function test_a_message_goes_to_v1_sms_with_the_key_header_and_an_idempotency_key(): void
    {
        Http::fake([self::URL => $this->queued()]);

        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901234567', 'RIZQIM tasdiqlash kodi: 482913'));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === self::URL
                && $request->header('X-API-Key') === [$this->apiKey]
                && !$request->hasHeader('Authorization')
                && strlen($request->header('Idempotency-Key')[0] ?? '') > 0
                && strlen($request->header('Idempotency-Key')[0]) <= 120
                && $request['phone'] === '+998901234567'
                && $request['text'] === 'RIZQIM tasdiqlash kodi: 482913'
                && $request['kind'] === 'transactional'
                && $request['ttl_seconds'] === 300;
        });
        $this->assertNothingSecretLogged('482913');
    }

    public function test_the_phone_is_normalized_to_e164_and_a_foreign_number_is_not_sent(): void
    {
        Http::fake([self::URL => $this->queued()]);
        $service = app(OnlinegroupSmsService::class);

        foreach (['90 123-45-67', '998901234567', '+998 (90) 123-45-67', '8998901234567'] as $phone) {
            $this->assertTrue($service->send($phone, 'Salom'));
        }
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request) => $request['phone'] === '+998901234567');
        Http::assertNotSent(fn (Request $request) => $request['phone'] !== '+998901234567');

        $this->assertFalse($service->send('+7 916 123 45 67', 'Salom'));
        $this->assertFalse($service->send('12345', 'Salom'));
        Http::assertSentCount(4);
    }

    public function test_no_answer_is_retried_once_with_the_same_idempotency_key(): void
    {
        Http::fakeSequence(self::URL)->pushFailedConnection()->push(['id' => 'x', 'status' => 'queued'], 200);

        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901234567', 'Kod: 111222'));

        $keys = [];
        Http::assertSent(function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];

            return true;
        });
        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertNothingSecretLogged('111222');
    }

    public function test_a_server_error_is_retried_with_the_same_key_and_gives_up_after_three_tries(): void
    {
        Http::fakeSequence(self::URL)->push(['detail' => 'x'], 503)->push(['detail' => 'x'], 502)->push(['detail' => 'x'], 500)->push(['id' => 'late'], 201);

        $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'Kod: 333444'));

        Http::assertSentCount(3);
        $this->assertNothingSecretLogged('333444');
        // Backed off between the tries
        Sleep::assertSleptTimes(2);
    }

    public function test_two_sends_are_two_messages_with_different_keys(): void
    {
        Http::fake([self::URL => $this->queued()]);
        $service = app(OnlinegroupSmsService::class);
        $service->send('+998901234567', 'Bir');
        $service->send('+998901234567', 'Bir');
        $keys = [];
        Http::assertSent(function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];

            return true;
        });
        $this->assertCount(2, array_unique($keys));
    }

    public function test_client_errors_and_429_are_not_retried(): void
    {
        foreach ([401, 403, 422, 429, 302] as $status) {
            Http::fake([self::URL => Http::response(['detail' => "Bu raqamga soatiga 5 tadan ko'p kod yuborib bo'lmaydi"], $status, $status === 302 ? ['Location' => 'https://evil.example/v1/sms'] : [])]);

            $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'Kod: 555666'), "HTTP {$status}");
            Http::assertSentCount(1);
            // A fresh fake per status (the recorder starts empty)
            $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
            Http::clearResolvedInstances();
        }
        $this->assertNothingSecretLogged('555666');
    }

    public function test_redirects_are_not_followed_and_requests_have_timeouts(): void
    {
        $options = [];
        Http::fake(function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return $this->queued();
        });

        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901234567', 'Salom'));
        $this->assertFalse($options['allow_redirects']);
        $this->assertGreaterThan(0, $options['timeout']);
        $this->assertLessThanOrEqual(15, $options['timeout']);
        $this->assertGreaterThan(0, $options['connect_timeout']);
    }

    public function test_without_a_key_nothing_is_sent(): void
    {
        Http::fake();
        Setting::where('key', 'onlinegroup_api_key')->update(['value' => null]);
        Cache::flush();

        $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'Salom'));
        Http::assertNothingSent();
    }

    public function test_the_phone_sign_in_code_reaches_the_gateway_and_verification_is_required(): void
    {
        Http::fake([self::URL => $this->queued()]);

        $this->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901112233'])->assertOk()->assertJsonPath('data.code', null);

        $text = null;
        Http::assertSent(function (Request $request) use (&$text) {
            $text = $request['text'];

            return $request['phone'] === '+998901112233';
        });
        $this->assertMatchesRegularExpression('/^RIZQIM tasdiqlash kodi: \d{6}$/', (string) $text);
        preg_match('/\d{6}/', (string) $text, $code);
        $this->assertNothingSecretLogged($code[0]);

        // A real SMS driver: the apps ask for a verified phone
        $this->assertTrue(\App\Services\Sms\SmsDriver::verificationRequired());
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();
        $this->assertFalse(\App\Services\Sms\SmsDriver::verificationRequired());
    }

    public function test_a_gateway_failure_tells_the_user_the_code_was_not_sent(): void
    {
        Http::fake([self::URL => Http::response(['detail' => 'x'], 401)]);

        $this->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901112233'])->assertStatus(500);
        Http::assertSentCount(1);
    }
}
