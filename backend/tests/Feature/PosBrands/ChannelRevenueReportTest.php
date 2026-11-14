<?php

namespace Tests\Feature\PosBrands;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * P6-55: the channel net revenue report (period × channel × brand) against fixed orders: the brand split of a
 * multi-brand order, the owner's aggregator commission versus the order's own RIZQIM commission, refunds, what is
 * left out (cancelled, another day, another store), the Excel workbook, the commissions settings (audited) and access.
 */
class ChannelRevenueReportTest extends TestCase
{
    use RefreshDatabase;
    use DarkKitchenFixtures;

    private ProductVariant $burger;

    private ProductVariant $lavash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDarkKitchen();

        [, $this->burger] = $this->brandDish($this->makeBrand('Burger Bro', 'BB'), 'Burger', '60000');
        [, $this->lavash] = $this->brandDish($this->makeBrand('Lavash Lab', 'LL'), 'Lavash', '40000');
    }

    /**
     * @param  list<array{0: ProductVariant, 1: string}>  $lines  variant, line total
     */
    private function sold(string $channel, OrderStatus $status, string $total, string $discount, string $commission, array $lines, array $attributes = []): Order
    {
        $order = $this->makeOrder($status, ['channel' => $channel, 'total_price' => $total, 'discount' => $discount, 'commission_amount' => $commission] + $attributes);
        foreach ($lines as [$variant, $amount]) {
            OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => $amount, 'total_price' => $amount]);
        }

        return $order;
    }

    private function fixedOrders(): void
    {
        // Telegram, two brands 60 000 + 40 000, discount 10 000, RIZQIM commission 9 000
        $this->sold('telegram', OrderStatus::DELIVERED, '90000', '10000', '9000', [[$this->burger, '60000'], [$this->lavash, '40000']]);
        // An aggregator's order: the owner's 25 % applies (12 500), not the order's own 0
        $this->sold('aggregator', OrderStatus::DELIVERED, '50000', '0', '0', [[$this->burger, '50000']]);
        // A house-brand order refunded in full
        $this->sold('telegram', OrderStatus::REFUNDED, '20000', '0', '2000', [[$this->variant, '20000']]);
        // Left out: cancelled, another day, another store
        $this->sold('telegram', OrderStatus::CANCELED, '30000', '0', '3000', [[$this->burger, '30000']]);
        $old = $this->sold('telegram', OrderStatus::DELIVERED, '30000', '0', '3000', [[$this->burger, '30000']]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $other = $this->makeStore('Other');
        $this->sold('telegram', OrderStatus::DELIVERED, '30000', '0', '3000', [[$this->burger, '30000']], ['store_id' => $other->id, 'branch_id' => $this->makeBranch($other, 'B')->id]);

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/channel-commissions', ['rates' => [['channel' => 'aggregator', 'percent' => '25', 'note' => 'Yandex Eats']]])
            ->assertOk()->assertJsonPath('data.rates', [['channel' => 'aggregator', 'percent' => '25.00', 'note' => 'Yandex Eats']]);
    }

    public function test_the_report_numbers_against_fixed_orders(): void
    {
        $this->fixedOrders();
        [$bb, $ll] = [$this->burger->product->brand_id, $this->lavash->product->brand_id];

        $data = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/reports/channel-revenue')->assertOk()->json('data');
        $rows = array_map(fn (array $row) => [$row['channel'], $row['brand_code'], $row['orders'], $row['gross'], $row['discounts'], $row['refunds'], $row['commission'], $row['net']], $data['rows']);
        $this->assertSame([
            ['aggregator', 'BB', 1, '50000.00', '0.00', '0.00', '12500.00', '37500.00'],
            ['telegram', null, 1, '20000.00', '0.00', '20000.00', '2000.00', '-2000.00'],
            ['telegram', 'BB', 1, '60000.00', '6000.00', '0.00', '5400.00', '48600.00'],
            ['telegram', 'LL', 1, '40000.00', '4000.00', '0.00', '3600.00', '32400.00'],
        ], $rows);
        $this->assertSame(['owner', 'billing', 'billing', 'billing'], array_column($data['rows'], 'commission_source'));
        $this->assertSame('25.00', $data['rows'][0]['commission_percent']);
        $this->assertSame([$bb, null, $bb, $ll], array_column($data['rows'], 'brand_id'));

        $channels = array_map(fn (array $row) => [$row['channel'], $row['orders'], $row['gross'], $row['net']], $data['by_channel']);
        $this->assertSame([['aggregator', 1, '50000.00', '37500.00'], ['telegram', 2, '120000.00', '79000.00']], $channels);
        $brands = array_map(fn (array $row) => [$row['brand_name'], $row['orders'], $row['net']], $data['by_brand']);
        $this->assertSame([[null, 1, '-2000.00'], ['Burger Bro', 2, '86100.00'], ['Lavash Lab', 1, '32400.00']], $brands);
        $this->assertSame(['orders' => 3, 'gross' => '170000.00', 'discounts' => '10000.00', 'refunds' => '20000.00', 'commission' => '23500.00', 'net' => '116500.00'], $data['totals']);

        // Another branch has none of them; a period without orders is empty
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/reports/channel-revenue?branch_id={$this->otherBranch->id}")->assertOk()->assertJsonPath('data.totals.orders', 0);
        $day = now()->subDays(3)->setTimezone('Asia/Tashkent')->toDateString();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/reports/channel-revenue?from={$day}&to={$day}")->assertOk()->assertJsonPath('data.totals.gross', '30000.00');
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/reports/channel-revenue?from=2026-01-10&to=2026-01-01')->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/reports/channel-revenue?from=2024-01-01&to=2026-01-01')->assertStatus(422);
    }

    public function test_the_excel_workbook_and_its_audit(): void
    {
        $this->fixedOrders();

        $response = $this->actingAs($this->owner, 'sanctum')->get('/api/v1/owner/reports/channel-revenue/export')->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $path = $response->baseResponse->getFile()->getPathname();

        $reader = new Reader();
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame(['Kanal va brend', 'Kanallar', 'Brendlar'], array_keys($sheets));
        $this->assertSame(['Agregator', '[BB] Burger Bro', 1, 50000, 0, 0, 12500, 25, 37500], $sheets['Kanal va brend'][2]);
        $this->assertSame('Asosiy brend', $sheets['Kanal va brend'][3][1]);
        $this->assertSame(['Jami', '', 3, 170000, 10000, 20000, 23500, '', 116500], end($sheets['Kanal va brend']));
        $this->assertSame(1, AuditLog::where('action', 'report.channel_revenue_exported')->where('store_id', $this->store->id)->count());
    }

    public function test_commissions_settings_and_access(): void
    {
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/channel-commissions', ['rates' => []])->assertForbidden(); // settings.manage
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/channel-commissions', ['rates' => [['channel' => 'aggregator', 'percent' => 101]]])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/channel-commissions', ['rates' => [['channel' => 'aggregator', 'percent' => 20], ['channel' => 'aggregator', 'percent' => 21]]])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/channel-commissions', ['rates' => [['channel' => 'aggregator', 'percent' => '18.5']]])->assertOk();
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/channel-commissions')->assertOk()->assertJsonPath('data.rates.0.percent', '18.50');
        $this->assertSame(1, AuditLog::where('action', 'settings.channel_commissions_changed')->count());

        // A cook has no reports.view; another store's owner sees only their own orders
        $this->actingAs($this->makeCook(), 'sanctum')->getJson('/api/v1/owner/reports/channel-revenue')->assertForbidden();
        $other = $this->makeStore('Boshqa');
        $this->useDarkKitchen($this->makeBranch($other, 'B'));
        $this->fixedOrders();
        $this->actingAs($this->makeStaff(UserRole::OWNER, null, $other), 'sanctum')->getJson('/api/v1/owner/reports/channel-revenue')->assertOk()->assertJsonPath('data.totals.orders', 0);
    }
}
