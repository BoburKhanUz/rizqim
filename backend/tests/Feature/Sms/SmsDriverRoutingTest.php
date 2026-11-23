<?php

namespace Tests\Feature\Sms;

use App\Models\Setting;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Sanity checks that hold after review 17 (eskiz still routed, flag right per driver, unknown driver fails closed). */
class SmsDriverRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_eskiz_still_sends_and_flags_verification(): void
    {
        config(['settings.sms_driver' => null]);
        foreach (['sms_driver' => 'eskiz', 'eskiz_email' => 'a@b.uz', 'eskiz_password' => 'pw'] as $k => $v) {
            Setting::create(['key' => $k, 'value' => $v]);
        }
        Cache::flush();
        Http::fake([
            'notify.eskiz.uz/api/auth/login' => Http::response(['data' => ['token' => 't']]),
            'notify.eskiz.uz/api/message/sms/send' => Http::response(['status' => 'waiting']),
            '*' => Http::response([], 500),
        ]);
        $this->assertTrue(app(SmsServiceInterface::class)->send('+998901234567', 'x'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sms/send') && $r['mobile_phone'] === '998901234567');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'onlinegroup'));
        $this->assertTrue(SmsDriver::verificationRequired());
    }

    public function test_unknown_driver_fails_closed_and_log_needs_no_verification(): void
    {
        Http::fake();
        config(['settings.sms_driver' => 'bogus']);
        Cache::flush();
        $this->assertFalse(app(SmsServiceInterface::class)->send('+998901234567', 'x'));
        $this->assertFalse(SmsDriver::verificationRequired());
        Http::assertNothingSent();
    }
}
