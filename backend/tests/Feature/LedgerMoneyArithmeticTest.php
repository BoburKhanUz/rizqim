<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_top_up_leaving_the_debt_exactly_at_the_credit_limit_unblocks_the_store(): void
    {
        // A debt equal to the limit is allowed. As floats 131072.01 - 31072.01 is 100000.00000000001, which would
        // count as over the limit and keep the store blocked
        $store = $this->store(['status' => StoreStatus::PAYMENT_REQUIRED->value, 'debt_balance' => '131072.01']);

        app(LedgerService::class)->reduceDebt($store, '31072.01', 'Naqd to\'lov');

        $store->refresh();
        $this->assertSame('100000.00', $store->debt_balance);
        $this->assertSame(StoreStatus::ACTIVE, $store->status);
        $this->assertFalse($store->is_order_locked);
        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $store->id,
            'amount' => '-31072.01',
            'balance_before' => '131072.01',
            'balance_after' => '100000.00',
        ]);

        // One tiyin over the limit keeps it blocked
        $over = $this->store(['status' => StoreStatus::PAYMENT_REQUIRED->value, 'debt_balance' => '131072.03']);
        app(LedgerService::class)->reduceDebt($over, '31072.02', 'Naqd to\'lov');
        $this->assertSame('100000.01', $over->fresh()->debt_balance);
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $over->fresh()->status);
    }
}
