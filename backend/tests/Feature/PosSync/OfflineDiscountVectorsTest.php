<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M5 S2 (docs/scale/M5_DESIGN.md §3, risk 7.2-2): the discount vectors the till's Dart test reads too
 * (tests/Fixtures/offline_discount_vectors.json). The server is the reference: the amounts are
 * PosChargesCalculator::discountOf, the cashier's limit is what an `order.discount` operation of a cashier meets on
 * sync (PosOrderService::cashierMayDiscount). A till that computes a discount as these vectors say sends bills the
 * server agrees with to the tiyin.
 */
class OfflineDiscountVectorsTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/Fixtures/offline_discount_vectors.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_amounts_are_what_the_server_computes(): void
    {
        $vectors = self::vectors();
        $this->assertSame(1, $vectors['version']);
        $this->assertGreaterThanOrEqual(15, count($vectors['amounts']));

        foreach ($vectors['amounts'] as $case) {
            $order = new Order(['discount_kind' => $case['kind'], 'discount_value' => $case['value']]);
            $items = Money::of($case['items']);
            $discount = PosChargesCalculator::discountOf($order, $items);

            $this->assertSame($case['discount'], $discount->toString(), $case['name']);
            $this->assertSame($case['after_discount'], Money::max(Money::zero(), $items->minus($discount))->toString(), $case['name']);
        }
    }

    public function test_a_discount_follows_the_lines(): void
    {
        foreach (self::vectors()['sequences'] as $sequence) {
            $order = new Order(['discount_kind' => $sequence['kind'], 'discount_value' => $sequence['value']]);
            foreach ($sequence['steps'] as $k => $step) {
                $this->assertSame($step['discount'], PosChargesCalculator::discountOf($order, Money::of($step['items']))->toString(), "{$sequence['name']} #{$k}");
            }
        }
    }

    public function test_the_operation_in_the_vectors_is_the_one_the_server_takes(): void
    {
        $this->setUpPosSync();
        $operation = self::vectors()['operation'];
        $order = (string) Str::uuid();

        $this->sync([
            $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')], 'send' => true]),
            $this->op($operation['type'], ['order' => $order] + $operation['payload']),
        ])->assertOk()
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.1.refs.discount', $operation['payload']['expected_discount']);

        $this->sync([$this->op($operation['type'], ['order' => $order] + $operation['remove'])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.discount', '0.00');
    }

    public function test_the_cashiers_limit_is_what_the_server_applies_on_sync(): void
    {
        $this->setUpPosSync();
        $vectors = self::vectors();
        $this->assertGreaterThanOrEqual(10, count($vectors['limits']));

        foreach ($vectors['limits'] as $case) {
            $this->branch->forceFill(['pos_settings' => array_merge($this->branch->fresh()->pos_settings ?? [], ['cashier_discount_max_percent' => $case['limit']])])->save();
            // One dish worth exactly the vector's items (a takeaway: no service charge, no room fee)
            $dish = $this->makeVariant($this->makeProduct('Taom ' . Str::random(6), ['mxik_code' => '10202001007000001']), $case['items'], ['stock' => 1000]);
            $order = (string) Str::uuid();

            $results = $this->sync([
                $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($dish, '1', $case['items'])], 'send' => true]),
                $this->op('order.discount', ['order' => $order, 'kind' => $case['kind'], 'value' => $case['value'], 'reason' => 'Vektor']),
            ])->assertOk()->assertJsonPath('data.results.0.status', 'applied')->json('data.results');

            $this->assertSame($case['allowed'] ? 'applied' : 'rejected', $results[1]['status'], $case['name']);
            $this->assertSame($case['allowed'] ? null : 'discount_not_allowed', $results[1]['reason'], $case['name']);
            $stored = Order::where('external_id', $order)->firstOrFail();
            $this->assertSame($case['allowed'], Money::of($stored->discount)->isPositive(), $case['name']);
            if ($case['allowed']) {
                $this->assertSame($case['kind'], $stored->discount_kind, $case['name']);
            }
        }
    }
}
