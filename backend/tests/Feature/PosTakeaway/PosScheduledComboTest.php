<?php

namespace Tests\Feature\PosTakeaway;

use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\PosFastFood\ComboFixtures;
use Tests\TestCase;

/**
 * Review P6W3 C10 (scheduled part): a combo on a takeaway given a pickup time later — its parts' kitchen lines wait with
 * the combo line and go to their stations when the order fires.
 */
class PosScheduledComboTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCombo();
        Carbon::setTestNow('2026-10-22 13:00:00');
    }

    public function test_a_combo_given_a_pickup_time_holds_its_parts_until_it_fires(): void
    {
        $this->makeCombo();
        $opened = $this->counterOrder([$this->comboLine()], false)->assertCreated();
        $order = Order::where('uuid', $opened->json('data.uuid'))->firstOrFail();
        $parts = fn () => OrderItem::withComboParts()->where('order_id', $order->id)->whereNotNull('combo_parent_id')->orderBy('id')->get();
        $this->assertCount(2, $parts());

        $this->tillAs('PUT', "/pos/orders/{$order->uuid}/pickup-time", ['version' => (int) $opened->json('data.version'), 'pickup_at' => '2026-10-22T14:00:00Z'])
            ->assertOk()->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T13:45:00.000000Z');
        $this->assertSame(['hold'], $parts()->map(fn (OrderItem $part) => $part->fire_mode?->value)->unique()->values()->all());
        $this->assertSame([null], $parts()->pluck('sent_at')->unique()->values()->all());

        Carbon::setTestNow('2026-10-22 13:45:00');
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());
        $this->assertSame(['sent'], $parts()->map(fn (OrderItem $part) => $part->kitchen_status->value)->unique()->values()->all());
        $this->assertSame(['now'], $parts()->map(fn (OrderItem $part) => $part->fire_mode?->value)->unique()->values()->all());
    }
}
