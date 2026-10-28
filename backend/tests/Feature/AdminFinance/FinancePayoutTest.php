<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-23: the payout queue (Moliya → Payoutlar). Approving nets the store's debt first — the oldest invoices, then the
 * commission debt — and transfers the rest ("Hisoblangan — Ushlangan qarz — O'tkaziladi"); 1 000 000 so'm or more
 * needs two different members; never paid twice; payouts.approve + step-up + reason; audited.
 */
class FinancePayoutTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    private function withWallet(Store $store, string $wallet): Store
    {
        $store->forceFill(['wallet_balance' => $wallet])->save();

        return $store->refresh();
    }

    private function finance(): string
    {
        return $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
    }

    public function test_a_payout_approver_sees_the_card_to_transfer_to_while_the_payout_is_open(): void
    {
        $store = $this->withWallet($this->storeWithTwoInvoices(), '500000.00');
        $payout = $this->payout($store, '400000.00', '8600123456789012');

        // Finance has payouts.approve but not pii.view: the open payout's card is shown in full and the reveal audited
        $card = $this->financeJson('GET', "/finance/payouts/{$payout->id}", $this->finance())->assertOk()->json('data');
        $this->assertSame('8600123456789012', $card['card']);
        $this->assertTrue(AuditLog::where('action', 'admin.pii.revealed')->exists());

        // A viewer (no payouts.approve, no pii.view) sees it masked
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $masked = $this->financeJson('GET', "/finance/payouts/{$payout->id}", $viewer)->assertOk()->json('data');
        $this->assertStringNotContainsString('123456789', $masked['card']);

        // Once the payout is decided the approver has no reason to see it any more
        $payout->forceFill(['status' => 'rejected'])->save();
        $closed = $this->financeJson('GET', "/finance/payouts/{$payout->id}", $this->finance())->assertOk()->json('data');
        $this->assertStringNotContainsString('123456789', $closed['card']);
    }

    public function test_a_payout_nets_the_invoices_first_and_transfers_the_rest(): void
    {
        $store = $this->withWallet($this->storeWithTwoInvoices(), '500000.00');
        $payout = $this->payout($store, '400000.00');
        $token = $this->finance();

        $card = $this->financeJson('GET', "/finance/payouts/{$payout->id}", $token)->assertOk()->json('data');
        $this->assertSame('pending', $card['status']);
        $this->assertFalse($card['needs_second_approval']);
        $this->assertTrue($card['actions']['can_approve']);
        $this->assertTrue($card['breakdown']['preview']);
        $this->assertSame(['400000.00', '298000.00', '102000.00'], [$card['breakdown']['accrued'], $card['breakdown']['netted'], $card['breakdown']['transfer']]);
        $this->assertSame(['invoice', 'invoice'], array_column($card['breakdown']['lines'], 'kind'));
        $this->assertSame(['298000.00', '298000.00', '0.00'], [$card['balances']['debt'], $card['balances']['subscription_debt'], $card['balances']['commission_debt']]);
        // The approver needs the number to transfer to (payouts.approve reveals it on an open payout)
        $this->assertSame('8600123456789012', $card['card']);

        $done = $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => "Hujjatlar tekshirildi"])->assertOk()->json('data');

        $this->assertSame('completed', $done['status']);
        $this->assertFalse($done['breakdown']['preview']);
        $this->assertSame(['400000.00', '298000.00', '102000.00'], [$done['breakdown']['accrued'], $done['breakdown']['netted'], $done['breakdown']['transfer']]);
        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame([$september->number, $october->number], array_column(array_column($done['breakdown']['lines'], 'invoice'), 'number'));
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $october->status);

        $store->refresh();
        $this->assertSame(['100000.00', '0.00', '0.00'], [$store->wallet_balance, $store->debt_balance, $store->subscription_debt]);
        $payout->refresh();
        $this->assertSame(['298000.00', '102000.00'], [$payout->netted_amount, $payout->transferred_amount]);

        // Each deduction is its own ledger row naming what it closed; the wallet shows the netting and the transfer
        $debtRows = StoreTransaction::where('payout_request_id', $payout->id)->orderBy('id')->get();
        $this->assertSame(['-149000.00', '-149000.00'], $debtRows->pluck('amount')->all());
        $this->assertSame([$september->id, $october->id], $debtRows->pluck('subscription_invoice_id')->all());
        $walletRows = StoreWalletTransaction::where('payout_request_id', $payout->id)->orderBy('id')->get();
        $this->assertSame([StoreTransactionType::PAYOUT->value], $walletRows->pluck('type')->unique()->values()->all());
        $this->assertEquals([-298000, -102000], $walletRows->pluck('amount')->all());

        $entry = AuditLog::where('action', 'admin.finance.payouts.approved')->sole();
        $this->assertSame('Hujjatlar tekshirildi', $entry->payload_json['reason']);
        $this->assertSame('completed', $entry->after['status']);
        $this->assertSame('298000.00', $entry->payload_json['netted']);

        // Never paid twice: a repeat is the same answer, nothing moves, nothing is audited again
        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => "Hujjatlar tekshirildi"])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame('100000.00', $store->fresh()->wallet_balance);
        $this->assertSame(4, StoreWalletTransaction::where('store_id', $store->id)->count() + StoreTransaction::where('payout_request_id', $payout->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'admin.finance.payouts.approved')->count());
    }

    public function test_a_debt_larger_than_the_payout_takes_all_of_it_and_nothing_goes_to_the_card(): void
    {
        $store = $this->withWallet($this->storeWithTwoInvoices(), '300000.00');
        $this->ledger()->deductCommission($store, '20000.00', $this->financeOrder($store)); // from the wallet: 280 000 left
        $payout = $this->payout($store, '200000.00');

        $done = $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $this->finance(), ['reason' => 'Tasdiq'])->assertOk()->json('data');

        $this->assertSame(['200000.00', '200000.00', '0.00'], [$done['breakdown']['accrued'], $done['breakdown']['netted'], $done['breakdown']['transfer']]);
        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertSame('51000.00', $october->paid_amount);
        $store->refresh();
        $this->assertSame(['80000.00', '98000.00', '98000.00'], [$store->wallet_balance, $store->debt_balance, $store->subscription_debt]);
        $this->assertSame(1, StoreWalletTransaction::where('payout_request_id', $payout->id)->count());
    }

    public function test_the_commission_debt_is_netted_after_the_invoices(): void
    {
        $store = $this->withWallet($this->v2Store('Commission only'), '0.00');
        $this->ledger()->deductCommission($store, '30000.00', $this->financeOrder($store)); // debt (the wallet is empty)
        $this->withWallet($store, '150000.00');
        $payout = $this->payout($store, '50000.00');

        $done = $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $this->finance(), ['reason' => 'Tasdiq'])->assertOk()->json('data');

        $this->assertSame([['kind' => 'commission', 'amount' => '30000.00']], array_map(fn (array $line) => ['kind' => $line['kind'], 'amount' => $line['amount']], $done['breakdown']['lines']));
        $this->assertSame('20000.00', $done['breakdown']['transfer']);
        $this->assertSame(['100000.00', '0.00'], [$store->fresh()->wallet_balance, $store->fresh()->debt_balance]);
    }

    public function test_a_million_or_more_needs_a_second_member(): void
    {
        $store = $this->withWallet($this->v2Store('Katta'), '3000000.00');
        $big = $this->payout($store, '1000000.00');
        $first = $this->makeMember(PlatformRole::SUPERADMIN);
        $firstToken = $this->confirmedSession($first);
        $secondToken = $this->finance();

        $awaiting = $this->financeJson('POST', "/finance/payouts/{$big->id}/approve", $firstToken, ['reason' => 'Birinchi tasdiq'])->assertOk()->json('data');
        $this->assertSame('awaiting_second', $awaiting['status']);
        $this->assertTrue($awaiting['needs_second_approval']);
        $this->assertSame($first->id, $awaiting['first_approval']['by']['id']);
        $this->assertFalse($awaiting['actions']['can_approve']);
        $this->assertSame('3000000.00', $store->fresh()->wallet_balance);
        $this->assertSame(1, AuditLog::where('action', 'admin.finance.payouts.approved')->where('user_id', $first->id)->count());

        // The first approver (a superadmin too) never completes it
        $this->financeJson('POST', "/finance/payouts/{$big->id}/approve", $firstToken, ['reason' => 'Yana men'])
            ->assertForbidden()->assertJsonPath('code', 'four_eyes');

        // It waits in the second-approval queue and in Bugun
        $this->financeJson('GET', '/finance/payouts?status=awaiting_second', $secondToken)->assertOk()->assertJsonPath('data.data.0.id', $big->id);
        $this->assertSame(1, $this->financeJson('GET', '/finance/summary', $secondToken)->json('data.payouts.awaiting_second'));
        $kinds = array_column($this->financeJson('GET', '/today/attention', $firstToken)->assertOk()->json('data.groups') ?? [], 'kind');
        $this->assertContains('payout_second_approval', $kinds);

        $card = $this->financeJson('GET', "/finance/payouts/{$big->id}", $secondToken)->json('data');
        $this->assertTrue($card['actions']['can_approve']);
        $done = $this->financeJson('POST', "/finance/payouts/{$big->id}/approve", $secondToken, ['reason' => 'Ikkinchi tasdiq'])->assertOk()->json('data');
        $this->assertSame('completed', $done['status']);
        $this->assertSame('2000000.00', $store->fresh()->wallet_balance);

        // One tiyin under the threshold: one approval completes it
        $small = $this->payout($store, '999999.99');
        $this->financeJson('POST', "/finance/payouts/{$small->id}/approve", $firstToken, ['reason' => 'Tasdiq'])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame('1000000.01', $store->fresh()->wallet_balance);
    }

    public function test_reject_needs_a_reason_and_a_decided_payout_stays_decided(): void
    {
        $store = $this->withWallet($this->v2Store('Rad'), '500000.00');
        $payout = $this->payout($store, '100000.00');
        $token = $this->finance();

        $this->financeJson('POST', "/finance/payouts/{$payout->id}/reject", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $rejected = $this->financeJson('POST', "/finance/payouts/{$payout->id}/reject", $token, ['reason' => "Karta egasi boshqa"])->assertOk()->json('data');
        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('Karta egasi boshqa', $rejected['resolution_notes']);
        $this->financeJson('POST', "/finance/payouts/{$payout->id}/reject", $token, ['reason' => "Karta egasi boshqa"])->assertOk();
        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => 'Tasdiq'])->assertStatus(409)->assertJsonPath('code', 'payout_decided');
        $this->assertSame('500000.00', $store->fresh()->wallet_balance);
        $this->assertSame(1, AuditLog::where('action', 'admin.finance.payouts.rejected')->count());

        // Neither does the old panel complete it afterwards
        $this->expectException(\DomainException::class);
        PayoutRequest::findOrFail($payout->id)->update(['status' => 'completed']);
    }

    public function test_permissions_step_up_and_a_wallet_that_cannot_cover_it(): void
    {
        $store = $this->withWallet($this->v2Store('Ruxsat'), '50000.00');
        $payout = $this->payout($store, '80000.00');

        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $this->financeJson('GET', '/finance/payouts', $viewer)->assertOk()->assertJsonPath('data.data.0.id', $payout->id);
        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $viewer, ['reason' => 'Tasdiq'])->assertForbidden();
        $operator = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $this->financeJson('GET', '/finance/payouts', $operator)->assertForbidden();

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->signIn($finance);
        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => 'Tasdiq'])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($finance, $token);

        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => 'Tasdiq'])
            ->assertStatus(422)->assertJsonPath('code', 'insufficient_wallet');
        $this->assertSame('pending', $payout->fresh()->status);
        $this->assertSame(0, StoreWalletTransaction::where('store_id', $store->id)->count());
        $this->financeJson('GET', '/finance/payouts/999999', $token)->assertNotFound();
    }

    public function test_an_idempotency_key_replays_the_approval(): void
    {
        $store = $this->withWallet($this->v2Store('Kalit'), '500000.00');
        $payout = $this->payout($store, '100000.00');
        $token = $this->finance();

        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => 'Tasdiq'], ['Idempotency-Key' => 'payout-1'])->assertOk();
        $this->financeJson('POST', "/finance/payouts/{$payout->id}/approve", $token, ['reason' => 'Tasdiq'], ['Idempotency-Key' => 'payout-1'])
            ->assertOk()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame('400000.00', $store->fresh()->wallet_balance);
        $this->assertSame(1, StoreWalletTransaction::where('payout_request_id', $payout->id)->count());
    }

    public function test_the_old_panel_nets_the_debt_too_and_leaves_large_payouts_to_two_members(): void
    {
        $store = $this->withWallet($this->storeWithTwoInvoices('Panel'), '2000000.00');
        $small = $this->payout($store, '300000.00');

        PayoutRequest::findOrFail($small->id)->update(['status' => 'completed']);

        $small->refresh();
        $this->assertSame(['298000.00', '2000.00'], [$small->netted_amount, $small->transferred_amount]);
        $this->assertSame(['1700000.00', '0.00'], [$store->fresh()->wallet_balance, $store->fresh()->debt_balance]);

        $large = $this->payout($store, '1000000.00');
        try {
            PayoutRequest::findOrFail($large->id)->update(['status' => 'completed']);
            $this->fail('A large payout must be refused outside the control center.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('ikki xodim', $e->getMessage());
        }
        $this->assertSame('pending', $large->fresh()->status);
        $this->assertSame('1700000.00', $store->fresh()->wallet_balance);
    }
}
