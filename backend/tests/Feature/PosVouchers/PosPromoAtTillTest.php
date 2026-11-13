<?php

namespace Tests\Feature\PosVouchers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosHallFixtures;
use Tests\TestCase;

/**
 * P6-43: a discount voucher is the store's promo code on a POS order (POST/DELETE /pos/orders/{uuid}/promo): the
 * order's discount with reason `promo:<CODE>`, the cashier's limit not applying, one code per order, usage counted as
 * for online orders, and the refusals (another store's code, inactive, minimum, limit), the module gate.
 */
class PosPromoAtTillTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->settings(['modules' => ['vouchers' => true], 'cashier_discount_max_percent' => '0']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function settings(array $settings): void
    {
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->fresh()->pos_settings ?? [], $settings)])->save();
        app()->forgetScopedInstances();
    }

    /** A table order of 100 000 so'm of dishes */
    private function order(): Order
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [
            $this->line($this->shashlik, 5), $this->line($this->tea, 2),
        ]])->assertOk()->assertJsonPath('data.subtotal', '100000.00');

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function promo(string $code, array $attributes = []): PromoCode
    {
        return PromoCode::create($attributes + ['code' => $code, 'type' => 'percentage', 'value' => 10, 'store_id' => $this->store->id, 'is_active' => true]);
    }

    private function apply(Order $order, string $code): TestResponse
    {
        return $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/promo", ['version' => (int) $order->refresh()->version, 'code' => $code]);
    }

    public function test_a_valid_code_is_the_orders_discount_whatever_the_cashiers_limit(): void
    {
        $this->promo('KUZ10', ['max_discount_amount' => 0]);
        $order = $this->order();

        // The cashier may give no discount by hand...
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/discount", ['version' => $order->version, 'kind' => 'percent', 'value' => '10', 'reason' => 'x'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        // ...but the store's own code applies: a percent that follows the lines
        $this->apply($order, ' kuz10 ')->assertOk()
            ->assertJsonPath('data.discount', '10000.00')
            ->assertJsonPath('data.discount_kind', 'percent')
            ->assertJsonPath('data.discount_reason', 'promo:KUZ10');
        $promo = PromoCode::where('code', 'KUZ10')->sole();
        $this->assertSame($promo->id, (int) $order->refresh()->promo_code_id);
        $this->assertSame(1, $promo->redemptions()->count());
        $this->assertTrue(AuditLog::where('action', 'pos.order.promo')->exists());

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => (int) $order->refresh()->version, 'items' => [$this->line($this->tea, 2)]])
            ->assertOk()->assertJsonPath('data.discount', '11000.00');

        // The same code again is the same discount; another one is refused until this one is off
        $this->apply($order, 'KUZ10')->assertOk()->assertJsonPath('data.discount', '11000.00');
        $this->promo('BAHOR', ['type' => 'fixed', 'value' => 5000]);
        $this->apply($order, 'BAHOR')->assertConflict()->assertJsonPath('data.reason', 'promo_already_applied');

        $this->pos($this->cashier, 'DELETE', "/pos/orders/{$order->uuid}/promo?version=" . $order->refresh()->version)->assertOk()
            ->assertJsonPath('data.discount', '0.00')->assertJsonPath('data.discount_reason', null);
        $this->assertNull($order->refresh()->promo_code_id);
        $this->assertSame(0, $promo->redemptions()->count());
        $this->pos($this->cashier, 'DELETE', "/pos/orders/{$order->uuid}/promo?version=" . $order->refresh()->version)->assertUnprocessable()->assertJsonPath('data.reason', 'promo_not_applied');

        // A fixed code is an amount
        $this->apply($order, 'BAHOR')->assertOk()->assertJsonPath('data.discount', '5000.00')->assertJsonPath('data.discount_kind', 'amount');
    }

    public function test_a_capped_percentage_is_the_capped_amount(): void
    {
        $this->promo('KATTA', ['value' => 50, 'max_discount_amount' => 20000]);

        $this->apply($this->order(), 'KATTA')->assertOk()->assertJsonPath('data.discount', '20000.00')->assertJsonPath('data.discount_kind', 'amount');
    }

    public function test_the_usage_limit_counts_pos_and_online_orders_and_a_cancelled_one_gives_it_back(): void
    {
        $promo = $this->promo('BIR', ['usage_limit' => 1]);
        $first = $this->order();
        $this->apply($first, 'BIR')->assertOk();

        $second = $this->order();
        $this->apply($second, 'BIR')->assertUnprocessable()->assertJsonPath('data.reason', 'promo_limit_reached');

        Order::whereKey($first->id)->update(['status' => 'canceled']);
        $this->apply($second, 'BIR')->assertOk();
        $this->assertSame(1, $promo->redemptions()->count());
    }

    public function test_refusals(): void
    {
        $order = $this->order();
        $this->apply($order, 'YOQ')->assertNotFound()->assertJsonPath('data.reason', 'promo_not_found');
        // A platform-wide code and another store's are not the till's
        PromoCode::create(['code' => 'HAMMA', 'type' => 'percentage', 'value' => 10, 'store_id' => null]);
        $this->apply($order, 'HAMMA')->assertNotFound();
        $other = $this->makeStore('Boshqa');
        PromoCode::create(['code' => 'BOSHQA', 'type' => 'percentage', 'value' => 10, 'store_id' => $other->id]);
        $this->apply($order, 'BOSHQA')->assertNotFound();

        $this->promo('ESKI', ['is_active' => false]);
        $this->apply($order, 'ESKI')->assertUnprocessable()->assertJsonPath('data.reason', 'promo_inactive');
        $this->promo('OTDI', ['expires_at' => now()->subDay()]);
        $this->apply($order, 'OTDI')->assertUnprocessable()->assertJsonPath('data.reason', 'promo_inactive');
        $this->promo('MIN', ['min_order_amount' => 150000]);
        $this->apply($order, 'MIN')->assertUnprocessable()->assertJsonPath('data.reason', 'promo_min_order')->assertJsonPath('data.min_order_amount', '150000.00');
        $this->assertSame('0.00', $order->refresh()->discount);
    }

    public function test_the_module_gates_applying_never_taking_off(): void
    {
        $this->promo('KUZ10');
        $order = $this->order();
        $this->apply($order, 'KUZ10')->assertOk();

        $this->settings(['modules' => ['vouchers' => false]]);
        $this->apply($this->order(), 'KUZ10')->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->pos($this->cashier, 'DELETE', "/pos/orders/{$order->uuid}/promo?version=" . $order->refresh()->version)->assertOk();
    }

    public function test_a_discount_by_hand_replaces_the_code(): void
    {
        $this->promo('KUZ10');
        $order = $this->order();
        $this->apply($order, 'KUZ10')->assertOk();

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/discount", ['version' => (int) $order->refresh()->version, 'kind' => 'amount', 'value' => '3000', 'reason' => 'Doimiy mijoz'])->assertOk();
        $this->assertNull($order->refresh()->promo_code_id);
        $this->assertSame('3000.00', $order->discount);
    }
}
