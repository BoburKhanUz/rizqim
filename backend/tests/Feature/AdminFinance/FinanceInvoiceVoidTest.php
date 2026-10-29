<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\SubscriptionInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review W2: voiding an invoice never moves money on one member's word. A paid or partly paid invoice is not voided
 * (its money would turn into credit); an unpaid one of 1 000 000 so'm or more waits for a second, different member.
 */
class FinanceInvoiceVoidTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    /**
     * An unpaid invoice of 1 200 000 charged to the store's debt.
     *
     * @return array{0: Store, 1: SubscriptionInvoice}
     */
    private function bigInvoice(): array
    {
        $this->withoutTrials();
        $store = $this->v2Store('Katta obuna');
        $this->billing()->assignPlan($store, $this->plan(['monthly_base_fee' => 1200000]), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));
        $invoice = SubscriptionInvoice::where('store_id', $store->id)->sole();
        $this->assertSame(['1200000.00', '1200000.00'], [$invoice->amount, $store->fresh()->debt_balance]);

        return [$store->refresh(), $invoice];
    }

    private function voidRows(Store $store): int
    {
        return StoreTransaction::where('store_id', $store->id)->where('type', StoreTransactionType::SUBSCRIPTION->value)->where('amount', '<', 0)->count();
    }

    public function test_a_paid_or_partly_paid_invoice_is_not_voided_and_no_money_moves(): void
    {
        $store = $this->storeWithTwoInvoices('Tolangan');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
        // 200 000 by bank: September paid, 51 000 of October
        $this->financeJson('POST', '/finance/adjustments', $token, [
            'store_id' => $store->id, 'kind' => 'debt_payment', 'amount' => '200000', 'reason' => "Hisob raqamiga o'tkazma",
        ])->assertCreated();
        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame('98000.00', $store->fresh()->debt_balance);

        foreach ([$september, $october] as $invoice) {
            $this->financeJson('POST', "/pos/invoices/{$invoice->uuid}/void", $token, ['reason' => 'Xato hisob-faktura'])
                ->assertStatus(422)->assertJsonPath('code', 'invoice_paid');
        }

        $this->assertSame([SubscriptionInvoiceStatus::PAID, SubscriptionInvoiceStatus::ISSUED], [$september->fresh()->status, $october->fresh()->status]);
        $this->assertSame(['98000.00', '98000.00'], [$store->fresh()->debt_balance, $store->fresh()->subscription_debt]);
        $this->assertSame(0, $this->voidRows($store));
        $this->assertSame(0, AuditLog::where('action', 'admin.pos.invoice_voided')->count());

        // Marked paid by hand ("To'landi", the ledger paid nothing of it): not voided either
        $other = $this->storeWithTwoInvoices('Belgilangan');
        [$marked] = $this->invoicesOf($other);
        $this->billing()->markPaid($marked);
        $this->financeJson('POST', "/pos/invoices/{$marked->uuid}/void", $token, ['reason' => 'Xato hisob-faktura'])
            ->assertStatus(422)->assertJsonPath('code', 'invoice_paid');
        $this->assertSame('298000.00', $other->fresh()->debt_balance);
    }

    public function test_a_void_of_a_million_waits_for_a_second_member(): void
    {
        [$store, $invoice] = $this->bigInvoice();
        $first = $this->makeMember(PlatformRole::SUPERADMIN);
        $firstToken = $this->confirmedSession($first);
        $second = $this->makeMember(PlatformRole::FINANCE);

        $pending = $this->financeJson('POST', "/pos/invoices/{$invoice->uuid}/void", $firstToken, ['reason' => 'Shartnoma bekor'])->assertOk();
        $pending->assertJsonPath('data.invoice.status', 'issued')
            ->assertJsonPath('data.invoice.void_request.by.id', $first->id)
            ->assertJsonPath('data.invoice.void_request.reason', 'Shartnoma bekor');
        $this->assertStringContainsString('ikkinchi xodim', $pending->json('message'));
        $this->assertSame(['1200000.00', 0], [$store->fresh()->debt_balance, $this->voidRows($store)]);
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.invoice_void_requested')->count());

        // The same member again (a superadmin too): the same answer, nothing more
        $this->financeJson('POST', "/pos/invoices/{$invoice->uuid}/void", $firstToken, ['reason' => 'Yana'])->assertOk()
            ->assertJsonPath('data.invoice.status', 'issued');
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.invoice_void_requested')->count());
        $this->assertSame('Shartnoma bekor', $invoice->fresh()->void_request_reason);

        // A different member completes it: only now the debt goes down
        $this->financeJson('POST', "/pos/invoices/{$invoice->uuid}/void", $this->confirmedSession($second), ['reason' => 'Tasdiqlayman'])->assertOk()
            ->assertJsonPath('data.invoice.status', 'void');
        $invoice->refresh();
        $this->assertSame([$second->id, $first->id], [$invoice->voided_by, $invoice->void_requested_by]);
        $this->assertSame(['0.00', 1], [$store->fresh()->debt_balance, $this->voidRows($store)]);
        $this->assertSame(1, AuditLog::where('action', 'admin.pos.invoice_voided')->count());
    }
}
