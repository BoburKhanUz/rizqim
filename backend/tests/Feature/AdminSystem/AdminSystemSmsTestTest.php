<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Admin\System\SmsTestSender;
use App\Services\Audit\AuditRedactor;
use App\Services\Sms\SmsFailures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tizim → Sozlamalar → SMS: "Sinov SMS yuborish". The platform admin checks the saved SMS settings by sending one fixed
 * text to a phone of their choice through the configured provider (never a text of their own), at most 5 an hour per
 * member and 30 an hour for the whole platform (only attempts that reached a provider count). The answer says plainly what went wrong; the provider key never reaches the answer, the log or the audit entry.
 */
class AdminSystemSmsTestTest extends TestCase
{
    use RefreshDatabase;
    use AdminSystemFixtures;

    private const URI = '/system/settings/sms-test';

    private const GATEWAY = 'https://sms.onlinegroup.uz/v1/sms';

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
            'settings.eskiz_email' => null,
            'settings.eskiz_password' => null,
            'services.onlinegroup.base_url' => 'https://sms.onlinegroup.uz',
        ]);
        Setting::create(['key' => 'sms_driver', 'value' => 'onlinegroup']);
        Setting::create(['key' => 'onlinegroup_api_key', 'value' => $this->apiKey]);
        Cache::flush();
        Sleep::fake();
        Http::preventStrayRequests();

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event->message . ' ' . json_encode($event->context);
        });
    }

    private function queued(): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['id' => '9c1e4d77-8f3a-4b10-bb2f-6d0c9e2a7f43', 'phone' => '+998901234567', 'kind' => 'transactional', 'status' => 'queued', 'segments' => 1], 201);
    }

    private function audits(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::query()->where('action', 'admin.system.sms_test_sent')->get();
    }

    private function assertNoSecretOrFullPhoneLeaked(string ...$extra): void
    {
        $all = implode("\n", $this->logged) . "\n" . AuditLog::query()->get()->toJson();
        foreach ([$this->apiKey, '901234567', ...$extra] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
    }

    public function test_only_a_stepped_up_system_manager_can_send_a_test(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        $body = ['phone' => '+998901234567'];

        $this->adminJson('POST', self::URI, null, $body)->assertUnauthorized();
        foreach ([PlatformRole::VIEWER, PlatformRole::OPERATOR, PlatformRole::FINANCE, PlatformRole::SUPPORT] as $role) {
            $this->adminJson('POST', self::URI, $this->confirmedSession($this->makeMember($role)), $body)->assertForbidden()->assertJsonPath('code', 'forbidden');
        }
        $this->adminJson('POST', self::URI, $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)), $body)->assertForbidden()->assertJsonPath('code', 'reauth_required');

        Http::assertNothingSent();
        $this->assertCount(0, $this->audits());
    }

    public function test_the_phone_must_be_an_uzbek_mobile_number(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        $token = $this->superToken();

        $this->adminJson('POST', self::URI, $token, [])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->adminJson('POST', self::URI, $token, ['phone' => '+1 202 555 0123'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->adminJson('POST', self::URI, $token, ['phone' => 'telefon'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->adminJson('POST', self::URI, $token, ['phone' => '90 123 45'])->assertStatus(422)->assertJsonValidationErrors('phone');

        Http::assertNothingSent();
        $this->assertCount(0, $this->audits());
    }

    public function test_the_fixed_text_goes_through_the_saved_onlinegroup_key_and_is_audited(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);

        // The way it is typed: normalised like every SMS (OnlinegroupSmsService); a text of one's own is ignored
        $response = $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '90 123 45 67', 'text' => 'Boshqa matn', 'message' => 'Boshqa matn'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver', 'onlinegroup')
            ->assertJsonPath('data.phone', '+998 90 *** ** 67');
        $this->assertStringNotContainsString($this->apiKey, $response->getContent());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::GATEWAY
            && $request->header('X-API-Key') === [$this->apiKey]
            && $request['phone'] === '+998901234567'
            && $request['text'] === SmsTestSender::TEXT);
        $this->assertSame('RIZQIM: sinov xabari. SMS sozlamalari ishlayapti.', SmsTestSender::TEXT);

        $audit = $this->audits()->sole();
        $this->assertSame($this->superadmin?->id, $audit->user_id);
        // Masked as every phone in the audit log (AuditRedactor)
        $this->assertSame(['onlinegroup', 'sent', AuditRedactor::maskPhone('+998901234567')], [$audit->payload_json['driver'], $audit->payload_json['outcome'], $audit->payload_json['phone']]);
        $this->assertNoSecretOrFullPhoneLeaked();
    }

    public function test_with_sms_off_the_saved_onlinegroup_key_can_be_checked_before_switching(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertOk()->assertJsonPath('data.driver', 'onlinegroup');
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::GATEWAY
            && $request->header('X-API-Key') === [$this->apiKey]
            && $request['text'] === SmsTestSender::TEXT);
    }

    public function test_a_key_check_with_sms_off_does_not_raise_the_sms_failures_alert(): void
    {
        Http::fake([self::GATEWAY => Http::response(['detail' => 'Invalid API key'], 401)]);
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_key_refused');
        // Sign-in codes do not use this key yet: nothing for the on-call group
        $this->assertSame([], app(SmsFailures::class)->counts(60));
    }

    public function test_a_failed_test_of_the_live_method_is_counted_for_the_alert(): void
    {
        Http::fake([self::GATEWAY => Http::response(['detail' => 'Invalid API key'], 401)]);

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_key_refused');
        $this->assertSame([SmsFailures::CONFIG => 1], app(SmsFailures::class)->counts(60));
    }

    public function test_an_answer_that_is_not_the_gateways_message_is_sms_send_failed(): void
    {
        Http::fake([self::GATEWAY => Http::response(['ok' => true], 200)]);

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_send_failed');
        $this->assertSame('sms_send_failed', $this->audits()->sole()->payload_json['outcome']);
    }

    public function test_a_gateway_address_that_is_not_https_is_not_set_up_not_a_refused_key(): void
    {
        config(['services.onlinegroup.base_url' => 'http://sms.example.uz']);

        $response = $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_not_configured');
        $this->assertStringContainsString('https', (string) $response->json('message'));
        Http::assertNothingSent();
    }

    public function test_an_empty_sms_driver_means_sms_off_as_the_settings_card_shows_it(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        Setting::where('key', 'sms_driver')->update(['value' => '']);
        Cache::flush();

        // As log: the saved Onlinegroup key is checked
        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertOk()->assertJsonPath('data.driver', 'onlinegroup');
        $this->assertSame('log', \App\Services\Sms\SmsDriver::current());

        Setting::where('key', 'sms_driver')->delete();
        config(['settings.sms_driver' => '']);
        Cache::flush();
        $this->assertSame('log', \App\Services\Sms\SmsDriver::current());
    }

    public function test_sms_that_is_not_set_up_is_a_clear_refusal(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        $token = $this->superToken();

        // Onlinegroup chosen, its key gone
        Setting::where('key', 'onlinegroup_api_key')->update(['value' => '']);
        Cache::flush();
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_not_configured')->assertJsonPath('data.reason', 'sms_not_configured');

        // SMS off and no key to check
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_not_configured');

        // Eskiz without its password
        Setting::where('key', 'sms_driver')->update(['value' => 'eskiz']);
        Setting::create(['key' => 'eskiz_email', 'value' => 'sms@rizqim.uz']);
        Cache::flush();
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_not_configured');

        Http::assertNothingSent();
        $this->assertCount(0, $this->audits());
    }

    /**
     * @return array<string, array{0: int, 1: string, 2: int}>
     */
    public static function gatewayFailures(): array
    {
        return [
            'key refused (401)' => [401, 'sms_key_refused', 1],
            'key without sms:send (403)' => [403, 'sms_key_refused', 1],
            "the gateway's own limit (429)" => [429, 'sms_provider_rate_limited', 1],
            'data refused (422)' => [422, 'sms_provider_refused', 1],
            'gateway down (503, every try)' => [503, 'sms_provider_unavailable', 3],
        ];
    }

    #[DataProvider('gatewayFailures')]
    public function test_a_gateway_failure_is_said_plainly_and_audited(int $status, string $code, int $tries): void
    {
        Http::fake([self::GATEWAY => Http::response(['detail' => 'Gateway says no'], $status)]);

        $response = $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', $code)
            ->assertJsonPath('data.reason', $code)
            ->assertJsonPath('data.driver', 'onlinegroup');
        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertStringNotContainsString($this->apiKey, $response->getContent());
        Http::assertSentCount($tries);

        // The attempt is in the audit log even though it failed
        $audit = $this->audits()->sole();
        $this->assertSame(['onlinegroup', $code], [$audit->payload_json['driver'], $audit->payload_json['outcome']]);
        $this->assertNoSecretOrFullPhoneLeaked();
    }

    private const ESKIZ_LOGIN = 'https://notify.eskiz.uz/api/auth/login';

    private const ESKIZ_SEND = 'https://notify.eskiz.uz/api/message/sms/send';

    /**
     * SMS through Eskiz with its email and a runtime password; returns the password and the token the fake login answers.
     *
     * @return array{0: string, 1: string}
     */
    private function useEskiz(): array
    {
        $password = 'pw-' . bin2hex(random_bytes(8));
        $token = 'tok-' . bin2hex(random_bytes(12));
        Setting::where('key', 'sms_driver')->update(['value' => 'eskiz']);
        Setting::create(['key' => 'eskiz_email', 'value' => 'sms@rizqim.uz']);
        Setting::create(['key' => 'eskiz_password', 'value' => $password]);
        Cache::flush();

        return [$password, $token];
    }

    public function test_eskiz_sends_the_fixed_text_and_leaks_nothing(): void
    {
        [$password, $token] = $this->useEskiz();
        Http::fake([
            self::ESKIZ_LOGIN => Http::response(['data' => ['token' => $token]]),
            self::ESKIZ_SEND => Http::response(['id' => 'abc', 'status' => 'waiting', 'message' => 'Waiting for SMS provider', 'mobile_phone' => '998901234567']),
        ]);

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '90 123 45 67'])->assertOk()->assertJsonPath('data.driver', 'eskiz');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === self::ESKIZ_SEND
            && $request->header('Authorization') === ['Bearer ' . $token]
            && $request['mobile_phone'] === '998901234567'
            && $request['message'] === SmsTestSender::TEXT);
        $this->assertNoSecretOrFullPhoneLeaked($password, $token);
    }

    public function test_eskiz_refusing_the_email_and_password_is_a_refused_key(): void
    {
        [$password] = $this->useEskiz();
        Http::fake([self::ESKIZ_LOGIN => Http::response(['message' => 'Invalid credentials for 998901234567'], 401)]);

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_key_refused')->assertJsonPath('data.driver', 'eskiz');
        Http::assertSentCount(1);
        $this->assertSame([SmsFailures::CONFIG => 1], app(SmsFailures::class)->counts(60));
        $this->assertNoSecretOrFullPhoneLeaked($password);
    }

    public function test_eskiz_retries_an_expired_token_only_once(): void
    {
        [$password, $token] = $this->useEskiz();
        Http::fake([
            self::ESKIZ_LOGIN => Http::response(['data' => ['token' => $token]]),
            self::ESKIZ_SEND => Http::response(['message' => 'Expired for 998901234567'], 401),
        ]);

        $this->adminJson('POST', self::URI, $this->superToken(), ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_key_refused');
        // login, send (401), a fresh login, send (401) again: then it stops
        Http::assertSentCount(4);
        $this->assertNoSecretOrFullPhoneLeaked($password, $token);
    }

    public function test_eskiz_without_an_answer_or_with_a_bad_phone_is_said_as_such(): void
    {
        [$password, $token] = $this->useEskiz();
        Http::fake([
            self::ESKIZ_LOGIN => Http::response(['data' => ['token' => $token]]),
            self::ESKIZ_SEND => Http::sequence()->push(['message' => 'down'], 503)->push(['message' => 'bad mobile_phone 998901234567'], 422),
        ]);
        $token2 = $this->superToken();

        $this->adminJson('POST', self::URI, $token2, ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_provider_unavailable');
        $this->adminJson('POST', self::URI, $token2, ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_provider_refused');
        $this->assertNoSecretOrFullPhoneLeaked($password, $token);
    }

    public function test_at_most_five_tests_an_hour_per_member(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        $token = $this->superToken();

        for ($i = 0; $i < 5; $i++) {
            $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertOk();
        }
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'sms_test_rate_limited')
            ->assertJsonPath('data.reason', 'sms_test_rate_limited')
            ->assertHeader('Retry-After');
        Http::assertSentCount(5);

        // Another member has their own allowance
        $other = $this->makeMember(PlatformRole::SUPERADMIN);
        $this->adminJson('POST', self::URI, $this->confirmedSession($other), ['phone' => '+998901234567'])->assertOk();
        Http::assertSentCount(6);
    }

    public function test_refused_requests_do_not_use_up_the_allowance(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        $token = $this->superToken();

        // A typo, SMS not set up, another role, no step-up: none reaches a provider, none counts
        for ($i = 0; $i < 6; $i++) {
            $this->adminJson('POST', self::URI, $token, ['phone' => '90 123'])->assertStatus(422)->assertJsonValidationErrors('phone');
        }
        Setting::where('key', 'onlinegroup_api_key')->update(['value' => '']);
        Cache::flush();
        for ($i = 0; $i < 6; $i++) {
            $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertStatus(422)->assertJsonPath('code', 'sms_not_configured');
        }
        $this->adminJson('POST', self::URI, $this->confirmedSession($this->makeMember(PlatformRole::VIEWER)), ['phone' => '+998901234567'])->assertForbidden();
        $this->adminJson('POST', self::URI, $this->signIn($this->superadmin), ['phone' => '+998901234567'])->assertForbidden()->assertJsonPath('code', 'reauth_required');

        Setting::where('key', 'onlinegroup_api_key')->update(['value' => $this->apiKey]);
        Cache::flush();
        for ($i = 0; $i < 5; $i++) {
            $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertOk();
        }
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertStatus(429)->assertJsonPath('code', 'sms_test_rate_limited');
        Http::assertSentCount(5);
    }

    public function test_the_whole_platform_sends_at_most_thirty_tests_an_hour(): void
    {
        Http::fake([self::GATEWAY => $this->queued()]);
        // 29 attempts already made by other members this hour
        for ($i = 0; $i < 29; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit('admin-sms-test:global', 3600);
        }

        $token = $this->superToken();
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])->assertOk();
        $this->adminJson('POST', self::URI, $token, ['phone' => '+998901234567'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'sms_test_rate_limited')
            ->assertHeader('Retry-After');
        Http::assertSentCount(1);
    }
}
