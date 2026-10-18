<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\ListGlobalStores;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerBalanceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_balances_are_not_mass_assignable_but_admin_settings_are(): void
    {
        $store = Store::create([
            'name' => 'Guarded Cafe',
            'balance' => -999999,
            'wallet_balance' => 999999,
            'credit_limit' => -50000,
            'commission_rate' => 7,
        ]);

        $store->update(['balance' => -1, 'wallet_balance' => 1, 'min_payout_reserve' => 20000]);
        $store = $store->fresh();

        $this->assertEquals(0, (float) $store->balance);
        $this->assertEquals(0, (float) $store->wallet_balance);
        $this->assertEquals(-50000, (float) $store->credit_limit);
        $this->assertEquals(7, (float) $store->commission_rate);
        $this->assertEquals(20000, (float) $store->min_payout_reserve);
    }

    public function test_superadmin_manual_topup_goes_through_the_ledger_and_unblocks_under_the_credit_limit(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'phone' => '+998907770001',
            'role' => UserRole::SUPER_ADMIN->value,
            'password' => bcrypt('password'),
        ]);
        $store = Store::forceCreate([
            'name' => 'Blocked Cafe',
            'status' => StoreStatus::PAYMENT_REQUIRED->value,
            'balance' => 120000,
            'credit_limit' => -100000,
        ]);

        Filament::setCurrentPanel('superadmin');
        $this->actingAs($admin);

        Livewire::test(ListGlobalStores::class)
            ->callTableAction('top_up', $store, data: ['amount' => 30000, 'notes' => 'Naqd to\'lov'])
            ->assertHasNoTableActionErrors();

        $store = $store->fresh();
        $this->assertEquals(90000, (float) $store->balance);
        $this->assertSame(StoreStatus::ACTIVE, $store->status);
        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $store->id,
            'type' => StoreTransactionType::TOPUP->value,
            'amount' => -30000,
            'balance_after' => 90000,
            'performed_by' => $admin->id,
            'notes' => 'Naqd to\'lov',
        ]);
        $this->assertSame(1, StoreTransaction::where('store_id', $store->id)->count());
    }
}
