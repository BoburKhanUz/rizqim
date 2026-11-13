<?php

namespace Tests\Feature\PosCorporate;

use App\Domain\Pos\Accounts\CustomerAccountService;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAccountMember;
use App\Models\Payment;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P6-44: a corporate lunch paid at the till with the bill's `account` tender on the real ledger: the member by talon
 * or phone, no manager needed unless the account asks for it, the limits refuse before any money is taken, the sale
 * receipt stays the full bill with the account part as `credit`, the bill's branch's business day, a refund gives the
 * day back.
 */
class PosCorporateTillTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    private FakeTelegramMessenger $telegram;

    private CustomerAccount $company;

    private CustomerAccountMember $aziz;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 23.11.2026 05:30 in Tashkent (before the PIN session is opened: it expires)
        Carbon::setTestNow(Carbon::parse('2026-11-23 00:30:00', 'UTC'));
        $this->setUpPosBilling();
        $this->app->instance(CustomerAccountLedger::class, app(CustomerAccountService::class));
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);

        // The branch's day starts at 06:00: 05:30 on Monday is still Sunday's business day
        $this->branch->forceFill(['business_day_starts_at' => '06:00:00'])->save();

        $this->company = CustomerAccount::create([
            'store_id' => $this->store->id, 'name' => 'Navoiy zavodi', 'kind' => 'corporate', 'talon_mode' => 'daily_limit',
            'charge_approval' => false, 'monthly_limit' => '1000000',
        ]);
        $this->aziz = CustomerAccountMember::create([
            'store_id' => $this->store->id, 'customer_account_id' => $this->company->id, 'name' => 'Aziz',
            'phone' => '+998901112233', 'code' => '5550001111', 'daily_limit' => '60000',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function lunch(string $total): string
    {
        return (string) $this->bill($this->posOrder([['Tushlik', '1', $total]]))->json('data.bills.0.uuid');
    }

    public function test_a_lunch_on_the_company_needs_no_manager_and_the_receipt_is_the_full_bill(): void
    {
        $this->openShift();
        $bill = $this->lunch('45000');

        $this->pay($bill, [['tender' => 'account', 'amount' => '45000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111']])->assertOk()
            ->assertJsonPath('data.bill.status', 'paid');

        $entry = CustomerAccountEntry::where('bill_uuid', $bill)->firstOrFail();
        $this->assertSame((int) $this->aziz->id, (int) $entry->member_id);
        $this->assertSame('2026-11-22', $entry->business_date->toDateString());
        $this->assertSame((int) $this->cashier->id, (int) $entry->approved_by);
        $this->assertSame('45000.00', (string) $this->company->refresh()->balance);
        $this->assertEquals(['account_uuid' => $this->company->uuid, 'approved_by' => $this->cashier->id, 'member_code' => '5550001111'], Payment::where('provider', 'account')->value('payload'));
        // Chek summasi = schyot summasi; the company's part is the credit part
        $document = $this->fiscal->document("pos-bill:{$bill}:sale");
        $this->assertSame('45000.00', $document->total->toString());
        $this->assertSame([true], array_map(fn ($payment) => $payment->credit, $document->payments));
        // A lunch is routine: no Telegram message to the owner
        $this->assertSame([], $this->telegram->sent ?? []);
    }

    public function test_the_limits_refuse_before_any_money_is_taken_and_cash_still_pays(): void
    {
        $this->openShift();
        $this->pay($this->lunch('45000'), [['tender' => 'account', 'amount' => '45000', 'account_uuid' => $this->company->uuid, 'member_phone' => '90 111 22 33']])->assertOk();

        // 20 000 more is over Aziz's 60 000 of the day: refused, nothing taken; the guest pays cash instead
        $bill = $this->lunch('20000');
        $this->pay($bill, [['tender' => 'account', 'amount' => '20000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111']])
            ->assertStatus(422)->assertJsonPath('data.reason', 'member_daily_limit')->assertJsonPath('data.available', '15000.00');
        $this->assertSame(0, Payment::where('bill_id', \App\Models\OrderBill::where('uuid', $bill)->value('id'))->count());
        // Mixed: the company pays what is left of the day, the guest the rest in cash
        $this->pay($bill, [
            ['tender' => 'account', 'amount' => '15000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111'],
            ['tender' => 'cash', 'amount' => '5000'],
        ])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        // No member named in daily_limit mode; an unknown talon
        $this->pay($this->lunch('1000'), [['tender' => 'account', 'amount' => '1000', 'account_uuid' => $this->company->uuid]])->assertStatus(422)->assertJsonPath('data.reason', 'member_required');
        $this->pay($this->lunch('1000'), [['tender' => 'account', 'amount' => '1000', 'account_uuid' => $this->company->uuid, 'member_code' => '0000']])->assertNotFound()->assertJsonPath('data.reason', 'member_not_found');
        $this->aziz->forceFill(['is_active' => false])->save();
        $this->pay($this->lunch('1000'), [['tender' => 'account', 'amount' => '1000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111']])->assertStatus(422)->assertJsonPath('data.reason', 'member_inactive');
    }

    public function test_with_charge_approval_on_the_manager_approves_as_for_nasiya(): void
    {
        $this->company->forceFill(['charge_approval' => true])->save();
        $this->openShift();
        $bill = $this->lunch('30000');
        $tenders = [['tender' => 'account', 'amount' => '30000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111']];

        $this->pay($bill, $tenders)->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->pay($bill, $tenders, ['approval_token' => $this->approval('customer_accounts.manage')])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame((int) $this->manager->id, (int) CustomerAccountEntry::where('bill_uuid', $bill)->value('approved_by'));
    }

    public function test_a_refund_at_the_till_gives_the_members_day_back(): void
    {
        $this->openShift();
        $bill = $this->lunch('60000');
        $this->pay($bill, [['tender' => 'account', 'amount' => '60000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111']])->assertOk();
        $payment = Payment::where('provider', 'account')->firstOrFail();

        $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '60000', 'reason' => 'Taom qaytarildi', 'approval_token' => $this->approval('pos.refund')])->assertCreated();

        $reversal = CustomerAccountEntry::where('type', 'adjustment')->firstOrFail();
        $this->assertSame([(int) $this->aziz->id, '2026-11-22', '-60000.00'], [(int) $reversal->member_id, $reversal->business_date->toDateString(), (string) $reversal->amount]);
        $this->assertSame('0.00', (string) $this->company->refresh()->balance);
        // Aziz may eat for 60 000 again today
        $this->pay($this->lunch('60000'), [['tender' => 'account', 'amount' => '60000', 'account_uuid' => $this->company->uuid, 'member_code' => '5550001111']])->assertOk();
    }

    public function test_a_split_lunch_is_one_meal_and_it_comes_back_only_when_the_whole_order_is_refunded(): void
    {
        // C8: a subscription member, one meal a day; the lunch's order is split into two bills
        $bobur = CustomerAccountMember::create([
            'store_id' => $this->store->id, 'customer_account_id' => $this->company->id, 'name' => 'Bobur',
            'code' => '5550002222', 'meals_per_day' => 1,
        ]);
        $this->openShift();
        $tender = fn (string $amount) => [['tender' => 'account', 'amount' => $amount, 'account_uuid' => $this->company->uuid, 'member_code' => '5550002222']];
        $bills = $this->bill($this->posOrder([['Tushlik', '1', '40000']]), 'equal', ['parts' => 2])->assertCreated()->json('data.bills');
        $this->pay($bills[0]['uuid'], $tender('20000'))->assertOk();
        $this->pay($bills[1]['uuid'], $tender('20000'))->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $usage = app(\App\Domain\Pos\Accounts\CorporateAccounts::class)->memberDay($bobur, '2026-11-22');
        $this->assertSame(['40000.00', 1], [$usage['amount']->toString(), $usage['meals']]);

        // A second lunch the same day: refused
        $this->pay($this->lunch('30000'), $tender('30000'))->assertStatus(422)->assertJsonPath('data.reason', 'member_meals_used');

        // One bill of the order refunded: the meal is still eaten; both refunded: it comes back
        $payments = Payment::where('provider', 'account')->orderBy('id')->get();
        $this->pos('POST', '/refunds', ['payment_uuid' => $payments[0]->uuid, 'amount' => '20000', 'reason' => 'Qaytarildi', 'approval_token' => $this->approval('pos.refund')])->assertCreated();
        $this->pay($this->lunch('30000'), $tender('30000'))->assertStatus(422)->assertJsonPath('data.reason', 'member_meals_used');
        $this->pos('POST', '/refunds', ['payment_uuid' => $payments[1]->uuid, 'amount' => '20000', 'reason' => 'Qaytarildi', 'approval_token' => $this->approval('pos.refund')])->assertCreated();
        $this->pay($this->lunch('30000'), $tender('30000'))->assertOk();
    }
}
