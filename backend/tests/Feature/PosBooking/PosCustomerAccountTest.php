<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Accounts\CustomerAccountException;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Enums\UserRole;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountEntry;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-17: customer accounts (nasiya, research report R18 and §5 "sotuv → kredit"): limits, blocking, the ledger P3-18
 * charges, repayments with CREDIT receipts, corrections, debtors and statements, owner alerts, who may do what.
 */
class PosCustomerAccountTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
    }

    private function open(array $data = [], $as = null): string
    {
        return (string) $this->api($as ?? $this->manager, 'POST', '/api/v1/pos/customer-accounts', $data + [
            'name' => 'Akmal aka',
            'phone' => '+998 90 111 22 33',
            'group_name' => 'Payshanba gapi',
            'credit_limit' => '2000000',
        ])->assertCreated()->json('data.uuid');
    }

    private function charge(string $uuid, string $amount, ?string $bill = null, ?int $approver = null): void
    {
        app(CustomerAccountLedger::class)->charge($uuid, Money::of($amount), $bill ?? (string) Str::uuid(), $approver ?? (int) $this->manager->id);
    }

    private function repay(string $uuid, string $amount, string $tender = 'cash', ?string $key = null, $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->api($as ?? $this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/repayments", ['amount' => $amount, 'tender' => $tender], ['Idempotency-Key' => $key ?? Str::random(12)]);
    }

    public function test_open_change_and_block_an_account(): void
    {
        $uuid = $this->open();
        $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}")->assertOk()
            ->assertJsonPath('data.account.phone', '+998901112233')
            ->assertJsonPath('data.account.credit_limit', '2000000.00')
            ->assertJsonPath('data.account.balance', '0.00')
            ->assertJsonPath('data.account.available_credit', '2000000.00')
            // A branch-bound manager opens it for their branch
            ->assertJsonPath('data.account.branch_id', $this->branch->id);

        // The phone is unique in the store
        $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Ikkinchi', 'phone' => '901112233'])->assertStatus(422)
            ->assertJsonValidationErrors('phone');
        $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Xato', 'phone' => '123'])->assertStatus(422);

        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['credit_limit' => null, 'is_blocked' => true])->assertOk()
            ->assertJsonPath('data.credit_limit', null)
            ->assertJsonPath('data.available_credit', null)
            ->assertJsonPath('data.is_blocked', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_account.updated']);
    }

    public function test_charges_respect_the_limit_and_blocking_and_one_charge_per_bill(): void
    {
        $uuid = $this->open();
        $bill = (string) Str::uuid();

        $this->charge($uuid, '1500000', $bill);
        $this->charge($uuid, '1500000', $bill); // a retried close: nothing new
        $account = CustomerAccount::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('1500000.00', $account->balance);
        $entry = CustomerAccountEntry::firstOrFail();
        $this->assertSame('charge', $entry->type->value);
        $this->assertSame((int) $this->manager->id, (int) $entry->approved_by);
        $this->assertSame($bill, $entry->bill_uuid);
        $this->assertSame('1500000.00', $entry->balance_after);

        $this->assertRefused(fn () => $this->charge($uuid, '1000', $bill), 'already_charged');
        $this->assertRefused(fn () => $this->charge($uuid, '500000.01'), 'credit_limit_exceeded');
        $this->charge($uuid, '500000');
        $this->assertSame('2000000.00', $account->refresh()->balance);

        $account->forceFill(['is_blocked' => true, 'credit_limit' => null])->save();
        $this->assertRefused(fn () => $this->charge($uuid, '1'), 'account_blocked');
        $this->assertRefused(fn () => $this->charge((string) Str::uuid(), '1'), 'account_not_found');
        $this->assertRefused(fn () => $this->charge($uuid, '0'), 'invalid_amount');

        // A limit of zero: no credit at all
        $zero = $this->open(['phone' => '+998901112244', 'credit_limit' => '0']);
        $this->assertRefused(fn () => $this->charge($zero, '1'), 'credit_limit_exceeded');
    }

    public function test_charges_never_cross_stores_or_branches(): void
    {
        $uuid = $this->open();
        $this->assertRefused(fn () => $this->charge($uuid, '1000', null, (int) $this->foreignOwner->id), 'account_not_found');

        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->assertRefused(fn () => $this->charge($uuid, '1000', null, (int) $otherManager->id), 'account_other_branch');

        // The owner works store-wide
        $this->charge($uuid, '1000', null, (int) $this->owner->id);
        $this->assertSame('1000.00', CustomerAccount::where('uuid', $uuid)->value('balance'));
    }

    public function test_a_big_credit_and_every_repayment_reach_the_owner(): void
    {
        config(['pos_bookings.credit_alert_threshold' => '500000']);
        $uuid = $this->open();

        $this->charge($uuid, '499999');
        $this->assertCount(0, $this->telegram->sent);
        $this->charge($uuid, '500000');
        $this->assertCount(1, $this->telegram->sent);
        $this->assertStringContainsString("Nasiyaga yozildi: 500 000 so'm", $this->telegram->sent[0]['text']);
        $this->assertStringContainsString("Jami qarz: 999 999 so'm", $this->telegram->sent[0]['text']);
        $this->assertSame((string) $this->owner->telegram_id, $this->telegram->sent[0]['chat']);

        $this->repay($uuid, '100000')->assertCreated();
        $this->assertCount(2, $this->telegram->sent);
        $this->assertStringContainsString("Nasiya to'landi: 100 000 so'm (cash)", $this->telegram->sent[1]['text']);
    }

    public function test_a_repayment_issues_a_credit_receipt_and_is_idempotent(): void
    {
        $uuid = $this->open();
        $this->charge($uuid, '850000');

        $first = $this->repay($uuid, '350000', 'card', 'repay-1')->assertCreated()
            ->assertJsonPath('data.account.balance', '500000.00')
            ->assertJsonPath('data.entry.type', 'repayment')
            ->assertJsonPath('data.entry.amount', '-350000.00')
            ->assertJsonPath('data.entry.balance_after', '500000.00')
            ->assertJsonPath('data.entry.tender', 'card')
            ->assertJsonPath('data.entry.fiscal_status', 'issued')
            ->assertJsonPath('data.entry.user.id', $this->cashier->id);
        $this->repay($uuid, '350000', 'card', 'repay-1')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(2, CustomerAccountEntry::count());
        $this->assertCount(1, $this->fiscal->documents());
        $document = $this->fiscal->documents()[0];
        $this->assertSame(FiscalReceiptType::CREDIT, $document->type);
        $this->assertSame(['credit'], $this->fiscalLines->charges);
        $this->assertSame('350000.00', $document->total->toString());
        $this->assertSame(FiscalPaymentForm::CARD, $document->payments[0]->form);
        $this->assertSame('customer_account_entry', $document->subjectType);
        $this->assertSame($first->json('data.entry.fiscal_receipt_uuid'), $this->fiscal->receipts[$document->idempotencyKey]['result']->receiptUuid);

        // Never more than the debt; never without an Idempotency-Key
        $this->repay($uuid, '500000.01')->assertStatus(422)->assertJsonPath('data.reason', 'more_than_debt');
        $this->api($this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/repayments", ['amount' => '1', 'tender' => 'cash'])->assertStatus(400);
        $this->repay($uuid, '100', 'account')->assertStatus(422);

        // A blocked account still takes repayments
        CustomerAccount::where('uuid', $uuid)->update(['is_blocked' => true]);
        $this->repay($uuid, '500000', 'qr')->assertCreated()->assertJsonPath('data.account.balance', '0.00');
    }

    public function test_corrections_debtors_and_the_statement(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(8, 0));
        $akmal = $this->open();
        $bobur = $this->open(['name' => 'Bobur', 'phone' => '+998935554433', 'group_name' => null]);
        $this->open(['name' => 'Qarzi yo\'q', 'phone' => '+998935554400', 'group_name' => null]);
        $this->charge($akmal, '300000');

        $this->travelTo(now()->setDate(2026, 11, 3));
        $this->charge($akmal, '200000');
        $this->charge($bobur, '900000');
        $this->repay($akmal, '100000')->assertCreated();
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$akmal}/adjustments", ['amount' => '-50000', 'note' => 'Chegirma sifatida kechildi'])
            ->assertCreated()->assertJsonPath('data.account.balance', '350000.00')->assertJsonPath('data.entry.approved_by.id', $this->manager->id);
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$akmal}/adjustments", ['amount' => '-400000', 'note' => 'x'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'more_than_debt');
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$akmal}/adjustments", ['amount' => '1000'])->assertStatus(422);

        $debtors = $this->api($this->manager, 'GET', '/api/v1/pos/customer-accounts/debtors')->assertOk()->json('data');
        $this->assertSame('1250000.00', $debtors['total_debt']);
        $this->assertSame(2, $debtors['count']);
        $this->assertSame(['Bobur', 'Akmal aka'], array_column($debtors['accounts'], 'name'));
        $this->assertSame(['Bobur', 'Akmal aka'], array_column($this->api($this->cashier, 'GET', '/api/v1/pos/customer-accounts?debtors=1')->json('data.accounts'), 'name'));
        $this->assertSame(['Akmal aka'], array_column($this->api($this->cashier, 'GET', '/api/v1/pos/customer-accounts?q=gapi')->json('data.accounts'), 'name'));
        $this->assertSame(['Bobur'], array_column($this->api($this->cashier, 'GET', '/api/v1/pos/customer-accounts?q=5554433')->json('data.accounts'), 'name'));

        $statement = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$akmal}/statement?from=2026-11-02&to=2026-11-03")->assertOk()->json('data');
        $this->assertSame('300000.00', $statement['opening_balance']);
        $this->assertSame('350000.00', $statement['closing_balance']);
        $this->assertSame('200000.00', $statement['charges_total']);
        $this->assertSame('100000.00', $statement['repayments_total']);
        $this->assertSame('-50000.00', $statement['adjustments_total']);
        $this->assertSame(['charge', 'repayment', 'adjustment'], array_column($statement['entries'], 'type'));
    }

    public function test_who_may_do_what_and_isolation(): void
    {
        $uuid = $this->open();
        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);

        // The cashier reads and takes repayments, but does not open, change or correct accounts
        $this->api($this->cashier, 'GET', '/api/v1/pos/customer-accounts')->assertOk();
        $this->api($this->cashier, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'X'])->assertForbidden();
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['credit_limit' => '9000000'])->assertForbidden();
        $this->api($this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/adjustments", ['amount' => '-1', 'note' => 'x'])->assertForbidden();
        $this->api($waiter, 'GET', '/api/v1/pos/customer-accounts')->assertForbidden();

        // Another store: not found; another branch's account: not found for that branch's staff, seen by the owner
        $this->api($this->foreignOwner, 'GET', "/api/v1/pos/customer-accounts/{$uuid}")->assertNotFound();
        $this->assertSame([], $this->api($this->foreignOwner, 'GET', '/api/v1/pos/customer-accounts')->json('data.accounts'));
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->api($otherManager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}")->assertNotFound();
        $this->api($otherManager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/repayments", ['amount' => '1', 'tender' => 'cash'], ['Idempotency-Key' => 'x1'])->assertNotFound();
        $this->api($this->owner, 'GET', "/api/v1/pos/customer-accounts/{$uuid}")->assertOk();

        // A store-wide account (opened by the owner) is seen in every branch; a terminal session acts in its branch
        $storeWide = $this->open(['name' => 'Umumiy', 'phone' => '+998935550000'], $this->owner);
        $this->api($otherManager, 'GET', "/api/v1/pos/customer-accounts/{$storeWide}")->assertOk()->assertJsonPath('data.account.branch_id', null);
        $session = $this->terminalSession($this->owner, $this->otherBranch);
        $this->apiToken($session['token'], 'GET', "/api/v1/pos/customer-accounts/{$uuid}")->assertNotFound();
        $this->apiToken($session['token'], 'GET', "/api/v1/pos/customer-accounts/{$storeWide}")->assertOk();

        $this->assertRefused(fn () => app(CustomerAccountLedger::class)->charge($uuid, Money::of('1'), (string) Str::uuid(), 999999), 'approver_unknown');
    }

    private function assertRefused(callable $action, string $reason): void
    {
        try {
            $action();
            $this->fail("Expected the account to refuse ({$reason}).");
        } catch (CustomerAccountException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
