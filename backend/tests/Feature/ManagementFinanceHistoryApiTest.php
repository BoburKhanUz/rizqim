<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-12: the store's money history for the partner web panel (finance.view): ledger, wallet, payouts, online payments.
 */
class ManagementFinanceHistoryApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function seedMoney(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'click', 'total_price' => 52000]);
        $otherBranchOrder = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme', 'branch_id' => $this->otherBranch->id]);

        foreach ([['commission', '5000.00', '2026-10-19 10:00:00'], ['topup', '-100000.00', '2026-10-20 10:00:00'], ['subscription', '150000.00', '2026-10-20 20:00:00']] as $i => [$type, $amount, $at]) {
            StoreTransaction::forceCreate([
                'store_id' => $this->store->id, 'type' => $type, 'amount' => $amount, 'balance_before' => 0, 'balance_after' => $amount,
                'order_id' => $type === 'commission' ? $order->id : null, 'notes' => "row {$i}", 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
        StoreTransaction::forceCreate(['store_id' => $this->foreignStore->id, 'type' => 'commission', 'amount' => 777, 'balance_before' => 0, 'balance_after' => 777]);

        StoreWalletTransaction::create(['store_id' => $this->store->id, 'type' => 'settlement', 'amount' => 47000, 'balance_before' => 0, 'balance_after' => 47000, 'order_id' => $order->id]);
        StoreWalletTransaction::create(['store_id' => $this->store->id, 'type' => 'payout', 'amount' => -40000, 'balance_before' => 47000, 'balance_after' => 7000,
            'notes' => 'Payout completed to card: 8600123456789012']);
        StoreWalletTransaction::create(['store_id' => $this->foreignStore->id, 'type' => 'settlement', 'amount' => 888, 'balance_before' => 0, 'balance_after' => 888]);

        PayoutRequest::create(['store_id' => $this->store->id, 'amount' => 40000, 'card_number' => '8600123456789012', 'status' => 'completed']);
        PayoutRequest::create(['store_id' => $this->store->id, 'amount' => 25000.5, 'card_number' => '9860 1111 2222 3333', 'status' => 'pending', 'notes' => 'Karta 9860111122223333 ga']);
        PayoutRequest::create(['store_id' => $this->foreignStore->id, 'amount' => 999, 'card_number' => '8600000000000000', 'status' => 'pending']);

        Payment::create(['order_id' => $order->id, 'provider' => 'click', 'provider_transaction_id' => 'C-1', 'amount' => 52000, 'status' => 'paid', 'paid_at' => now(), 'payload' => ['secret' => 'raw']]);
        Payment::create(['order_id' => $otherBranchOrder->id, 'provider' => 'payme', 'provider_transaction_id' => 'P-1', 'amount' => 50000, 'status' => 'pending']);
        $foreignOrder = \App\Models\Order::create(['store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id, 'user_id' => $this->customer->id,
            'status' => OrderStatus::NEW, 'order_type' => 'delivery', 'payment_method' => 'click', 'total_price' => 999]);
        Payment::create(['order_id' => $foreignOrder->id, 'provider' => 'click', 'amount' => 999, 'status' => 'paid']);
    }

    public function test_the_owner_reads_the_ledger_and_wallet_paginated(): void
    {
        $this->seedMoney();

        $ledger = $this->as($this->owner, 'GET', '/api/v1/owner/finance/ledger?per_page=2')->assertOk()->json('data');
        $this->assertSame(3, $ledger['total']);
        $this->assertSame(2, $ledger['last_page']);
        $this->assertSame('subscription', $ledger['data'][0]['type']);
        $this->assertSame('150000.00', $ledger['data'][0]['amount']);

        $commission = $this->as($this->owner, 'GET', '/api/v1/owner/finance/ledger?type=commission')->json('data.data');
        $this->assertCount(1, $commission);
        $this->assertSame('5000.00', $commission[0]['amount']);
        $this->assertNotNull($commission[0]['order']['uuid']);

        // Days of the store's timezone: 2026-10-20 20:00 UTC is the 21st in Tashkent
        $this->assertSame(['topup'], array_column($this->as($this->owner, 'GET', '/api/v1/owner/finance/ledger?from=2026-10-20&to=2026-10-20')->json('data.data'), 'type'));

        $wallet = $this->as($this->owner, 'GET', '/api/v1/owner/finance/wallet')->assertOk()->json('data');
        $this->assertSame(2, $wallet['total']);
        $this->assertSame('-40000.00', $wallet['data'][0]['amount']);
        $this->assertSame('Payout completed to card: 8600 **** **** 9012', $wallet['data'][0]['notes']);
    }

    public function test_payouts_and_payments_never_show_card_numbers_or_raw_payloads(): void
    {
        $this->seedMoney();

        $payouts = $this->as($this->owner, 'GET', '/api/v1/owner/payouts')->assertOk()->json('data.data');
        $this->assertCount(2, $payouts);
        $this->assertSame(['9860 **** **** 3333', '8600 **** **** 9012'], array_column($payouts, 'card'));
        $this->assertSame('25000.50', $payouts[0]['amount']);
        $this->assertSame('Karta 9860 **** **** 3333 ga', $payouts[0]['notes']);
        $this->assertSame(['completed'], array_column($this->as($this->owner, 'GET', '/api/v1/owner/payouts?status=completed')->json('data.data'), 'status'));

        $response = $this->as($this->owner, 'GET', '/api/v1/owner/payments')->assertOk();
        $payments = $response->json('data.data');
        $this->assertCount(2, $payments);
        $this->assertSame(['P-1', 'C-1'], array_column($payments, 'provider_transaction_id'));
        $this->assertSame('52000.00', $payments[1]['amount']);
        $this->assertSame('52000.00', $payments[1]['order']['total_price']);
        $this->assertStringNotContainsString('raw', $response->getContent());
        $this->assertStringNotContainsString('8600123456789012', $this->as($this->owner, 'GET', '/api/v1/owner/payouts')->getContent());

        $this->assertSame(['paid'], array_column($this->as($this->owner, 'GET', '/api/v1/owner/payments?status=paid')->json('data.data'), 'status'));
        $this->assertSame(['payme'], array_column($this->as($this->owner, 'GET', "/api/v1/owner/payments?branch_id={$this->otherBranch->id}")->json('data.data'), 'provider'));
    }

    public function test_finance_view_is_the_owners_unless_granted(): void
    {
        $this->seedMoney();
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        foreach (['/api/v1/owner/finance/ledger', '/api/v1/owner/finance/wallet', '/api/v1/owner/payouts', '/api/v1/owner/payments'] as $uri) {
            foreach ([$this->manager, $cashier, $this->courier, $this->customer] as $user) {
                $this->as($user, 'GET', $uri)->assertForbidden();
            }
            $this->bearerJson('GET', $uri, $this->pinSession($this->owner))->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');
        }

        // Granted to managers: a branch manager sees their branch's payments only
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::FINANCE_VIEW, true);
        $this->assertSame(['click'], array_column($this->as($this->manager, 'GET', '/api/v1/owner/payments')->assertOk()->json('data.data'), 'provider'));
        $this->as($this->manager, 'GET', "/api/v1/owner/payments?branch_id={$this->otherBranch->id}")->assertForbidden();
        $this->as($this->manager, 'GET', '/api/v1/owner/finance/ledger')->assertOk();
    }

    public function test_another_stores_money_is_never_listed(): void
    {
        $this->seedMoney();

        $this->assertSame(1, $this->as($this->foreignOwner, 'GET', '/api/v1/owner/finance/ledger')->json('data.total'));
        $this->assertSame('777.00', $this->as($this->foreignOwner, 'GET', '/api/v1/owner/finance/ledger')->json('data.data.0.amount'));
        $this->assertSame(1, $this->as($this->foreignOwner, 'GET', '/api/v1/owner/payouts')->json('data.total'));
        $this->assertSame(1, $this->as($this->foreignOwner, 'GET', '/api/v1/owner/payments')->json('data.total'));
        $this->as($this->owner, 'GET', "/api/v1/owner/payments?branch_id={$this->foreignBranch->id}")->assertNotFound();
    }

    public function test_filters_are_validated(): void
    {
        $cases = [
            '/api/v1/owner/finance/ledger?type=gift' => 'type',
            '/api/v1/owner/finance/ledger?per_page=500' => 'per_page',
            '/api/v1/owner/finance/wallet?from=2026-13-01' => 'from',
            '/api/v1/owner/payouts?status=paid' => 'status',
            '/api/v1/owner/payouts?from=2026-10-21&to=2026-10-01' => 'to',
            '/api/v1/owner/payments?status=done' => 'status',
            '/api/v1/owner/payments?branch_id=99999999999999999999' => 'branch_id',
        ];
        foreach ($cases as $uri => $field) {
            $this->as($this->owner, 'GET', $uri)->assertStatus(422)->assertJsonValidationErrors($field);
        }
    }
}
