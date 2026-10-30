<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-21 / P6-25 offline: a restaurant order taken offline keeps its courses (a later one held), the till's "N-kursni
 * yuborish" and the fish's real weight reach the server as their own operations (order.course_send, order.weight), a
 * bill made before the weight is rejected, the table plate comes with order.open, and courses are dropped — never the
 * sale — while the module is off.
 */
class PosSyncRestaurantTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    private ProductVariant $fish;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();

        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->pos_settings, FormatProfile::RESTORAN, ['weight_later' => true, 'table_service' => true])])->save();
        $this->fish = $this->makeVariant($this->makeProduct('Sazan', ['weight_mode' => 'raw', 'mxik_code' => '10202001009000001']), '245000', [
            'name' => 'Mangal', 'unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.5', 'stock' => 100,
        ]);
    }

    public function test_courses_and_the_weight_entered_offline_are_applied_in_order(): void
    {
        $order = (string) Str::uuid();
        $fishLine = (string) Str::uuid();
        $bill = (string) Str::uuid();
        $ops = [
            $this->op('order.open', [
                'order' => $order,
                'local_number' => 'K1-007',
                'channel' => 'pos_takeaway',
                'table_label' => '12',
                'items' => [
                    $this->line($this->tea, '1') + ['course_no' => 1],
                    $this->line($this->shashlik, '4') + ['course_no' => 2],
                    $this->line($this->fish, '1.5', null, $fishLine) + ['course_no' => 2],
                ],
                'send' => true,
            ], null, 40),
            // A bill before the fish is weighed: rejected (no bill while a line awaits its weight)
            $this->op('bill.create', ['order' => $order, 'bill' => (string) Str::uuid()], null, 35),
            $this->op('order.course_send', ['order' => $order, 'course_no' => 2], null, 30),
            $this->op('order.weight', ['order' => $order, 'line' => $fishLine, 'quantity' => '1.35'], null, 25),
            // The same course again: nothing left, applied
            $this->op('order.course_send', ['order' => $order, 'course_no' => 2], null, 24),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill], null, 20),
        ];

        $response = $this->sync($ops)->assertOk();
        $response->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'weight_pending')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.3.status', 'applied')
            ->assertJsonPath('data.results.4.status', 'applied')
            ->assertJsonPath('data.results.4.refs.sent', [])
            ->assertJsonPath('data.results.5.status', 'applied');

        $saved = Order::where('external_id', $order)->firstOrFail();
        $this->assertSame('12', $saved->table_label);
        $lines = OrderItem::where('order_id', $saved->id)->orderBy('id')->get();
        $this->assertSame([1, 2, 2], $lines->pluck('course_no')->all());
        $this->assertTrue($lines->every(fn (OrderItem $item) => $item->sent_at !== null));
        $fish = $lines->firstWhere('client_line_id', $fishLine);
        $this->assertSame('weighed', $fish->weight_status->value);
        $this->assertSame('1.35 kg', $fish->quantityLabel());
        $this->assertSame('330750.00', (string) $fish->total_price);
        $this->assertSame((int) $this->cashier->id, (int) $fish->weighed_by);
    }

    public function test_offline_courses_are_dropped_while_the_module_is_off_and_a_weighed_line_keeps_the_servers_weight(): void
    {
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->pos_settings, FormatProfile::RESTORAN, ['courses' => false, 'weight_later' => true])])->save();
        $order = (string) Str::uuid();
        $fishLine = (string) Str::uuid();

        $this->sync([
            $this->op('order.open', [
                'order' => $order,
                'local_number' => 'K1-008',
                'channel' => 'pos_takeaway',
                'items' => [$this->line($this->shashlik, '2') + ['course_no' => 2], $this->line($this->fish, '1', null, $fishLine)],
            ], null, 30),
            $this->op('order.weight', ['order' => $order, 'line' => $fishLine, 'quantity' => '1.1'], null, 25),
            $this->op('order.weight', ['order' => $order, 'line' => $fishLine, 'quantity' => '1.2'], null, 20),
            $this->op('order.course_send', ['order' => $order, 'course_no' => 0], null, 19),
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.2.status', 'rejected')
            ->assertJsonPath('data.results.2.reason', 'line_weighed')
            ->assertJsonPath('data.results.3.status', 'rejected')
            ->assertJsonPath('data.results.3.reason', 'course_invalid');

        $saved = Order::where('external_id', $order)->firstOrFail();
        $shashlik = OrderItem::where('order_id', $saved->id)->where('product_variant_id', $this->shashlik->id)->firstOrFail();
        $this->assertNull($shashlik->course_no);
        $this->assertSame('now', $shashlik->fire_mode->value);
        $this->assertSame('1.1 kg', OrderItem::where('client_line_id', $fishLine)->firstOrFail()->quantityLabel());
    }
}
