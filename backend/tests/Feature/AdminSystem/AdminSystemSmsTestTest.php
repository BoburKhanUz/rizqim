<?php

namespace Tests\Feature\AdminSystem;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Admin\System\SmsTestSender;
use App\Services\Audit\AuditRedactor;
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
 * member. The answer says plainly what went wrong; the provider key never reaches the answer, the log or the audit entry.
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
}
