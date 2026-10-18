<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Filament\Superadmin\Widgets\FinanceStats;
use App\Filament\Widgets\StoreBalanceWidget;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerMoneyArithmeticTest extends TestCase
{
    use RefreshDatabase;

    private function store(array $attributes): Store
    {
        return Store::forceCreate(array_merge([
            'name' => 'Exact Cafe',
            'status' => StoreStatus::ACTIVE->value,
            'credit_limit' => -100000,
            'commission_rate' => 10,
        ], $attributes));
    }

    public function test_top_up_leaving_the_debt_exactly_at_the_credit_limit_keeps_the_store_blocked(): void
    {
        // As floats 131072.02 - 31072.02 is 99999.999999999985, which passed for "under the limit" and unblocked it
        $store = $this->store(['status' => StoreStatus::PAYMENT_REQUIRED->value, 'debt_balance' => '131072.02']);

        app(LedgerService::class)->reduceDebt($store, '31072.02', 'Naqd to\'lov');

        $store->refresh();
        $this->assertSame('100000.00', $store->debt_balance);
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $store->status);
        $this->assertTrue($store->is_order_locked);
        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $store->id,
            'amount' => '-31072.02',
            'balance_before' => '131072.02',
            'balance_after' => '100000.00',
        ]);
    }

    public function test_owner_panel_shows_what_the_payout_form_allows(): void
    {
        $store = $this->store(['wallet_balance' => 300000, 'min_payout_reserve' => 100000]);
        $owner = User::create([
            'name' => 'Owner',
            'phone' => '+998901230001',
            'role' => UserRole::OWNER->value,
            'store_id' => $store->id,
            'password' => bcrypt('password'),
        ]);
        PayoutRequest::create(['store_id' => $store->id, 'amount' => 150000, 'card_number' => '8600123456789012', 'status' => 'pending']);

        $this->actingAs($owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($store->fresh());

        // 300k wallet - 150k already requested - 100k reserve; the pending request used to be ignored (200k)
        Livewire::test(StoreBalanceWidget::class)
            ->assertSee('50,000 UZS')
            ->assertDontSee('200,000 UZS');
    }

    public function test_superadmin_finance_stats_sum_what_stores_owe_not_their_prepayments(): void
    {
        $this->store(['name' => 'Indebted Cafe', 'debt_balance' => 50000]);
        $this->store(['name' => 'Prepaid Cafe', 'debt_balance' => -20000]);
        $admin = User::create([
            'name' => 'Super Admin',
            'phone' => '+998901230002',
            'role' => UserRole::SUPER_ADMIN->value,
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('superadmin');

        Livewire::test(FinanceStats::class)
            ->assertSee('50 000 UZS')
            ->assertDontSee('20 000 UZS');
    }
}
