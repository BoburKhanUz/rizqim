<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\AuditLog;
use App\Models\FinanceAdjustment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P4-23: manual adjustments (Moliya → Qo'lda tuzatish): finance.manage + step-up + reason + optional document; from
 * 1 000 000 so'm pending until a DIFFERENT member approves (a superadmin too); the ledger row only on approval;
 * reject; idempotent; audited.
 */
class FinanceAdjustmentTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    public function test_a_small_adjustment_is_applied_at_once_and_audited(): void
    {
        $store = $this->v2Store('Kichik', ['credit_limit' => -100000]);
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $made = $this->financeJson('POST', '/finance/adjustments', $token, [
            'store_id' => $store->id, 'kind' => 'debt_charge', 'amount' => '150000.50', 'reason' => "Yozilmagan komissiya",
        ])->assertCreated()->json('data');

        $this->assertSame('applied', $made['status']);
        $this->assertFalse($made['needs_second_approval']);
        $this->assertSame('150000.50', $made['signed_amount']);
        $this->assertCount(1, $made['transactions']);
        $row = StoreTransaction::findOrFail($made['transactions'][0]['id']);
        $this->assertSame(StoreTransactionType::ADJUSTMENT, $row->type);
        $this->assertSame('150000.50', $row->amount);
        $this->assertSame((string) $made['uuid'], $row->reference_id);
        $store->refresh();
        $this->assertSame('150000.50', $store->debt_balance);
        // A charge over the credit limit blocks the store like a commission
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $store->status);

        $entry = AuditLog::where('action', 'admin.finance.adjustments.created')->sole();
        $this->assertSame('Yozilmagan komissiya', $entry->payload_json['reason']);
        $this->assertSame('applied', $entry->after['status']);

        // A credit of the commission debt unblocks it
        $this->financeJson('POST', '/finance/adjustments', $token, [
            'store_id' => $store->id, 'kind' => 'debt_credit', 'amount' => '100000', 'reason' => 'Xato komissiya',
        ])->assertCreated()->assertJsonPath('data.signed_amount', '-100000.00');
        $this->assertSame(StoreStatus::ACTIVE, $store->fresh()->status);
        $this->assertSame('50000.50', $store->fresh()->debt_balance);
    }

    public function test_a_million_waits_for_a_different_member_and_is_booked_only_then(): void
    {
        $store = $this->v2Store('Katta tuzatish');
        $boss = $this->makeMember(PlatformRole::SUPERADMIN);
        $bossToken = $this->confirmedSession($boss);
        $financeToken = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $made = $this->financeJson('POST', '/finance/adjustments', $bossToken, [
            'store_id' => $store->id, 'kind' => 'wallet_credit', 'amount' => '1000000', 'reason' => "Bank ko'chirmasi bo'yicha",
        ])->assertCreated()->json('data');
        $this->assertSame('pending', $made['status']);
        $this->assertTrue($made['needs_second_approval']);
        $this->assertFalse($made['can_approve']);
        $this->assertSame('0.00', $store->fresh()->wallet_balance);
        $this->assertSame(0, StoreWalletTransaction::where('store_id', $store->id)->count());

        // The maker, a superadmin, cannot approve it
        $this->financeJson('POST', "/finance/adjustments/{$made['id']}/approve", $bossToken, ['reason' => "O'zim"])
            ->assertForbidden()->assertJsonPath('code', 'four_eyes');

        // It waits in the queue and in Bugun
        $this->assertSame(1, $this->financeJson('GET', '/finance/summary', $financeToken)->json('data.adjustments.pending'));
        $this->financeJson('GET', '/finance/adjustments?status=pending', $financeToken)->assertOk()->assertJsonPath('data.data.0.can_approve', true);
        $kinds = array_column($this->financeJson('GET', '/today/attention', $bossToken)->json('data.groups'), 'kind');
        $this->assertContains('adjustment_second_approval', $kinds);

        $approved = $this->financeJson('POST', "/finance/adjustments/{$made['id']}/approve", $financeToken, ['reason' => "Ko'chirma tekshirildi"], ['Idempotency-Key' => 'adj-1'])
            ->assertOk()->json('data');
        $this->assertSame('applied', $approved['status']);
        $this->assertSame('1000000.00', $store->fresh()->wallet_balance);
        $this->assertSame(1, StoreWalletTransaction::where('store_id', $store->id)->where('type', StoreTransactionType::ADJUSTMENT->value)->count());

        // Never twice: the same key, the same member again, another decision
        $this->financeJson('POST', "/finance/adjustments/{$made['id']}/approve", $financeToken, ['reason' => "Ko'chirma tekshirildi"], ['Idempotency-Key' => 'adj-1'])
            ->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->financeJson('POST', "/finance/adjustments/{$made['id']}/approve", $financeToken, ['reason' => "Ko'chirma tekshirildi"])->assertOk();
        $this->financeJson('POST', "/finance/adjustments/{$made['id']}/reject", $financeToken, ['reason' => 'Fikr o\'zgardi'])
            ->assertStatus(409)->assertJsonPath('code', 'adjustment_decided');
        $this->assertSame('1000000.00', $store->fresh()->wallet_balance);
        $this->assertSame(1, AuditLog::where('action', 'admin.finance.adjustments.approved')->count());
    }

    public function test_a_rejected_adjustment_books_nothing(): void
    {
        $store = $this->v2Store('Rad tuzatish');
        $maker = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
        $made = $this->financeJson('POST', '/finance/adjustments', $maker, [
            'store_id' => $store->id, 'kind' => 'debt_charge', 'amount' => '2500000', 'reason' => 'Jarima',
        ])->assertCreated()->json('data');

        $other = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
        $this->financeJson('POST', "/finance/adjustments/{$made['id']}/reject", $other, ['reason' => "Asos yo'q"])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->financeJson('POST', "/finance/adjustments/{$made['id']}/approve", $other, ['reason' => 'Tasdiq'])->assertStatus(409);

        $this->assertSame('0.00', $store->fresh()->debt_balance);
        $this->assertSame(0, StoreTransaction::where('store_id', $store->id)->count());
    }

    public function test_a_payment_pays_the_oldest_invoices_first_each_its_own_row(): void
    {
        $store = $this->storeWithTwoInvoices('Bank');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $made = $this->financeJson('POST', '/finance/adjustments', $token, [
            'store_id' => $store->id, 'kind' => 'debt_payment', 'amount' => '200000', 'reason' => "Hisob raqamiga o'tkazma",
        ])->assertCreated()->json('data');

        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertSame('51000.00', $october->paid_amount);
        $this->assertSame([$september->id, $october->id], array_column($made['transactions'], 'subscription_invoice_id'));
        $this->assertSame(['-149000.00', '-51000.00'], array_column($made['transactions'], 'amount'));
        $this->assertSame(['98000.00', '98000.00'], [$store->fresh()->debt_balance, $store->fresh()->subscription_debt]);
    }

    public function test_a_wallet_debit_never_overdraws_and_input_is_checked(): void
    {
        $store = $this->v2Store('Hamyon');
        $store->forceFill(['wallet_balance' => '10000.00'])->save();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => $store->id, 'kind' => 'wallet_debit', 'amount' => '10000.01', 'reason' => 'Xato'])
            ->assertStatus(422)->assertJsonPath('code', 'insufficient_wallet');
        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => $store->id, 'kind' => 'wallet_debit', 'amount' => '0', 'reason' => 'Xato'])->assertStatus(422);
        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => $store->id, 'kind' => 'wallet_debit', 'amount' => '1.234', 'reason' => 'Xato'])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => $store->id, 'kind' => 'gift', 'amount' => '1', 'reason' => 'Xato'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => $store->id, 'kind' => 'wallet_debit', 'amount' => '1'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => 999999, 'kind' => 'wallet_debit', 'amount' => '1', 'reason' => 'Xato'])->assertNotFound();

        $this->financeJson('POST', '/finance/adjustments', $token, ['store_id' => $store->id, 'kind' => 'wallet_debit', 'amount' => '10000', 'reason' => 'Xato'])->assertCreated();
        $this->assertSame('0.00', $store->fresh()->wallet_balance);
        $this->assertSame(1, FinanceAdjustment::count());
    }

    public function test_a_document_is_kept_privately_and_only_finance_members_download_it(): void
    {
        Storage::fake('local');
        $store = $this->v2Store('Hujjat');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $this->financeJson('POST', '/finance/adjustments', $token, [
            'store_id' => $store->id, 'kind' => 'wallet_credit', 'amount' => '5000', 'reason' => 'Akt',
            'document' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertStatus(422)->assertJsonValidationErrors('document');

        $made = $this->financeJson('POST', '/finance/adjustments', $token, [
            'store_id' => $store->id, 'kind' => 'wallet_credit', 'amount' => '5000', 'reason' => 'Akt',
            'document' => UploadedFile::fake()->create('akt 12.pdf', 20, 'application/pdf'),
        ])->assertCreated()->json('data');
        $this->assertSame('akt 12.pdf', $made['document']['name']);
        $adjustment = FinanceAdjustment::findOrFail($made['id']);
        Storage::disk('local')->assertExists($adjustment->document_path);
        $this->assertArrayNotHasKey('document_path', $made);

        $this->financeJson('GET', "/finance/adjustments/{$made['id']}/document", $token)->assertOk()->assertDownload('akt 12.pdf');
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $this->financeJson('GET', "/finance/adjustments/{$made['id']}/document", $viewer)->assertOk();
        $operator = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $this->financeJson('GET', "/finance/adjustments/{$made['id']}/document", $operator)->assertForbidden();
        $this->financeJson('POST', '/finance/adjustments', $viewer, ['store_id' => $store->id, 'kind' => 'wallet_credit', 'amount' => '1', 'reason' => 'Akt'])->assertForbidden();
    }

    public function test_an_idempotency_key_creates_one_adjustment(): void
    {
        $store = $this->v2Store('Bitta');
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
        $body = ['store_id' => $store->id, 'kind' => 'wallet_credit', 'amount' => '7000', 'reason' => 'Tuzatish'];

        $this->financeJson('POST', '/finance/adjustments', $token, $body, ['Idempotency-Key' => 'make-1'])->assertCreated();
        $this->financeJson('POST', '/finance/adjustments', $token, $body, ['Idempotency-Key' => 'make-1'])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, FinanceAdjustment::count());
        $this->assertSame('7000.00', $store->fresh()->wallet_balance);
    }
}
