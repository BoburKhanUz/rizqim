<?php

namespace Tests\Feature\Sms;

use App\Models\Alert;
use App\Models\Setting;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\System\SystemSettings;
use App\Services\Sms\OnlinegroupSmsService;
use App\Services\Sms\SmsFailures;
use App\Services\Sms\SmsGatewayException;
use App\Services\Sms\SmsServiceInterface;
use App\Support\SentryScrubber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * Review 17 follow-up of the SMS drivers: the control centre's secrets are encrypted at rest (and every reader still
 * works), a broken gateway is reported and raises the `sms_failures` alert, the sign-in codes have a platform-wide
 * budget and a gateway 429 answers like our own throttle, the `log` driver in production keeps the text out of the
 * log, and booking / link messages live longer in the gateway's queue than a code.
 */
class SmsSecretsAndLimitsTest extends TestCase
{
    use MonitoringFixtures;
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
            'settings.eskiz_password' => null,
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

    private function queued(): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['id' => '9c1e4d77-8f3a-4b10-bb2f-6d0c9e2a7f43', 'status' => 'queued', 'segments' => 1], 201);
    }

    // M1

    public function test_the_eskiz_password_is_encrypted_at_rest_and_eskiz_still_signs_in_with_it(): void
    {
        Setting::where('key', 'sms_driver')->update(['value' => 'eskiz']);
        Setting::create(['key' => 'eskiz_email', 'value' => 'a@b.uz']);
        app(SystemSettings::class)->replaceSecret('eskiz_password', 'eskiz-pass-9876');
        Cache::flush();

        $raw = (string) DB::table('settings')->where('key', 'eskiz_password')->value('value');
        $this->assertStringNotContainsString('eskiz-pass-9876', $raw);
        $this->assertSame('eskiz-pass-9876', Crypt::decryptString($raw));

        Http::fake([
            'notify.eskiz.uz/api/auth/login' => Http::response(['data' => ['token' => 't']]),
            'notify.eskiz.uz/api/message/sms/send' => Http::response(['status' => 'waiting']),
        ]);
        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901234567', 'x'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'auth/login') && $r['password'] === 'eskiz-pass-9876');
        $this->assertStringNotContainsString('eskiz-pass-9876', (string) json_encode(Cache::get('setting_eskiz_password')));
    }

    public function test_the_encrypted_key_reaches_the_gateway_and_the_settings_screen_shows_its_last_four(): void
    {
        $this->storeKey();
        Http::fake([self::URL => $this->queued()]);

        $this->assertTrue(app(OnlinegroupSmsService::class)->send('+998901234567', 'Salom'));
        Http::assertSent(fn (Request $r) => $r->header('X-API-Key')[0] === $this->apiKey);

        $items = collect(app(SystemSettings::class)->groups())->flatMap(fn (array $group) => $group['items'])->keyBy('key');
        $this->assertSame(substr($this->apiKey, -4), $items['onlinegroup_api_key']['last4']);
        $this->assertTrue($items['onlinegroup_api_key']['configured']);
        $this->assertNull($items['onlinegroup_api_key']['value']);
    }

    public function test_a_secret_from_env_is_read_but_never_cached_in_plain_text(): void
    {
        config(['settings.onlinegroup_api_key' => $this->apiKey]);

        $this->assertSame($this->apiKey, setting('onlinegroup_api_key'));
        $this->assertStringNotContainsString($this->apiKey, (string) json_encode(Cache::get('setting_onlinegroup_api_key')));
    }

    public function test_the_migration_encrypts_existing_secrets_idempotently_and_reverses(): void
    {
        DB::table('settings')->insert([
            ['key' => 'onlinegroup_api_key', 'value' => $this->apiKey, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'eskiz_password', 'value' => 'old-pass', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'eskiz_email', 'value' => 'a@b.uz', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Cache::forever('setting_onlinegroup_api_key', $this->apiKey);
        $migration = require database_path('migrations/2026_12_06_100000_encrypt_secret_settings.php');
        $raw = fn (string $key) => DB::table('settings')->where('key', $key)->value('value');

        $migration->up();
        $first = $raw('onlinegroup_api_key');
        $this->assertSame($this->apiKey, Crypt::decryptString($first));
        $this->assertSame('old-pass', Crypt::decryptString($raw('eskiz_password')));
        $this->assertSame('a@b.uz', $raw('eskiz_email'));
        $this->assertNull(Cache::get('setting_onlinegroup_api_key'));
        $this->assertSame($this->apiKey, setting('onlinegroup_api_key'));

        $migration->up();
        $this->assertSame($first, $raw('onlinegroup_api_key'), 'an encrypted value is not encrypted twice');

        $migration->down();
        $this->assertSame($this->apiKey, $raw('onlinegroup_api_key'));
        $this->assertSame('old-pass', $raw('eskiz_password'));
        $migration->down();
        $this->assertSame([$this->apiKey, 'old-pass'], [$raw('onlinegroup_api_key'), $raw('eskiz_password')], 'a plain value stays plain');
    }

    // M2

    public function test_a_missing_key_is_reported_and_a_refused_key_raises_the_sms_failures_alert(): void
    {
        Exceptions::fake();
        Http::fake([self::URL => Http::response(['detail' => 'API kalit yaroqsiz'], 401)]);

        $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'RIZQIM tasdiqlash kodi: 123456'));
        Exceptions::assertReported(fn (SmsGatewayException $e) => str_contains($e->getMessage(), 'onlinegroup_api_key'));
        Http::assertNothingSent();

        $this->storeKey();
        $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'RIZQIM tasdiqlash kodi: 123456'));
        Exceptions::assertReported(fn (SmsGatewayException $e) => str_contains($e->getMessage(), '401') && !str_contains($e->getMessage(), $this->apiKey) && !str_contains($e->getMessage(), '123456'));

        $this->onlyRule(AlertKind::SMS_FAILURES);
        app(AlertEvaluator::class)->run();
        $alerts = Alert::query()->where('kind', AlertKind::SMS_FAILURES->value)->unresolved()->get()->keyBy('subject_key');
        $this->assertSame(['config'], $alerts->keys()->all());
        $this->assertSame('critical', $alerts['config']->severity);
        $this->assertStringNotContainsString($this->apiKey, (string) json_encode($alerts->toArray()));

        // The window passes with no failure: the alert resolves
        $this->travel(2)->hours();
        app(AlertEvaluator::class)->run();
        $this->assertSame(0, Alert::query()->where('kind', AlertKind::SMS_FAILURES->value)->unresolved()->count());
    }

    public function test_transient_failures_alert_only_from_the_threshold(): void
    {
        $this->storeKey();
        Http::fake([self::URL => Http::response(['detail' => 'x'], 503)]);
        $rule = $this->onlyRule(AlertKind::SMS_FAILURES);
        $this->assertSame([3, 15], [$rule->threshold, $rule->window_minutes]);

        app(SmsServiceInterface::class)->send('+998901234567', 'Salom');
        app(SmsServiceInterface::class)->send('+998901234567', 'Salom');
        app(AlertEvaluator::class)->run();
        $this->assertSame(0, Alert::query()->where('kind', AlertKind::SMS_FAILURES->value)->count());

        app(SmsServiceInterface::class)->send('+998901234567', 'Salom');
        app(AlertEvaluator::class)->run();
        $alert = Alert::query()->where('kind', AlertKind::SMS_FAILURES->value)->sole();
        $this->assertSame(['delivery', 'warning'], [$alert->subject_key, $alert->severity]);
        $this->assertSame(3, app(SmsFailures::class)->counts(15)['unavailable'] ?? null);
    }

    // M3

    public function test_the_daily_platform_cap_is_configurable(): void
    {
        $this->storeKey();
        config(['auth.phone_verification.global_per_minute' => 100, 'auth.phone_verification.global_per_day' => 3]);
        Http::fake([self::URL => $this->queued()]);

        $statuses = [];
        for ($i = 0; $i < 4; $i++) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.' . ($i + 1)])
                ->postJson('/api/v1/auth/phone/send-code', ['phone' => '+99890111220' . $i])->status();
        }

        $this->assertSame([200, 200, 200, 429], $statuses);
        Http::assertSentCount(3);
    }

    public function test_a_gateway_429_answers_like_our_own_throttle(): void
    {
        $this->storeKey();
        Http::fake([self::URL => Http::response(['detail' => "Bu raqamga soatiga 5 tadan ko'p kod yuborib bo'lmaydi"], 429)]);

        $response = $this->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901112233']);
        $response->assertStatus(429)->assertJsonPath('success', false)->assertHeader('Retry-After');
        $this->assertStringStartsWith("Juda ko'p urinish", (string) $response->json('message'));
        Http::assertSentCount(1);
    }

    // L1

    public function test_the_log_driver_in_production_logs_the_masked_phone_only_and_raises_the_alert(): void
    {
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901112233', 'RIZQIM tasdiqlash kodi: 654321'));

        $all = implode("\n", $this->logged);
        $this->assertStringNotContainsString('654321', $all);
        $this->assertStringNotContainsString('tasdiqlash', $all);
        $this->assertStringNotContainsString('901112233', $all);
        $this->assertStringContainsString('+998 90 *** ** 33', $all);

        $this->onlyRule(AlertKind::SMS_FAILURES);
        app(AlertEvaluator::class)->run();
        $alert = Alert::query()->where('kind', AlertKind::SMS_FAILURES->value)->sole();
        $this->assertSame(['disabled', 'critical'], [$alert->subject_key, $alert->severity]);
    }

    public function test_the_log_driver_in_testing_keeps_the_full_line(): void
    {
        Setting::where('key', 'sms_driver')->update(['value' => 'log']);
        Cache::flush();

        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901112233', 'Salom 777'));
        $this->assertStringContainsString('+998901112233: Salom 777', implode("\n", $this->logged));
        $this->assertSame([], app(SmsFailures::class)->counts(15));
    }

    // L2

    public function test_only_200_or_201_with_an_id_and_a_known_status_is_success(): void
    {
        $this->storeKey();
        $cases = [
            [201, ['id' => 'a', 'status' => 'queued'], true],
            [200, ['id' => 'a', 'status' => 'sent'], true],
            [202, ['id' => 'a', 'status' => 'queued'], false],
            [201, ['status' => 'queued'], false],
            [201, ['id' => 'a', 'status' => 'whatever'], false],
            [201, ['id' => 'a', 'status' => 'failed'], false],
            [204, null, false],
        ];
        foreach ($cases as [$status, $body, $expected]) {
            $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
            Http::clearResolvedInstances();
            Http::fake([self::URL => Http::response($body, $status)]);
            $this->assertSame($expected, app(OnlinegroupSmsService::class)->send('+998901234567', 'Salom'), json_encode([$status, $body]));
            Http::assertSentCount(1);
        }
    }

    // L3

    public function test_a_plus_number_must_be_uzbek(): void
    {
        $this->assertSame('+998901234567', OnlinegroupSmsService::normalizePhone('+998 90 123 45 67'));
        $this->assertSame('+998901234567', OnlinegroupSmsService::normalizePhone(' 90 123 45 67'));
        $this->assertNull(OnlinegroupSmsService::normalizePhone('+8998901234567'));
        $this->assertNull(OnlinegroupSmsService::normalizePhone('+901234567'));
    }

    // L4

    public function test_plain_http_is_only_for_a_local_gateway_in_development(): void
    {
        $this->storeKey();
        Http::fake(['*' => Http::response(['id' => 'x', 'status' => 'queued'], 201)]);

        config(['services.onlinegroup.base_url' => 'http://127.0.0.1:8099']);
        $this->assertTrue(app(OnlinegroupSmsService::class)->send('+998901234567', 'Salom'));
        Http::assertSentCount(1);

        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse(app(OnlinegroupSmsService::class)->send('+998901234567', 'Salom'));
        Http::assertSentCount(1);
    }

    // L5 (other Uzbek forms)

    public function test_sentry_scrubber_masks_uzbek_code_forms(): void
    {
        foreach (['Tasdiqlash kodingiz: 482913', 'kodni 482913 kiriting', 'Kodi 482913', 'parol: 482913', 'Your code is 482913'] as $text) {
            $this->assertStringNotContainsString('482913', SentryScrubber::scrubString($text), $text);
        }
    }

    // Notes: ttl

    public function test_codes_live_300_seconds_in_the_queue_and_other_messages_an_hour(): void
    {
        $this->storeKey();
        Http::fake([self::URL => $this->queued()]);

        $this->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901112233'])->assertOk();
        app(SmsServiceInterface::class)->send('+998901112233', 'RIZQIM: bron tasdiqlandi');

        $ttls = [];
        Http::assertSent(function (Request $request) use (&$ttls) {
            $ttls[] = $request['ttl_seconds'];

            return true;
        });
        $this->assertSame([300, 3600], $ttls);

        config(['services.onlinegroup.message_ttl_seconds' => 7200]);
        app(SmsServiceInterface::class)->send('+998901112233', 'RIZQIM: bron tasdiqlandi');
        Http::assertSent(fn (Request $request) => $request['ttl_seconds'] === 7200);
    }
}
