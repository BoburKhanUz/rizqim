<?php

namespace Tests\Feature\Sms;

use App\Models\Setting;
use App\Services\Admin\System\SystemSettings;
use App\Services\Audit\AuditRedactor;
use App\Services\Sms\OnlinegroupSmsService;
use App\Services\Sms\SmsGatewayException;
use App\Services\Sms\SmsServiceInterface;
use App\Support\SentryScrubber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Review 17 of the onlinegroup driver (30202b15): secrets at rest, reporting, global send budget, the log driver in
 * production, strict success, phone and base URL checks, scrubbers.
 */
class OnlinegroupHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://sms.onlinegroup.uz/v1/sms';

    private string $apiKey;

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = 'sk_' . bin2hex(random_bytes(16));
        config([
            'settings.sms_driver' => null,
            'settings.onlinegroup_api_key' => null,
            'services.onlinegroup.base_url' => 'https://sms.onlinegroup.uz',
        ]);
        Setting::create(['key' => 'sms_driver', 'value' => 'onlinegroup']);
        Cache::flush();
        Sleep::fake();
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event->message . ' ' . json_encode($event->context);
        });
    }

    private function storeKey(): void
    {
        app(SystemSettings::class)->replaceSecret('onlinegroup_api_key', $this->apiKey);
        Cache::flush();
    }

    // M1: the gateway key sits in the settings table (and the forever cache) as plain text
    public function test_m1_the_api_key_is_encrypted_at_rest(): void
    {
        $this->storeKey();

        $raw = DB::table('settings')->where('key', 'onlinegroup_api_key')->value('value');
        $this->assertNotSame($this->apiKey, $raw, 'settings.value holds the gateway key in plain text');
        $this->assertStringNotContainsString($this->apiKey, (string) $raw);

        // and the setting() forever-cache (Redis in production) holds it in plain text too
        setting('onlinegroup_api_key');
        $this->assertNotSame($this->apiKey, Cache::get('setting_onlinegroup_api_key'), 'the cache holds the key in plain text');
    }

    // M2: a revoked key / missing sms:send scope stops every sign-in code, and nothing but a log line says so
    public function test_m2_a_refused_key_is_reported_not_only_logged(): void
    {
        $this->storeKey();
        Exceptions::fake();
        Http::fake([self::URL => Http::response(['detail' => "'sms:send' ruxsati yo'q"], 403)]);

        $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'RIZQIM tasdiqlash kodi: 123456'));

        // Something that reaches Sentry / ops (report(), and the sms_failures alert check), without the key.
        // (The review's `fn (\Throwable $e) => true` can never pass: the fake matches the closure's type exactly.)
        Exceptions::assertReported(fn (SmsGatewayException $e) => !str_contains($e->getMessage(), $this->apiKey));
    }

    // M3: no platform-wide send budget: the gateway allows 60 requests/min per key and ~600 segments/month per SIM,
    // but the otp-send throttle is only per IP and per phone, so 61 IPs x 61 numbers all go out in one minute
    public function test_m3_there_is_a_global_cap_on_codes_sent_per_minute(): void
    {
        $this->storeKey();
        Http::fake([self::URL => Http::response(['id' => 'x', 'status' => 'queued', 'segments' => 1], 201)]);

        for ($i = 0; $i < 61; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.' . intdiv($i, 250) . '.' . ($i % 250 + 1)])
                ->postJson('/api/v1/auth/phone/send-code', ['phone' => '+9989011' . str_pad((string) $i, 5, '0', STR_PAD_LEFT)]);
        }

        $this->assertLessThan(61, Http::recorded()->count(), 'every anonymous request reached the gateway: no global budget');
    }

    // L1: production with sms_driver=log writes every sign-in code (and full phone) to the log
    public function test_l1_production_log_driver_does_not_write_the_code(): void
    {
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();
        $this->app->detectEnvironment(fn () => 'production');

        $this->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901112233'])->assertOk();

        $this->assertDoesNotMatchRegularExpression('/kodi: \d{6}/', implode("\n", $this->logged), 'the code is in the production log');
    }

    // L2: any 2xx counts as "sent", even a body that is not the gateway's message (HTML, empty)
    public function test_l2_a_2xx_without_a_gateway_message_is_not_success(): void
    {
        $this->storeKey();
        Http::fake([self::URL => Http::response('<html><body>OK</body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertFalse(app(OnlinegroupSmsService::class)->send('+998901234567', 'RIZQIM tasdiqlash kodi: 123456'));
    }

    // L3: an explicit foreign number with nine digits becomes an Uzbek one
    public function test_l3_a_plus_prefixed_foreign_number_is_not_rewritten_to_998(): void
    {
        $this->assertNull(OnlinegroupSmsService::normalizePhone('+376 312 345'));
    }

    // L4: an http:// base URL sends the key in clear text
    public function test_l4_the_key_never_goes_over_plain_http(): void
    {
        $this->storeKey();
        config(['services.onlinegroup.base_url' => 'http://sms.onlinegroup.uz']);
        Http::fake(['*' => Http::response(['id' => 'x', 'status' => 'queued'], 201)]);

        app(OnlinegroupSmsService::class)->send('+998901234567', 'Salom');

        Http::assertNothingSent();
    }

    // L5: the Sentry scrubber misses the Uzbek "kodi: 123456" (the OTP text) -- log breadcrumbs carry it
    public function test_l5_sentry_scrubber_masks_the_otp_text(): void
    {
        $this->assertStringNotContainsString('482913', SentryScrubber::scrubString('RIZQIM tasdiqlash kodi: 482913'));
    }

    // L6: the audit redactor only knows sk_live_/sk_test_ keys; an Onlinegroup key pasted into a reason stays
    public function test_l6_audit_redactor_masks_an_onlinegroup_key_in_free_text(): void
    {
        $clean = AuditRedactor::clean(['reason' => "yangi kalit {$this->apiKey} qo'yildi"]);
        $this->assertStringNotContainsString($this->apiKey, json_encode($clean));
    }
}
