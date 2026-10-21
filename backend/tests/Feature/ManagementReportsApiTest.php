<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Auth\PermissionService;
use App\Services\Order\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * P2-12: sales reports and their CSV download for the partner web panel (reports.view): days of the store's timezone,
 * money as strings, the same revenue rule as the Mini App's analytics (delivered orders).
 */
class ManagementReportsApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-21 07:00:00', 'UTC'));
        Cache::flush();
        $this->setUpManagement();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function sale(OrderStatus $status, string $createdAtUtc, array $attributes = [], string $name = 'Cheeseburger', int|string $quantity = 1, int $unit = 50000): Order
    {
        $total = (int) round($unit * (float) $quantity);
        $order = $this->makeOrder($status, array_merge([
            'created_at' => $createdAtUtc,
            'updated_at' => $createdAtUtc,
            'subtotal' => $total,
            'total_price' => $total,
        ], $attributes));
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'total_price' => $total,
        ]);

        return $order;
    }

    private function seedSales(): void
    {
        // 2026-10-20 00:30 in Tashkent (UTC+5): counts on the 20th, not the 19th
        $this->sale(OrderStatus::DELIVERED, '2026-10-19 19:30:00', ['channel' => 'telegram', 'payment_method' => 'cash']);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 08:00:00', ['channel' => 'mobile', 'payment_method' => 'click'], 'Osh', '1.5', 60000);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 09:00:00', ['channel' => 'pos_hall', 'branch_id' => $this->otherBranch->id], 'Osh', 2, 60000);
        $this->sale(OrderStatus::CANCELED, '2026-10-20 10:00:00', ['channel' => 'telegram']);
        $this->sale(OrderStatus::PREPARING, '2026-10-21 06:00:00', ['channel' => 'telegram']);
        // 2026-10-19 in Tashkent: outside a 20th–21st period
        $this->sale(OrderStatus::DELIVERED, '2026-10-19 18:00:00', ['channel' => 'telegram']);
        // Another store's order on the same day
        Order::create([
            'store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id, 'user_id' => $this->customer->id,
            'status' => OrderStatus::DELIVERED, 'order_type' => 'delivery', 'payment_method' => 'cash', 'total_price' => 999000,
            'created_at' => '2026-10-20 08:00:00', 'updated_at' => '2026-10-20 08:00:00',
        ]);
    }

    public function test_sales_by_period_channel_and_branch_in_the_stores_timezone(): void
    {
        $this->seedSales();

        $data = $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-21')->assertOk()->json('data');

        $this->assertSame(['from' => '2026-10-20', 'to' => '2026-10-21', 'days' => 2, 'timezone' => 'Asia/Tashkent', 'basis' => 'created_at', 'group_by' => 'day'], $data['period']);
        $this->assertSame(5, $data['summary']['orders_count']);
        $this->assertSame(3, $data['summary']['delivered_count']);
        $this->assertSame(1, $data['summary']['canceled_count']);
        $this->assertSame(1, $data['summary']['active_count']);
        // 50 000 + 1.5 × 60 000 + 2 × 60 000
        $this->assertSame('260000.00', $data['summary']['revenue']);
        $this->assertSame('86666.67', $data['summary']['average_check']);
        $this->assertSame([
            ['date' => '2026-10-20', 'orders' => 4, 'delivered' => 3, 'revenue' => '260000.00'],
            ['date' => '2026-10-21', 'orders' => 1, 'delivered' => 0, 'revenue' => '0.00'],
        ], $data['series']);
        $this->assertSame([
            ['channel' => 'pos_hall', 'label' => 'Zal (POS)', 'orders' => 1, 'revenue' => '120000.00'],
            ['channel' => 'mobile', 'label' => 'Mobil ilova', 'orders' => 1, 'revenue' => '90000.00'],
            ['channel' => 'telegram', 'label' => 'Telegram', 'orders' => 1, 'revenue' => '50000.00'],
        ], $data['by_channel']);
        $this->assertSame([
            ['branch_id' => $this->branch->id, 'name' => 'Main Branch', 'orders' => 4, 'delivered' => 2, 'revenue' => '140000.00'],
            ['branch_id' => $this->otherBranch->id, 'name' => 'Second Branch', 'orders' => 1, 'delivered' => 1, 'revenue' => '120000.00'],
        ], $data['by_branch']);

        // The same figure as the Mini App's analytics for that day
        $analytics = $this->as($this->owner, 'GET', '/api/v1/owner/analytics?period=custom&from=2026-10-20&to=2026-10-20')->json('data.summary.revenue');
        $this->assertEquals(260000, $analytics);

        // A store in another timezone: the 19:30 UTC order falls on the 19th there (UTC+4)
        $this->store->update(['timezone' => 'Asia/Dubai']);
        $dubai = $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-20')->json('data');
        $this->assertSame('Asia/Dubai', $dubai['period']['timezone']);
        $this->assertSame('210000.00', $dubai['summary']['revenue']);
    }

    public function test_weekly_and_monthly_series(): void
    {
        $this->seedSales();

        $weeks = $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-10-01&to=2026-10-21&group_by=week')->assertOk()->json('data.series');
        $this->assertSame('2026-09-28', $weeks[0]['date']);
        $this->assertSame('2026-10-19', end($weeks)['date']);
        $this->assertSame('310000.00', end($weeks)['revenue']);

        $months = $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-01-01&to=2026-10-21&group_by=month')->assertOk()->json('data.series');
        $this->assertCount(10, $months);
        $this->assertSame(['date' => '2026-10-01', 'orders' => 6, 'delivered' => 4, 'revenue' => '310000.00'], $months[9]);
    }

    public function test_top_products_by_quantity_or_revenue(): void
    {
        $this->seedSales();

        $byQuantity = $this->as($this->owner, 'GET', '/api/v1/owner/reports/top-products?from=2026-10-20&to=2026-10-21')->assertOk()->json('data.products');
        $this->assertSame([
            ['name' => 'Osh', 'quantity' => 3.5, 'revenue' => '210000.00', 'orders' => 2],
            ['name' => 'Cheeseburger', 'quantity' => 1, 'revenue' => '50000.00', 'orders' => 1],
        ], $byQuantity);

        $limited = $this->as($this->owner, 'GET', '/api/v1/owner/reports/top-products?from=2026-10-20&to=2026-10-21&sort=revenue&limit=1')->assertOk()->json('data');
        $this->assertSame('revenue', $limited['sort']);
        $this->assertSame(['Osh'], array_column($limited['products'], 'name'));
    }

    public function test_a_branch_manager_sees_their_branch_only(): void
    {
        $this->seedSales();

        $data = $this->as($this->manager, 'GET', '/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-21')->assertOk()->json('data');
        $this->assertSame($this->branch->id, $data['branch_id']);
        $this->assertSame('140000.00', $data['summary']['revenue']);

        $this->as($this->manager, 'GET', "/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-21&branch_id={$this->otherBranch->id}")->assertForbidden();
        $this->as($this->owner, 'GET', "/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-21&branch_id={$this->otherBranch->id}")
            ->assertOk()->assertJsonPath('data.summary.revenue', '120000.00');
        // Another store's branch: not found
        $this->as($this->owner, 'GET', "/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-21&branch_id={$this->foreignBranch->id}")->assertNotFound();
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=orders&from=2026-10-20&to=2026-10-21&branch_id=' . $this->foreignBranch->id)->assertNotFound();
    }

    public function test_reports_need_reports_view(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $query = '?from=2026-10-20&to=2026-10-21';

        foreach ([$cashier, $this->courier, $this->customer] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/reports/sales' . $query)->assertForbidden();
            $this->as($user, 'GET', '/api/v1/owner/reports/top-products' . $query)->assertForbidden();
            $this->as($user, 'GET', '/api/v1/owner/reports/export' . $query . '&report=sales')->assertForbidden();
        }
        $this->as($this->foreignOwner, 'GET', '/api/v1/owner/reports/sales' . $query)->assertOk()->assertJsonPath('data.summary.orders_count', 0);

        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::REPORTS_VIEW, false);
        $this->as($this->manager, 'GET', '/api/v1/owner/reports/sales' . $query)->assertForbidden();

        app(PermissionService::class)->setOverride($this->store, UserRole::CASHIER, Permission::REPORTS_VIEW, true);
        $this->as($cashier, 'GET', '/api/v1/owner/reports/sales' . $query)->assertOk()->assertJsonPath('data.branch_id', $this->branch->id);
    }

    public function test_the_period_is_validated_and_bounded(): void
    {
        $cases = [
            '' => 'from',
            '?from=2026-10-20' => 'to',
            '?from=20.10.2026&to=2026-10-21' => 'from',
            '?from=2026-10-21&to=2026-10-20' => 'to',
            '?from=2025-01-01&to=2026-10-21' => 'to',
            '?from=2026-10-01&to=2026-10-21&group_by=hour' => 'group_by',
            '?from=2026-10-01&to=2026-10-21&branch_id=99999999999999999999' => 'branch_id',
        ];
        foreach ($cases as $query => $field) {
            $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales' . $query)->assertStatus(422)->assertJsonValidationErrors($field);
        }

        $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2025-01-01&to=2026-10-21')
            ->assertJsonPath('errors.to.0', "Davr ko'pi bilan 366 kun bo'lishi mumkin.");
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/top-products?from=2026-10-01&to=2026-10-21&limit=51')->assertStatus(422)->assertJsonValidationErrors('limit');

        // The orders CSV is capped at 93 days, the sales one at 366
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=orders&from=2026-06-01&to=2026-10-21')
            ->assertStatus(422)->assertJsonPath('errors.to.0', "Davr ko'pi bilan 93 kun bo'lishi mumkin.");
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=sales&from=2026-06-01&to=2026-10-21')->assertOk();
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=pdf&from=2026-10-01&to=2026-10-21')->assertStatus(422)->assertJsonValidationErrors('report');
    }

    public function test_the_orders_csv(): void
    {
        $this->seedSales();
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 11:00:00', ['channel' => 'mobile'], '=HYPERLINK("http://evil")');

        $response = $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=orders&from=2026-10-20&to=2026-10-21')->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('rizqim_orders_20261020_20261021.csv', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", substr($csv, 3)))));
        $this->assertSame('Buyurtma', $rows[0][0]);
        $this->assertSame('Jami (UZS)', $rows[0][13]);
        $this->assertCount(7, $rows); // header + the store's 6 orders of the period; not the 19th's, not the foreign one

        // Newest first (the last placed); times in Tashkent; money with two decimals
        $this->assertSame('2026-10-20 16:00', $rows[1][1]);
        $last = end($rows);
        $this->assertSame('2026-10-20 00:30', $last[1]);
        $this->assertSame('Main Branch', $last[2]);
        $this->assertSame('Telegram', $last[3]);
        $this->assertSame('Yetkazildi', $last[5]);
        $this->assertSame('50000.00', $last[13]);
        $this->assertStringNotContainsString('999000', $csv);
    }

    public function test_the_sales_and_products_csv_and_formula_cells(): void
    {
        $this->seedSales();
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 11:00:00', ['channel' => 'mobile'], '=HYPERLINK("http://evil")', 1, 1000);

        $sales = $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=sales&from=2026-10-20&to=2026-10-21')->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", substr($sales, 3)))));
        $this->assertSame(['Davr', 'Buyurtmalar', 'Yetkazilgan', 'Tushum (UZS)'], $rows[0]);
        $this->assertSame(['2026-10-20', '5', '4', '261000.00'], $rows[1]);
        $this->assertSame(['Jami', '6', '4', '261000.00'], end($rows));

        $products = $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=products&from=2026-10-20&to=2026-10-21')->assertOk()->streamedContent();
        $this->assertStringContainsString('Osh,3.5,2,210000.00', $products);
        // A name a spreadsheet would run as a formula is written as text
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\"", $products);
    }

    public function test_an_orders_csv_with_too_many_rows_is_refused(): void
    {
        $this->partialMock(SalesReportService::class, fn ($mock) => $mock->shouldReceive('countOrders')->andReturn(SalesReportService::EXPORT_MAX_ROWS + 1));

        $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=orders&from=2026-10-01&to=2026-10-21')
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'too_many_rows')
            ->assertJsonPath('data.limit', SalesReportService::EXPORT_MAX_ROWS);
    }

    public function test_csv_downloads_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=sales&from=2026-10-20&to=2026-10-21')->assertOk();
        }
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/export?report=sales&from=2026-10-20&to=2026-10-21')->assertStatus(429);
        // The JSON reports are not counted against it
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-21')->assertOk();
    }
}
