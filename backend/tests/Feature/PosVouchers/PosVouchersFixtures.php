<?php

namespace Tests\Feature\PosVouchers;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;

/**
 * P6-43 helpers: the billing fixtures on the real fiscal service (sandbox driver, MenuFiscalLineBuilder) so the
 * advance and sale receipts are the real ones, the branch's `vouchers` module on, and the voucher requests.
 */
trait PosVouchersFixtures
{
    use PosBillingFixtures;

    protected function setUpVouchers(): void
    {
        $this->setUpPosBilling();
        $this->app->forgetInstance(FiscalIssuer::class);
        $this->app->forgetInstance(FiscalLineBuilder::class);
        $this->product->forceFill(['mxik_code' => '10202001001000001'])->save();
        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox'],
        ])->save();
        $this->modules(['vouchers' => true]);
    }

    /**
     * @param  array<string, bool|null>  $modules
     */
    protected function modules(array $modules, ?Branch $branch = null): void
    {
        $branch ??= $this->branch;
        $settings = is_array($branch->fresh()->pos_settings) ? $branch->fresh()->pos_settings : [];
        Branch::whereKey($branch->id)->update(['pos_settings' => json_encode(['modules' => $modules] + $settings)]);
        app()->forgetScopedInstances();
    }

    /**
     * A POS order with menu lines (the product has its MXIK) worth $total.
     */
    protected function menuOrder(string $total = '150000'): Order
    {
        $order = $this->posOrder([]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Osh',
            'quantity' => '1',
            'unit' => 'piece',
            'unit_price' => $total,
            'total_price' => $total,
        ]);
        $order->forceFill(['subtotal' => $total, 'total_price' => $total])->save();

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function sell(string $faceValue = '200000', string $tender = 'cash', array $extra = [], ?string $key = null): TestResponse
    {
        return $this->pos('POST', '/vouchers/sell', ['face_value' => $faceValue, 'tender' => $tender] + $extra, null, $key);
    }

    protected function billOf(Order $order): string
    {
        return (string) $this->bill($order)->assertCreated()->json('data.bills.0.uuid');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function owner(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->json($method, '/api/v1/owner/pos' . $uri, $data);
    }
}
