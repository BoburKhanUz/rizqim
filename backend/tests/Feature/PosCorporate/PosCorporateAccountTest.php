<?php

namespace Tests\Feature\PosCorporate;

use App\Domain\Pos\Accounts\CustomerAccountException;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\UserRole;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAccountMember;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\PosBooking\PosBookingFixtures;
use Tests\TestCase;

/**
 * P6-44 (D4): corporate accounts and talons on the real ledger: the account kind and its details, members with daily
 * limits and meals per day, the monthly limit, the business day (Asia/Tashkent, 05:00) the limits count in, the
 * till's member lookup, the monthly statement and its Excel file, who may do what, other stores.
 */
class PosCorporateAccountTest extends TestCase
{
    use PosBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
        // The stolovaya: corporate talons on by default
        foreach ([$this->branch, $this->otherBranch] as $branch) {
            $branch->forceFill(['pos_settings' => PosSettings::withFormat($branch->fresh()->pos_settings, FormatProfile::STOLOVAYA, [])])->save();
        }
        // Friday 20.11.2026, 12:00 in Tashkent
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function company(array $data = []): string
    {
        return (string) $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', $data + [
            'name' => 'Navoiy zavodi',
            'kind' => 'corporate',
            'company_name' => '"Navoiy Metall" MChJ',
            'inn' => '301234567',
            'contact_name' => 'Dilshod Karimov',
            'contact_phone' => '90 123 45 67',
            'invoice_email' => 'buxgalter@navoiy.uz',
            'monthly_limit' => '5000000',
            'talon_mode' => 'daily_limit',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.uuid');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function member(string $account, array $data = []): array
    {
        return $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$account}/members", $data + ['name' => 'Aziz Rahimov'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()->json('data');
    }

    private function charge(string $account, string $amount, ?string $code = null, ?string $bill = null, ?string $phone = null): string
    {
        $bill ??= (string) Str::uuid();
        app(CustomerAccountLedger::class)->charge($account, Money::of($amount), $bill, (int) $this->manager->id, $code, $phone);

        return $bill;
    }

    private function refused(callable $call): CustomerAccountException
    {
        try {
            $call();
        } catch (CustomerAccountException $e) {
            return $e;
        }
        $this->fail('The charge was not refused.');
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
    }

    public function test_a_corporate_account_with_its_details_masked_in_lists_and_no_approval_by_default(): void
    {
        $uuid = $this->company();
        $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}")->assertOk()
            ->assertJsonPath('data.account.kind', 'corporate')
            ->assertJsonPath('data.account.kind_label', 'Korxona')
            ->assertJsonPath('data.account.inn', '301234567')
            ->assertJsonPath('data.account.contact_phone', '+998901234567')
            ->assertJsonPath('data.account.monthly_limit', '5000000.00')
            ->assertJsonPath('data.account.charge_approval', false)
            ->assertJsonPath('data.account.talon_mode', 'daily_limit')
            ->assertJsonPath('data.account.members_count', 0);

        // Lists mask the INN; the kind filter
        $this->member($uuid);
        $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Akmal aka'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()
            ->assertJsonPath('data.kind', 'customer')->assertJsonPath('data.charge_approval', true);
        $list = $this->api($this->manager, 'GET', '/api/v1/pos/customer-accounts?kind=corporate')->assertOk();
        $this->assertCount(1, $list->json('data.accounts'));
        $list->assertJsonPath('data.accounts.0.inn', '30*****67')->assertJsonPath('data.accounts.0.members_count', 1);
        $this->assertCount(2, $this->api($this->manager, 'GET', '/api/v1/pos/customer-accounts')->json('data.accounts'));

        // Wrong details
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['inn' => '12345'])->assertStatus(422)->assertJsonValidationErrors('inn');
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['talon_mode' => 'paper'])->assertStatus(422)->assertJsonValidationErrors('talon_mode');
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['invoice_email' => 'not-mail'])->assertStatus(422)->assertJsonValidationErrors('invoice_email');

        // The owner turns the approval on (audited); back to nasiya only without members
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['charge_approval' => true])->assertOk()->assertJsonPath('data.charge_approval', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_account.updated']);
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}", ['kind' => 'customer'])->assertStatus(422)->assertJsonPath('data.reason', 'account_has_members');
    }

    public function test_a_corporate_account_needs_the_module(): void
    {
        // Choyxona: nasiya yes, corporate talons no
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->fresh()->pos_settings, FormatProfile::CHOYXONA, [])])->save();
        $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Zavod', 'kind' => 'corporate'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $nasiya = (string) $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Zavod'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.uuid');
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$nasiya}", ['kind' => 'corporate'])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        // Review S4: the till's lookup is not gated (an existing account is still charged); members are (setup)
        $this->api($this->manager, 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=1234567890')->assertNotFound()->assertJsonPath('data.reason', 'member_not_found');
        // A nasiya account has no members
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->fresh()->pos_settings, FormatProfile::STOLOVAYA, [])])->save();
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$nasiya}/members", ['name' => 'Aziz'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonPath('data.reason', 'account_not_corporate');
    }

    public function test_members_their_talon_codes_and_the_bulk_paste(): void
    {
        $uuid = $this->company();
        $aziz = $this->member($uuid, ['phone' => '+998 90 111 22 33', 'daily_limit' => '50000']);
        $this->assertMatchesRegularExpression('/^\d{10}$/', $aziz['code']);
        $this->assertSame('+998901112233', $aziz['phone']);
        $this->assertSame('50000.00', $aziz['daily_limit']);
        $own = $this->member($uuid, ['name' => 'Bobur', 'code' => 'nav-0002', 'meals_per_day' => 1]);
        $this->assertSame('NAV-0002', $own['code']);

        // Codes and phones are unique in the store
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members", ['name' => 'X', 'code' => 'NAV-0002'], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members", ['name' => 'X', 'phone' => '901112233'], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members", ['name' => 'X', 'code' => 'a b'], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('code');

        // Bulk: all or nothing
        $bad = $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members/bulk", [
            'lines' => "Dilnoza;90 222 33 44\n;901110000\nSardor;12\nOtabek;901112233\nDilnoza 2;902223344",
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonPath('data.reason', 'bulk_invalid');
        $this->assertSame([[2, 'name_required'], [3, 'phone_invalid'], [4, 'phone_taken'], [5, 'phone_duplicate']], array_map(fn (array $line) => [$line['line'], $line['reason']], $bad->json('data.lines')));
        $this->assertSame(2, CustomerAccountMember::count());

        $ok = $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members/bulk", [
            'lines' => "Dilnoza;90 222 33 44\n\nSardor;\nOtabek\t903334455\tNAV-0100",
            'daily_limit' => '40000',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('data.created', 3);
        $this->assertSame(['Dilnoza', 'Sardor', 'Otabek'], array_column($ok->json('data.members'), 'name'));
        $this->assertSame('NAV-0100', $ok->json('data.members.2.code'));
        $this->assertSame('40000.00', $ok->json('data.members.1.daily_limit'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_account.members_imported']);

        // Deactivate, regenerate the code; the list (active first)
        $this->api($this->manager, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}/members/{$own['uuid']}", ['is_active' => false, 'regenerate_code' => true], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertNotSame('NAV-0002', CustomerAccountMember::where('uuid', $own['uuid'])->value('code'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_account.member_updated']);
        $list = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/members")->assertOk()->assertJsonPath('data.active_count', 4);
        $this->assertSame('Bobur', $list->json('data.members.4.name'));
        $this->assertCount(1, $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/members?q=dil")->json('data.members'));
        $this->assertCount(1, $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/members?active=0")->json('data.members'));
    }

    public function test_the_daily_limit_counts_by_the_tashkent_business_day(): void
    {
        $uuid = $this->company();
        $code = $this->member($uuid, ['daily_limit' => '60000'])['code'];

        // Friday 23:59 and Saturday 00:00 in Tashkent: still Friday's business day
        $this->at('2026-11-20 18:59:00');
        $this->charge($uuid, '25000', $code);
        $this->at('2026-11-20 19:00:00');
        $this->charge($uuid, '25000', $code);
        // 04:59: Friday still; 15 000 more is over 60 000
        $this->at('2026-11-20 23:59:00');
        $e = $this->refused(fn () => $this->charge($uuid, '15000', $code));
        $this->assertSame('member_daily_limit', $e->reason);
        $this->assertSame(['daily_limit' => '60000.00', 'used' => '50000.00', 'available' => '10000.00', 'business_date' => '2026-11-20'], $e->context);
        $this->charge($uuid, '10000', $code);
        // 05:00 Saturday: a new day
        $this->at('2026-11-21 00:00:00');
        $this->charge($uuid, '25000', $code);

        $this->assertSame(['2026-11-20', '2026-11-20', '2026-11-20', '2026-11-21'], CustomerAccountEntry::orderBy('id')->get()->map(fn ($entry) => $entry->business_date->toDateString())->all());
        $this->assertSame('85000.00', (string) CustomerAccount::where('uuid', $uuid)->value('balance'));
        $this->assertSame(4, CustomerAccountEntry::whereNotNull('member_id')->count());
    }

    public function test_meals_per_day_the_member_rules_and_a_refund_giving_the_meal_back(): void
    {
        $uuid = $this->company(['talon_mode' => 'subscription']);
        $member = $this->member($uuid, ['phone' => '901112233', 'meals_per_day' => 1]);

        // A charge must name the member (talon mode), by code or phone
        $this->assertSame('member_required', $this->refused(fn () => $this->charge($uuid, '30000'))->reason);
        $this->assertSame('member_not_found', $this->refused(fn () => $this->charge($uuid, '30000', 'NOPE-1'))->reason);
        $bill = $this->charge($uuid, '30000', null, null, '+998 90 111 22 33');
        $e = $this->refused(fn () => $this->charge($uuid, '30000', $member['code']));
        $this->assertSame('member_meals_used', $e->reason);
        $this->assertSame(422, $e->status);

        // The lunch is refunded at the till: the meal comes back
        app(CustomerAccountLedger::class)->reverse($uuid, Money::of('30000'), $bill, 'refund-1', (int) $this->manager->id);
        $this->assertSame('2026-11-20', CustomerAccountEntry::where('type', 'adjustment')->firstOrFail()->business_date->toDateString());
        $this->charge($uuid, '30000', $member['code']);

        // Deactivated: refused even on a new day
        CustomerAccountMember::where('uuid', $member['uuid'])->update(['is_active' => false]);
        $this->at('2026-11-21 07:00:00');
        $this->assertSame('member_inactive', $this->refused(fn () => $this->charge($uuid, '30000', $member['code']))->reason);

        // Another company's member is not this account's
        $other = $this->company(['name' => 'Boshqa korxona', 'talon_mode' => 'none']);
        $this->assertSame('member_not_found', $this->refused(fn () => $this->charge($other, '30000', $member['code']))->reason);
        // Without talons the company is charged as a whole
        $this->charge($other, '30000');
    }

    public function test_the_monthly_and_credit_limits(): void
    {
        $uuid = $this->company(['monthly_limit' => '100000', 'talon_mode' => 'none', 'credit_limit' => '150000']);
        $this->at('2026-11-30 12:00:00');
        $this->charge($uuid, '80000');
        $e = $this->refused(fn () => $this->charge($uuid, '30000'));
        $this->assertSame('account_monthly_limit', $e->reason);
        $this->assertSame(['monthly_limit' => '100000.00', 'used' => '80000.00', 'available' => '20000.00'], $e->context);

        // 1 December at 04:59 Tashkent is still November's business day; at 05:00 December starts
        $this->at('2026-11-30 23:59:00');
        $this->assertSame('account_monthly_limit', $this->refused(fn () => $this->charge($uuid, '30000'))->reason);
        $this->at('2026-12-01 00:00:00');
        $this->charge($uuid, '30000');
        // The debt is over the credit limit (no repayment yet)
        $e = $this->refused(fn () => $this->charge($uuid, '50000'));
        $this->assertSame('account_credit_limit', $e->reason);
        $this->assertSame('40000.00', $e->context['available']);

        // Blocked
        CustomerAccount::where('uuid', $uuid)->update(['is_blocked' => true]);
        $this->assertSame('account_blocked', $this->refused(fn () => $this->charge($uuid, '1000'))->reason);
    }

    public function test_the_till_looks_a_member_up_with_what_is_left(): void
    {
        $uuid = $this->company(['monthly_limit' => '1000000']);
        $member = $this->member($uuid, ['phone' => '901112233', 'daily_limit' => '50000', 'meals_per_day' => 2]);
        $this->charge($uuid, '20000', $member['code']);

        $session = $this->terminalSession($this->cashier, $this->branch);
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=' . strtolower($member['code']))->assertOk()
            ->assertJsonPath('data.account.uuid', $uuid)
            ->assertJsonPath('data.account.charge_approval', false)
            ->assertJsonPath('data.member.name', 'Aziz Rahimov')
            ->assertJsonPath('data.member.phone', '+998 90 *** ** 33')
            ->assertJsonPath('data.usage.business_date', '2026-11-20')
            ->assertJsonPath('data.usage.member.amount', '20000.00')
            ->assertJsonPath('data.usage.member.daily_available', '30000.00')
            ->assertJsonPath('data.usage.member.meals_left', 1)
            ->assertJsonPath('data.usage.month.available', '980000.00');
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?phone=90%20111%2022%2033')->assertOk()->assertJsonPath('data.member.uuid', $member['uuid']);
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=0000000000')->assertNotFound()->assertJsonPath('data.reason', 'member_not_found');
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup')->assertStatus(422);

        // A cashier cannot set members up
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members", ['name' => 'X'], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_other_stores_and_branches_never_see_a_member(): void
    {
        $uuid = $this->company();
        $member = $this->member($uuid);
        $this->foreignBranch->forceFill(['pos_settings' => PosSettings::withFormat($this->foreignBranch->fresh()->pos_settings, FormatProfile::STOLOVAYA, [])])->save();

        $this->api($this->foreignOwner, 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=' . $member['code'])->assertNotFound();
        $this->api($this->foreignOwner, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/members")->assertNotFound();
        $this->api($this->foreignOwner, 'PATCH', "/api/v1/pos/customer-accounts/{$uuid}/members/{$member['uuid']}", ['name' => 'X'], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
        $this->api($this->foreignOwner, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertNotFound();
        // The account is the branch's: the other branch's cashier does not find its people
        $otherCashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);
        $session = $this->terminalSession($otherCashier, $this->otherBranch);
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=' . $member['code'])->assertNotFound();

        // The same code in another store is that store's own member
        $foreign = CustomerAccount::create(['store_id' => $this->foreignStore->id, 'name' => 'F', 'kind' => 'corporate']);
        CustomerAccountMember::create(['store_id' => $this->foreignStore->id, 'customer_account_id' => $foreign->id, 'name' => 'F1', 'code' => 'SAME-1']);
        $this->member($uuid, ['name' => 'Bizniki', 'code' => 'SAME-1']);
        $this->charge($uuid, '1000', 'SAME-1');
        $this->assertSame('Bizniki', CustomerAccountEntry::latest('id')->firstOrFail()->member->name);
        // and charging the foreign account with our approver is a 404 like any other store's account
        $this->assertSame('account_not_found', $this->refused(fn () => $this->charge((string) $foreign->uuid, '1000', 'SAME-1'))->reason);
    }

    public function test_the_monthly_statement_and_its_excel_file(): void
    {
        $uuid = $this->company(['talon_mode' => 'daily_limit']);
        $aziz = $this->member($uuid, ['name' => 'Aziz']);
        $bobur = $this->member($uuid, ['name' => 'Bobur']);
        $this->member($uuid, ['name' => 'Charos']); // ate nothing: still a row

        $this->at('2026-11-02 07:00:00');
        $this->charge($uuid, '30000', $aziz['code']);
        $refunded = $this->charge($uuid, '25000', $bobur['code']);
        $this->at('2026-11-02 23:30:00'); // 04:30 on the 3rd: the 2nd's business day
        $this->charge($uuid, '12000', $aziz['code']);
        $this->at('2026-11-03 07:00:00');
        $this->charge($uuid, '28000', $bobur['code']);
        app(CustomerAccountLedger::class)->reverse($uuid, Money::of('25000'), $refunded, 'refund-1', (int) $this->manager->id);
        // Outside the month: October and December
        $this->at('2026-10-31 07:00:00');
        $this->charge($uuid, '99000', $aziz['code']);
        $this->at('2026-12-01 07:00:00');
        $this->charge($uuid, '77000', $aziz['code']);
        // A repayment in November
        $this->at('2026-11-20 07:00:00');
        $this->api($this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/repayments", ['amount' => '50000', 'tender' => 'cash'], ['Idempotency-Key' => 'repay-1'])->assertCreated();

        $statement = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement?month=2026-11")->assertOk()->json('data');
        $this->assertSame(['2026-11-01', '2026-11-30', 30], [$statement['from'], $statement['to'], count($statement['days'])]);
        $this->assertSame('"Navoiy Metall" MChJ', $statement['account']['company_name']);
        $this->assertSame('301234567', $statement['account']['inn']);
        $rows = collect($statement['rows'])->keyBy(fn (array $row) => $row['member']['name'] ?? '-');
        $this->assertSame([['date' => '2026-11-02', 'amount' => '42000.00', 'meals' => 2]], $rows['Aziz']['cells']);
        $this->assertSame(['42000.00', 2], [$rows['Aziz']['amount'], $rows['Aziz']['meals']]);
        // The refunded lunch is netted out of the 2nd
        $this->assertSame([['date' => '2026-11-02', 'amount' => '0.00', 'meals' => 0], ['date' => '2026-11-03', 'amount' => '28000.00', 'meals' => 1]], $rows['Bobur']['cells']);
        $this->assertSame([[], '0.00', 0], [$rows['Charos']['cells'], $rows['Charos']['amount'], $rows['Charos']['meals']]);
        $this->assertSame([['date' => '2026-11-02', 'amount' => '42000.00', 'meals' => 2], ['date' => '2026-11-03', 'amount' => '28000.00', 'meals' => 1]], $statement['day_totals']);
        $this->assertSame(['70000.00', 3, '50000.00'], [$statement['total'], $statement['meals'], $statement['repaid_total']]);
        // The debt now: 30+25+12+28-25+99+77-50
        $this->assertSame('196000.00', $statement['balance']);

        // Excel
        $response = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement/export?month=2026-11")->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('korxona-navoiy-zavodi-2026-11.xlsx', (string) $response->headers->get('Content-Disposition'));
        $reader = new Reader();
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        $this->assertSame(['Hisobot', 'Hisob-faktura'], array_keys($sheets));
        $header = $sheets['Hisobot'][0];
        $this->assertSame(['Xodim', 'Talon kodi', '1', '2', '3'], array_slice($header, 0, 5));
        $this->assertSame(["Jami (so'm)", 'Ovqatlar'], array_slice($header, -2));
        $this->assertCount(2 + 30 + 2, $header);
        $azizRow = collect($sheets['Hisobot'])->first(fn (array $row) => $row[0] === 'Aziz');
        $this->assertSame($aziz['code'], $azizRow[1]);
        $this->assertEquals(42000, $azizRow[3]); // the 2nd
        $this->assertEquals([42000, 2], array_slice($azizRow, -2));
        $total = end($sheets['Hisobot']);
        $this->assertSame('Jami', $total[0]);
        $this->assertEquals([70000, 3], array_slice($total, -2));
        $invoice = collect($sheets['Hisob-faktura'])->mapWithKeys(fn (array $row) => [(string) ($row[0] ?? '') => $row[1] ?? null]);
        $this->assertSame('301234567', $invoice['STIR']);
        $this->assertSame('+998 90 123 45 67', $invoice['Telefon']);
        $this->assertEquals(196000, $invoice["Hozirgi qarz (so'm)"]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_account.statement_exported']);

        // reports.view: a cashier may not
        $this->api($this->cashier, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertForbidden();
        $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement?month=2026-13")->assertStatus(422);
    }
}
