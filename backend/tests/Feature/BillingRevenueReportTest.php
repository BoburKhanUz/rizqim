<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\UserRole;
use App\Filament\Superadmin\Pages\RevenueReport;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Finance\RevenueReportService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1-23: Rizqim's income per month and per store, commission vs subscription, in Tashkent months.
 */
class BillingRevenueReportTest extends TestCase
{
    use RefreshDatabase;
    use BillingFixtures;

    private Store $a;
    private Store $b;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->billingStore('Alpha');
        $this->b = $this->billingStore('Beta');
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998901112233', 'password' => bcrypt('x')]);

        $this->delivered($this->a, '5000.25', '2026-10-15 12:00');
        $this->delivered($this->a, '1000.00', '2026-10-01 01:00');                        // 2026-09-30 20:00 UTC: October in Tashkent
        $this->delivered($this->a, '9999.00', '2026-10-20 12:00', OrderStatus::REFUNDED);  // refunded: not income
        $this->delivered($this->a, '7777.00', '2026-11-01 00:00');                        // November
        $this->delivered($this->b, '3000.00', '2026-09-10 12:00');
        $this->delivered($this->b, '400.00', '2026-10-31 23:59');

        $this->invoice($this->a, '2026-10', '100000.00', SubscriptionInvoiceStatus::ISSUED);
        $this->invoice($this->b, '2026-10', '50000.50', SubscriptionInvoiceStatus::PAID);
        $this->invoice($this->b, '2026-09', '20000.00', SubscriptionInvoiceStatus::VOID);
        $this->invoice($this->a, '2026-09', '30000.00', SubscriptionInvoiceStatus::ISSUED);
    }

    private function delivered(Store $store, string $commission, string $deliveredAt, OrderStatus $status = OrderStatus::DELIVERED): void
    {
        $order = Order::create([
            'store_id' => $store->id,
            'user_id' => $this->customer->id,
            'status' => $status,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 100000,
            'commission_amount' => $commission,
        ]);

        DB::table('order_status_histories')->insert([
            'order_id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
            'created_at' => $this->tashkent($deliveredAt),
            'updated_at' => $this->tashkent($deliveredAt),
        ]);
    }

    private function invoice(Store $store, string $period, string $amount, SubscriptionInvoiceStatus $status): void
    {
        $start = $this->tashkent("{$period}-01");

        SubscriptionInvoice::create([
            'number' => "T-{$period}-{$store->id}",
            'store_id' => $store->id,
            'period' => $period,
            'period_start' => $start,
            'period_end' => $start->copy()->addMonth(),
            'terminals_count' => 0,
            'included_terminals' => 0,
            'base_fee' => $amount,
            'fee_per_terminal' => 0,
            'lines' => [],
            'amount' => $amount,
            'status' => $status,
            'due_date' => '2026-12-01',
            'issued_at' => $start,
        ]);
    }

    public function test_income_per_month(): void
    {
        $rows = app(RevenueReportService::class)->monthly('2026-08', '2026-11');

        $this->assertSame(['2026-08', '2026-09', '2026-10', '2026-11'], array_column($rows, 'period'));
        $this->assertSame(
            [
                ['0.00', '0.00', '0.00'],
                ['3000.00', '30000.00', '33000.00'],
                ['6400.25', '150000.50', '156400.75'],
                ['7777.00', '0.00', '7777.00'],
            ],
            array_map(fn (array $row) => [$row['commission']->toString(), $row['subscription']->toString(), $row['total']->toString()], $rows)
        );
    }

    public function test_income_per_store_for_a_month(): void
    {
        $rows = app(RevenueReportService::class)->byStore('2026-10');

        $this->assertSame(
            [
                ['Alpha', '6000.25', '100000.00', '106000.25'],
                ['Beta', '400.00', '50000.50', '50400.50'],
            ],
            array_map(fn (array $row) => [$row['store_name'], $row['commission']->toString(), $row['subscription']->toString(), $row['total']->toString()], $rows)
        );
        $this->assertSame([], app(RevenueReportService::class)->byStore('2025-01'));
    }

    public function test_the_superadmin_page_shows_the_report(): void
    {
        $admin = User::create(['name' => 'Admin', 'phone' => '+998901110005', 'password' => bcrypt('x'), 'role' => UserRole::SUPER_ADMIN->value]);
        Filament::setCurrentPanel('superadmin');
        $this->actingAs($admin);

        Livewire::test(RevenueReport::class)
            ->set('from', '2026-09')
            ->set('to', '2026-10')
            ->set('period', '2026-10')
            ->assertSee('156 401 UZS')
            ->assertSee('Alpha')
            ->assertSee('106 000 UZS');
    }
}
