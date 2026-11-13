<?php

namespace Tests\Feature\PosVouchers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosHallFixtures;
use Tests\TestCase;

/**
 * Review C1 (P6 wave 4): a promo code's discount is worked out again from the code with every change of the order's
 * lines — min(fixed value or % of the dishes, the code's cap, the dishes) — and the code is dropped (discount 0, the
 * order's promo_code_id cleared, audited `pos.order.promo_dropped`, a plain `promo_note`) once the dishes fall under
 * its minimum.
 */
class PosPromoRecalculationTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosHall();
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->fresh()->pos_settings ?? [], ['modules' => ['vouchers' => true], 'cashier_discount_max_percent' => '0'])])->save();
        app()->forgetScopedInstances();
    }

    /** A table order of 100 000 so'm of dishes (5 shashlik = 90 000, 2 tea = 10 000) */
    private function order(): Order
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [
            $this->line($this->shashlik, 5), $this->line($this->tea, 2),
        ]])->assertOk()->assertJsonPath('data.subtotal', '100000.00');

        return $order->refresh();
    }

    private function apply(Order $order, string $code): TestResponse
    {
        return $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/promo", ['version' => (int) $order->refresh()->version, 'code' => $code]);
    }

    private function voidShashlik(Order $order): TestResponse
    {
        $shashlik = $order->refresh()->items()->where('product_variant_id', $this->shashlik->id)->where('kitchen_status', '!=', 'void')->firstOrFail();

        return $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/items/{$shashlik->id}/void", ['version' => (int) $order->refresh()->version, 'reason' => 'Mehmon voz kechdi']);
    }

    public function test_a_fixed_code_is_dropped_when_the_dishes_fall_under_its_minimum(): void
    {
        $promo = PromoCode::create(['code' => 'MIN80', 'type' => 'fixed', 'value' => 30000, 'min_order_amount' => 80000, 'store_id' => $this->store->id, 'is_active' => true]);
        $order = $this->order();
        $this->apply($order, 'MIN80')->assertOk()->assertJsonPath('data.discount', '30000.00')->assertJsonPath('data.promo_note', null);

        $this->voidShashlik($order)->assertOk()
            ->assertJsonPath('data.subtotal', '10000.00')
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.discount_kind', null)
            ->assertJsonPath('data.discount_reason', null)
            ->assertJsonPath('data.promo_note', "Promo-kod MIN80 olib tashlandi: taomlar summasi kamida 80 000 so'm bo'lishi kerak.");

        $order->refresh();
        $this->assertNull($order->promo_code_id, 'the dropped code no longer counts as used');
        $this->assertSame('10000.00', (string) $order->total_price);
        $this->assertSame(0, $promo->redemptions()->count());
        $log = AuditLog::where('action', 'pos.order.promo_dropped')->where('model_id', $order->id)->sole();
        $this->assertSame('MIN80', $log->payload_json['promo']);
        $this->assertSame('10000.00', $log->payload_json['items_subtotal']);

        // Adding dishes back does not bring a dropped code back; applying it again works and clears the note
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [
            $this->line($this->shashlik, 5),
        ]])->assertOk()->assertJsonPath('data.discount', '0.00');
        $this->apply($order, 'MIN80')->assertOk()->assertJsonPath('data.discount', '30000.00')->assertJsonPath('data.promo_note', null);
    }

    public function test_a_capped_percentage_is_worked_out_again_after_a_void_and_after_lines_are_added_back(): void
    {
        PromoCode::create(['code' => 'P10', 'type' => 'percentage', 'value' => 10, 'max_discount_amount' => 9000, 'store_id' => $this->store->id, 'is_active' => true]);
        $order = $this->order();
        $this->apply($order, 'P10')->assertOk()->assertJsonPath('data.discount', '9000.00');

        // 10 % of the 10 000 left is 1 000, not the 9 000 cap
        $this->voidShashlik($order)->assertOk()->assertJsonPath('data.discount', '1000.00')->assertJsonPath('data.total_price', '9000.00');
        $this->assertNotNull($order->refresh()->promo_code_id);

        // Lines added back: 10 % of 100 000 is 10 000, capped at 9 000 again
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [
            $this->line($this->shashlik, 5),
        ]])->assertOk()->assertJsonPath('data.subtotal', '100000.00')->assertJsonPath('data.discount', '9000.00');

        // Split bills share the promo discount as any order discount: the bills' discounts add up to it
        $bills = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => (int) $order->refresh()->version, 'mode' => 'equal', 'parts' => 2])
            ->assertCreated()->json('data.bills');
        $this->assertSame(9000.0, array_sum(array_map(fn (array $bill) => (float) $bill['discount'], $bills)));
        $this->assertSame(91000.0, array_sum(array_map(fn (array $bill) => (float) $bill['total'], $bills)));
    }

    public function test_a_fixed_code_above_its_minimum_is_never_more_than_the_dishes(): void
    {
        PromoCode::create(['code' => 'F30', 'type' => 'fixed', 'value' => 30000, 'store_id' => $this->store->id, 'is_active' => true]);
        $order = $this->order();
        $this->apply($order, 'F30')->assertOk()->assertJsonPath('data.discount', '30000.00');

        $this->voidShashlik($order)->assertOk()->assertJsonPath('data.discount', '10000.00')->assertJsonPath('data.total_price', '0.00');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [
            $this->line($this->shashlik, 5),
        ]])->assertOk()->assertJsonPath('data.discount', '30000.00');
        $this->assertSame(0, AuditLog::where('action', 'pos.order.promo_dropped')->count());
    }
}
