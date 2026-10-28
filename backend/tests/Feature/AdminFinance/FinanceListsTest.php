<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\StoreTopupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-23: Moliya's lists — balance top-ups (Click, Payme; stuck ones), settlement failures with a retry through the
 * existing recovery path, store balances and debts (over the credit limit: commission debt only) and a store's
 * ledger with the netting rows naming what they closed.
 */
class FinanceListsTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    public function test_topups_are_listed_with_their_ledger_row_and_stuck_ones_are_flagged(): void
    {
        $store = $this->v2Store('Toldirish');
        $old = StoreTopupRequest::create(['store_id' => $store->id, 'amount' => '50000.00', 'provider' => 'click', 'status' => 'pending']);
        $old->forceFill(['created_at' => now()->subHours(2)])->save();
        $fresh = StoreTopupRequest::create(['store_id' => $store->id, 'amount' => '20000.00', 'provider' => 'payme', 'status' => 'pending']);
        $paid = StoreTopupRequest::create(['store_id' => $store->id, 'amount' => '30000.00', 'provider' => 'payme', 'status' => 'paid', 'paid_at' => now()]);
        $row = $this->ledger()->creditTopup($paid, 'Payme');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));

        $all = $this->financeJson('GET', '/finance/topups', $token)->assertOk()->json('data');
        $this->assertSame([$paid->id, $fresh->id, $old->id], array_column($all['data'], 'id'));
        $this->assertSame(['pending' => 2, 'stuck' => 1], $all['counts']);
        $this->assertSame($row->id, $all['data'][0]['transaction_id']);
        $this->assertSame('30000.00', $all['data'][0]['amount']);
        $this->assertSame([false, false, true], array_column($all['data'], 'stuck'));

        $this->assertSame([$old->id], array_column($this->financeJson('GET', '/finance/topups?stuck=1', $token)->json('data.data'), 'id'));
        $this->assertSame([$paid->id, $fresh->id], array_column($this->financeJson('GET', '/finance/topups?provider=payme', $token)->json('data.data'), 'id'));
        $this->assertSame(1, $this->financeJson('GET', '/finance/summary', $token)->json('data.topups.stuck'));
    }

    public function test_a_failed_settlement_is_listed_and_retried_through_the_recovery_path(): void
    {
        $store = $this->v2Store('Xato');
        $order = $this->financeOrder($store, ['commission_amount' => 5000]);
        $order->forceFill(['settlement_failed_at' => now()->subHour(), 'settlement_error' => 'QueryException: lock timeout', 'settlement_attempts' => 12])->save();
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($finance);

        $list = $this->financeJson('GET', '/finance/settlement-failures', $token)->assertOk()->json('data');
        $this->assertSame(1, $list['count']);
        $this->assertSame((string) $order->uuid, $list['data'][0]['uuid']);
        $this->assertTrue($list['data'][0]['gave_up']);
        $this->assertSame('QueryException: lock timeout', $list['data'][0]['error']);

        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $this->financeJson('POST', "/finance/settlement-failures/{$order->uuid}/retry", $viewer, ['reason' => 'Qayta'])->assertForbidden();
        $this->financeJson('POST', "/finance/settlement-failures/{$order->uuid}/retry", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->financeJson('POST', "/finance/settlement-failures/{$order->uuid}/retry", $token, ['reason' => 'Sabab tuzatildi'])
            ->assertOk()->assertJsonPath('data.booked', true)->assertJsonPath('data.order', null);
        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame('5000.00', $store->fresh()->debt_balance);
        $entry = AuditLog::where('action', 'admin.finance.settlement_failures.retried')->sole();
        $this->assertSame($finance->id, (int) $entry->user_id);
        $this->assertTrue($entry->payload_json['booked']);

        $this->financeJson('POST', "/finance/settlement-failures/{$order->uuid}/retry", $token, ['reason' => 'Yana'])->assertNotFound();
        $this->assertSame('5000.00', $store->fresh()->debt_balance);
    }

    public function test_balances_and_the_over_limit_list_count_only_the_commission_debt(): void
    {
        $over = $this->v2Store('Oshgan', ['credit_limit' => -10000]);
        $over->forceFill(['debt_balance' => '20000.00'])->save();
        $invoicesOnly = $this->v2Store('Obuna qarzi', ['credit_limit' => -10000]);
        $invoicesOnly->forceFill(['debt_balance' => '149000.00', 'subscription_debt' => '149000.00'])->save();
        $wallet = $this->v2Store('Hamyonli');
        $wallet->forceFill(['wallet_balance' => '50000.00'])->save();
        $this->payout($wallet, '20000.00');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $all = $this->financeJson('GET', '/finance/balances', $token)->assertOk()->json('data');
        $this->assertSame(['over_limit' => 1, 'wallet_total' => '50000.00', 'debt_total' => '169000.00', 'subscription_debt_total' => '149000.00', 'commission_debt_total' => '20000.00'], $all['totals']);

        $overLimit = $this->financeJson('GET', '/finance/balances?filter=over_limit', $token)->json('data.data');
        $this->assertSame([$over->id], array_map(fn (array $row) => $row['store']['id'], $overLimit));
        $this->assertTrue($overLimit[0]['over_limit']);
        $this->assertTrue($overLimit[0]['is_order_locked']);

        $subscription = $this->financeJson('GET', '/finance/balances?filter=subscription_debt', $token)->json('data.data.0');
        $this->assertSame(['149000.00', '0.00', false], [$subscription['subscription_debt'], $subscription['commission_debt'], $subscription['over_limit']]);

        $walletRow = $this->financeJson('GET', '/finance/balances?filter=wallet', $token)->json('data.data.0');
        $this->assertSame(['50000.00', '20000.00'], [$walletRow['wallet_balance'], $walletRow['pending_payouts']]);
        $this->assertSame(1, $this->financeJson('GET', '/finance/balances?q=hamyon', $token)->json('data.total'));
    }

    public function test_a_stores_ledger_shows_what_each_netting_row_closed(): void
    {
        $store = $this->storeWithTwoInvoices('Daftar');
        $order = $this->platformOrder($store, 'click', '160000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september, $october] = $this->invoicesOf($store);
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $debt = $this->financeJson('GET', "/finance/stores/{$store->id}/ledger", $token)->assertOk()->json('data.data');
        $payoffs = array_values(array_filter($debt, fn (array $row) => $row['type'] === 'topup'));
        $this->assertSame([$october->number, $september->number], array_map(fn (array $row) => $row['subscription_invoice']['number'], $payoffs));
        $this->assertSame((string) $order->uuid, $payoffs[0]['order']['uuid']);

        $wallet = $this->financeJson('GET', "/finance/stores/{$store->id}/ledger?ledger=wallet", $token)->json('data.data');
        // All 160 000 went to the invoices: nothing reached the wallet
        $this->assertSame([], $wallet);
        $this->financeJson('GET', '/finance/stores/999999/ledger', $token)->assertNotFound();
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
    }
}
