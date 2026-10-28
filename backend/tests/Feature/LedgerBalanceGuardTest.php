<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerBalanceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_balances_are_not_mass_assignable_but_admin_settings_are(): void
    {
        $store = Store::create([
            'name' => 'Guarded Cafe',
            'debt_balance' => -999999,
            'wallet_balance' => 999999,
            'credit_limit' => -50000,
            'commission_rate' => 7,
        ]);

        $store->update(['debt_balance' => -1, 'wallet_balance' => 1, 'min_payout_reserve' => 20000]);
        $store = $store->fresh();

        $this->assertEquals(0, (float) $store->debt_balance);
        $this->assertEquals(0, (float) $store->wallet_balance);
        $this->assertEquals(-50000, (float) $store->credit_limit);
        $this->assertEquals(7, (float) $store->commission_rate);
        $this->assertEquals(20000, (float) $store->min_payout_reserve);
    }
}
