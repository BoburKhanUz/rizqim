<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The partner web panel (partner/web) signs people in through its own Next server, so every request reaches the backend
 * from the partner-web container. The panel forwards the client address as X-Forwarded-For; the backend must take it
 * from the compose network, so the per-IP limits stay per person and not one bucket for every partner.
 */
class PartnerWebClientIpTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER_WEB = '172.18.0.7';

    private function viaPartnerWeb(string $clientIp): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PARTNER_WEB, 'HTTP_X_FORWARDED_FOR' => $clientIp]);
    }

    public function test_two_partners_signing_in_through_the_panel_get_their_own_sms_limit(): void
    {
        $this->viaPartnerWeb('203.0.113.10')->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901111111'])->assertSuccessful();
        $this->viaPartnerWeb('203.0.113.11')->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998902222222'])->assertSuccessful();

        // The same person asking for a second phone's code within the minute is still held back
        $this->viaPartnerWeb('203.0.113.10')->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998903333333'])->assertStatus(429);
    }

    public function test_the_refresh_limit_of_one_client_does_not_lock_out_another(): void
    {
        $refresh = fn (string $ip, int $i) => $this->viaPartnerWeb($ip)->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'junk' . $i,
            'device_uuid' => '9b2f7a4e-1d3c-4b5a-8e6f-0a1b2c3d4e5f',
        ]);

        for ($i = 0; $i < 30; $i++) {
            $this->assertNotSame(429, $refresh('198.51.100.7', $i)->status());
        }
        $this->assertSame(429, $refresh('198.51.100.7', 30)->status());
        $this->assertNotSame(429, $refresh('198.51.100.8', 31)->status());
    }
}
