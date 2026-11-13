<?php

namespace Tests\Feature\PosCorporate;

use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\UserRole;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountMember;
use App\Models\RolePermissionOverride;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\PosBooking\PosBookingFixtures;
use Tests\TestCase;

/**
 * Review fixes of P6 wave 4 (corporate accounts): talon codes, INN and the contact phone masked for those who only read
 * reports (S1, S2), the till's member lookup works with the module off (S4) and gives no talon code for a phone (S5),
 * a refund of an account charge is never refused for a repaid debt (C7), a branch-bound reader's statement of a
 * store-wide account (only their branch's charges).
 */
class PosCorporateReviewFixesTest extends TestCase
{
    use PosBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
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
    private function company(array $data = [], ?\App\Models\User $as = null): string
    {
        return (string) $this->api($as ?? $this->manager, 'POST', '/api/v1/pos/customer-accounts', $data + [
            'name' => 'Navoiy zavodi',
            'kind' => 'corporate',
            'company_name' => '"Navoiy Metall" MChJ',
            'inn' => '301234567',
            'contact_name' => 'Dilshod Karimov',
            'contact_phone' => '90 123 45 67',
            'talon_mode' => 'daily_limit',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.uuid');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function member(string $account, array $data = [], ?\App\Models\User $as = null): array
    {
        return $this->api($as ?? $this->manager, 'POST', "/api/v1/pos/customer-accounts/{$account}/members", $data + ['name' => 'Aziz'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()->json('data');
    }

    private function charge(string $account, string $amount, ?string $code, ?int $approver = null): string
    {
        $bill = (string) Str::uuid();
        app(CustomerAccountLedger::class)->charge($account, Money::of($amount), $bill, $approver ?? (int) $this->manager->id, $code);

        return $bill;
    }

    private function reportsOnlyCashier(): void
    {
        RolePermissionOverride::create(['store_id' => $this->store->id, 'role' => UserRole::CASHIER->value, 'permission' => 'reports.view', 'allowed' => true]);
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function sheets(\Illuminate\Testing\TestResponse $response): array
    {
        $reader = new Reader();
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }

    public function test_a_reports_only_reader_gets_talon_codes_inn_and_phone_masked(): void
    {
        $uuid = $this->company();
        $aziz = $this->member($uuid, ['name' => 'Aziz', 'code' => '4815162342']);
        $this->charge($uuid, '30000', $aziz['code']);
        $this->reportsOnlyCashier();

        // S1 + S2: JSON
        $statement = $this->api($this->cashier, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertOk()->json('data');
        $this->assertSame('48******42', $statement['rows'][0]['member']['code']);
        $this->assertSame('30*****67', $statement['account']['inn']);
        $this->assertSame('+998 90 *** ** 67', $statement['account']['contact_phone']);

        // ...and the Excel file
        $sheets = $this->sheets($this->api($this->cashier, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement/export")->assertOk());
        $row = collect($sheets['Hisobot'])->first(fn (array $row) => $row[0] === 'Aziz');
        $this->assertSame('48******42', $row[1]);
        $invoice = collect($sheets['Hisob-faktura'])->mapWithKeys(fn (array $row) => [(string) ($row[0] ?? '') => $row[1] ?? null]);
        $this->assertSame('30*****67', $invoice['STIR']);
        $this->assertSame('+998 90 *** ** 67', $invoice['Telefon']);
        $this->assertStringNotContainsString('4815162342', json_encode($sheets));

        // The manager (customer_accounts.manage) sees them in full
        $full = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertOk()->json('data');
        $this->assertSame(['4815162342', '301234567', '+998901234567'], [$full['rows'][0]['member']['code'], $full['account']['inn'], $full['account']['contact_phone']]);
        $sheets = $this->sheets($this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement/export")->assertOk());
        $this->assertSame('4815162342', collect($sheets['Hisobot'])->first(fn (array $row) => $row[0] === 'Aziz')[1]);
    }

    public function test_the_till_finds_a_member_with_the_module_off_and_a_phone_gives_no_talon_code(): void
    {
        $uuid = $this->company();
        $aziz = $this->member($uuid, ['phone' => '901112233', 'code' => '5550001111']);
        $session = $this->terminalSession($this->cashier, $this->branch);

        // S5: by phone the talon code is masked; by code it is the code typed
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?phone=901112233')->assertOk()
            ->assertJsonPath('data.member.uuid', $aziz['uuid'])->assertJsonPath('data.member.code', '55******11');
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=5550001111')->assertOk()->assertJsonPath('data.member.code', '5550001111');

        // S4: the owner switches the corporate module off: existing accounts are still charged from the till
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->fresh()->pos_settings, FormatProfile::CHOYXONA, [])])->save();
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/customer-accounts/members/lookup?code=5550001111')->assertOk()->assertJsonPath('data.account.uuid', $uuid);
        // Setting members up stays gated
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/members", ['name' => 'X'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_a_refund_after_the_company_paid_its_invoice_leaves_an_overpayment(): void
    {
        $uuid = $this->company();
        $aziz = $this->member($uuid, ['daily_limit' => '100000']);
        $lunch = $this->charge($uuid, '30000', $aziz['code']);
        $this->api($this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/repayments", ['amount' => '30000', 'tender' => 'cash'], ['Idempotency-Key' => 'repay-1'])->assertCreated();

        // C7: never refused; the company is owed 30 000 (Ortiqcha to'lov)
        app(CustomerAccountLedger::class)->reverse($uuid, Money::of('30000'), $lunch, 'refund-1', (int) $this->manager->id);
        $this->assertSame('-30000.00', (string) CustomerAccount::where('uuid', $uuid)->value('balance'));
        $statement = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertOk()->json('data');
        $this->assertSame(['0.00', 0, '30000.00', '-30000.00'], [$statement['total'], $statement['meals'], $statement['repaid_total'], $statement['balance']]);
        $sheets = $this->sheets($this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement/export")->assertOk());
        $invoice = collect($sheets['Hisob-faktura'])->mapWithKeys(fn (array $row) => [(string) ($row[0] ?? '') => $row[1] ?? null]);
        $this->assertEquals(30000, $invoice["Ortiqcha to'lov (so'm)"]);

        // The next lunch uses the credit; a manager may still correct up
        $this->charge($uuid, '20000', $aziz['code']);
        $this->assertSame('-10000.00', (string) CustomerAccount::where('uuid', $uuid)->value('balance'));
        $this->api($this->manager, 'POST', "/api/v1/pos/customer-accounts/{$uuid}/adjustments", ['amount' => '5000', 'note' => 'Tuzatish'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame('-5000.00', (string) CustomerAccount::where('uuid', $uuid)->value('balance'));

        // The same for nasiya
        $nasiya = (string) $this->api($this->manager, 'POST', '/api/v1/pos/customer-accounts', ['name' => 'Akmal aka'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.uuid');
        $bill = (string) Str::uuid();
        app(CustomerAccountLedger::class)->charge($nasiya, Money::of('40000'), $bill, (int) $this->manager->id);
        $this->api($this->cashier, 'POST', "/api/v1/pos/customer-accounts/{$nasiya}/repayments", ['amount' => '40000', 'tender' => 'cash'], ['Idempotency-Key' => 'repay-2'])->assertCreated();
        app(CustomerAccountLedger::class)->reverse($nasiya, Money::of('15000'), $bill, 'refund-2', (int) $this->manager->id);
        $this->assertSame('-15000.00', (string) CustomerAccount::where('uuid', $nasiya)->value('balance'));
    }

    public function test_a_branch_bound_reader_sees_only_their_branchs_charges_of_a_store_wide_account(): void
    {
        $uuid = $this->company([], $this->owner);
        $this->assertNull(CustomerAccount::where('uuid', $uuid)->value('branch_id'));
        $aziz = $this->member($uuid, ['name' => 'Aziz'], $this->owner);
        $other = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->charge($uuid, '30000', $aziz['code']);
        $this->charge($uuid, '45000', $aziz['code'], (int) $other->id);

        // The owner: every branch
        $all = $this->api($this->owner, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertOk()->json('data');
        $this->assertSame(['75000.00', 2, null], [$all['total'], $all['meals'], $all['branch_id']]);

        // A branch manager: only their branch's lunches, in the JSON and in the Excel file
        $own = $this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertOk()->json('data');
        $this->assertSame(['30000.00', 1, (int) $this->branch->id], [$own['total'], $own['meals'], $own['branch_id']]);
        $this->assertSame('30000.00', $own['rows'][0]['amount']);
        $sheets = $this->sheets($this->api($this->manager, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement/export")->assertOk());
        $this->assertEquals([30000, 1], array_slice(end($sheets['Hisobot']), -2));
        // The other branch's manager: theirs
        $this->api($other, 'GET', "/api/v1/pos/customer-accounts/{$uuid}/corporate-statement")->assertOk()->assertJsonPath('data.total', '45000.00');
        // A branch's own account stays closed to another branch
        $branchOwn = $this->company(['name' => 'Filial zavodi']);
        $this->api($other, 'GET', "/api/v1/pos/customer-accounts/{$branchOwn}/corporate-statement")->assertNotFound();
    }
}
