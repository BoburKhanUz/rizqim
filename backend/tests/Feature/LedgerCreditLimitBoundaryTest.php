<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Models\StoreTopupRequest;
use App\Services\Finance\LedgerService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * credit_limit is the most a store may owe the platform: a debt equal to it is allowed, only a debt over it refuses
 * orders and blocks the store, and the same boundary unblocks it. A limit of 0 used to block every store for good.
 */
class LedgerCreditLimitBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function withFinances(string $status, string|int $debt, string|int $creditLimit): void
    {
        $this->store->forceFill(['status' => $status, 'debt_balance' => $debt, 'credit_limit' => $creditLimit])->save();
        $this->store->refresh();
    }

    private function canOrder(): bool
    {
        try {
            $this->checkout($this->customer, 1);

            return true;
        } catch (Exception $e) {
            $this->assertStringContainsString('qarz limitidan oshgan', $e->getMessage());

            return false;
        }
    }

    public function test_a_zero_limit_lets_a_debt_free_store_sell_and_any_debt_stops_it(): void
    {
        $this->withFinances(StoreStatus::ACTIVE->value, 0, 0);
        $this->assertFalse($this->store->is_order_locked);
        $this->assertTrue($this->canOrder());

        $this->withFinances(StoreStatus::ACTIVE->value, 1, 0);
        $this->assertTrue($this->store->is_order_locked);
        $this->assertFalse($this->canOrder());
    }

    public function test_a_debt_equal_to_the_limit_is_allowed_and_one_tiyin_more_is_not(): void
    {
        $this->withFinances(StoreStatus::ACTIVE->value, '100000.00', -100000);
        $this->assertFalse($this->store->is_order_locked);
        $this->assertTrue($this->canOrder());

        $this->withFinances(StoreStatus::ACTIVE->value, '100000.01', -100000);
        $this->assertTrue($this->store->is_order_locked);
        $this->assertFalse($this->canOrder());
    }

    public function test_commission_blocks_only_past_the_limit(): void
    {
        $this->withFinances(StoreStatus::ACTIVE->value, 90000, -100000);
        $ledger = app(LedgerService::class);

        // 90 000 + 10 000 commission: exactly the limit, still selling
        $ledger->processOrderDelivered($this->makeOrder(OrderStatus::DELIVERED, ['commission_amount' => 10000]));
        $this->assertEquals(100000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(StoreStatus::ACTIVE, $this->store->fresh()->status);

        $ledger->processOrderDelivered($this->makeOrder(OrderStatus::DELIVERED, ['commission_amount' => '0.01']));
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $this->store->fresh()->status);
    }

    public function test_paying_down_unblocks_on_the_same_boundary(): void
    {
        $ledger = app(LedgerService::class);
        $this->withFinances(StoreStatus::PAYMENT_REQUIRED->value, 130000, -100000);

        $ledger->reduceDebt($this->store, '29999.99', "Naqd to'lov");
        $this->assertEquals('100000.01', $this->store->fresh()->debt_balance);
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $this->store->fresh()->status);

        $ledger->reduceDebt($this->store, '0.01', "Naqd to'lov");
        $this->assertSame(StoreStatus::ACTIVE, $this->store->fresh()->status);
        $this->assertTrue($this->canOrder());

        // With a limit of 0, paying the whole debt off unblocks the store (0 >= 0 used to keep it blocked forever)
        $this->withFinances(StoreStatus::PAYMENT_REQUIRED->value, 5000, 0);
        $ledger->creditTopup(StoreTopupRequest::create([
            'store_id' => $this->store->id,
            'amount' => 5000,
            'provider' => 'payme',
            'status' => 'paid',
        ]), 'Payme');

        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(StoreStatus::ACTIVE, $this->store->fresh()->status);
        $this->assertTrue($this->canOrder());
    }
}
