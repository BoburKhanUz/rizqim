<?php

namespace Tests\Feature\OnlineFiscal;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * P3-14: a fiscal-mode store, its MXIK catalogue and an online order (osh × 2, somsa 1.5 kg, promo discount,
 * delivery), with the P3-11 contracts bound to in-memory fakes.
 */
trait OnlineFiscalFixtures
{
    protected Store $store;
    protected User $customer;
    protected FakeOnlineFiscalLineBuilder $lineBuilder;
    protected FakeOnlineFiscalIssuer $issuer;

    protected function setUpOnlineFiscal(): void
    {
        $this->lineBuilder = new FakeOnlineFiscalLineBuilder();
        $this->lineBuilder->catalog = [
            'Osh' => ['mxik' => '10202001001000001', 'package' => '1503985'],
            'Somsa' => ['mxik' => '10701001001000000', 'package' => '1504142', 'unit' => '1'],
        ];
        $this->issuer = new FakeOnlineFiscalIssuer();
        $this->app->instance(FiscalLineBuilder::class, $this->lineBuilder);
        $this->app->instance(FiscalIssuer::class, $this->issuer);

        $this->store = Store::forceCreate([
            'name' => 'Fiskal Choyxona',
            'slug' => 'fiskal-choyxona',
            'status' => 'active',
            'payment_account_type' => 'platform_account',
            'debt_balance' => 0,
        ]);
        $this->setFiscal(true, '305123456');

        $this->customer = User::create([
            'name' => 'Customer',
            'phone' => '+998903330000',
            'role' => UserRole::CUSTOMER->value,
            'password' => bcrypt('password'),
        ]);
    }

    protected function setFiscal(bool $enabled, ?string $tin): void
    {
        DB::table('stores')->where('id', $this->store->id)->update([
            'tin' => $tin,
            'tax_number' => null,
            'fiscal_settings' => json_encode(['enabled' => $enabled, 'driver' => 'sandbox']),
        ]);
    }

    /**
     * Osh 2 × 35 000 + Somsa 1.5 kg × 40 000 = 130 000, minus a 10 000.01 promo, plus 15 000 delivery = 134 999.99.
     */
    protected function makeFiscalOrder(string $method, array $attributes = [], ?array $lines = null): Order
    {
        $lines ??= [['Osh', '2', '35000'], ['Somsa', '1.5', '40000']];
        $subtotal = array_sum(array_map(fn (array $line) => (float) $line[1] * (float) $line[2], $lines));
        $attributes += ['discount' => '10000.01', 'delivery_price' => '15000'];
        $total = round($subtotal - (float) $attributes['discount'] + (float) $attributes['delivery_price'], 2);

        $order = Order::create(array_merge([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'subtotal' => $subtotal,
            'total_price' => $total,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'delivery',
            'payment_method' => $method,
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));

        foreach ($lines as [$name, $quantity, $price]) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_name' => $name,
                'quantity' => $quantity,
                'unit' => $quantity === '1.5' ? 'kg' : 'piece',
                'unit_price' => $price,
                'total_price' => (float) $quantity * (float) $price,
            ]);
        }

        return $order;
    }
}
