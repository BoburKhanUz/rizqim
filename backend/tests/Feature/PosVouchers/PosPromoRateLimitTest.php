<?php

namespace Tests\Feature\PosVouchers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review S6 (P6 wave 4): typing promo codes at the till is limited to 10 a minute per staff member and per terminal
 * (no guessing codes at the API's 300 a minute), with a plain 429 `promo_rate_limited`.
 */
class PosPromoRateLimitTest extends TestCase
{
    use PosVouchersFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpVouchers();
    }

    public function test_ten_codes_a_minute_per_staff_member_and_per_terminal(): void
    {
        $order = $this->menuOrder('50000');
        for ($i = 0; $i < 10; $i++) {
            $this->pos('POST', "/orders/{$order->uuid}/promo", ['version' => (int) $order->version, 'code' => "TAXMIN{$i}"])
                ->assertNotFound()->assertJsonPath('data.reason', 'promo_not_found');
        }
        $this->pos('POST', "/orders/{$order->uuid}/promo", ['version' => (int) $order->version, 'code' => 'TAXMIN10'])
            ->assertStatus(429)
            ->assertJsonPath('data.reason', 'promo_rate_limited')
            ->assertJsonPath('message', "Promo-kod juda ko'p marta kiritildi. Bir daqiqadan so'ng qayta urinib ko'ring.");

        // Another person on the same terminal: the terminal's limit
        $managerToken = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);
        $this->pos('POST', "/orders/{$order->uuid}/promo", ['version' => (int) $order->version, 'code' => 'TAXMIN11'], $managerToken)->assertStatus(429);

        // The same person on another terminal: their own limit
        $other = $this->pairTerminal($this->owner, $this->branch);
        $this->pos('POST', "/orders/{$order->uuid}/promo", ['version' => (int) $order->version, 'code' => 'TAXMIN12'], $this->pinSession($other, $this->cashier))->assertStatus(429);

        // Other till routes are not touched; a minute later codes are taken again
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $this->pos('GET', '/vouchers/ACDE-FGHJ-KMNP')->assertNotFound();
        $this->travel(61)->seconds();
        $this->pos('POST', "/orders/{$order->uuid}/promo", ['version' => (int) $order->version, 'code' => 'TAXMIN13'])->assertNotFound();
    }
}
