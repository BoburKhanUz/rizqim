<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Forged "paid" callbacks on store-owned accounts. A store collecting into its own Click service or Payme cashbox knows
 * the key that authenticates the provider's "paid" callback, and Click's SHOP API and Payme's Merchant API have no
 * read-back RIZQIM uses for an order, so a callback signed with that key is trusted for that store's own STORE-owned
 * orders. These tests pin what such a key can NOT do: pay another store's order, pay an order of its own store that
 * the platform collects, pay another amount; and that the "paid" it can forge moves no platform money (no wallet
 * settlement, no keshbek balance).
 */
class PaymentForgedPaidCallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private string $platformClick;
    private string $platformPayme;

    /** @var array<string, array{store: Store, click: string, payme: string}> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Built at runtime: no merchant key is written in the code base
        $this->platformClick = 'platform-' . Str::random(24);
        $this->platformPayme = 'platform-' . Str::random(24);
        config(['services.click.secret_key' => $this->platformClick, 'services.payme.secret_key' => $this->platformPayme]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998901212001', 'password' => bcrypt('password')]);

        foreach (['x', 'y'] as $name) {
            $click = "{$name}-click-" . Str::random(24);
            $payme = "{$name}-payme-" . Str::random(24);
            $this->stores[$name] = [
                'store' => Store::forceCreate([
                    'name' => strtoupper($name) . ' Cafe',
                    'status' => 'active',
                    'payment_account_type' => 'own_account',
                    'credit_limit' => -1000000,
                    'payment_settings' => [
                        'click' => ['enabled' => true, 'service_id' => "svc-{$name}", 'merchant_id' => "m-{$name}", 'secret_key' => $click],
                        'payme' => ['enabled' => true, 'merchant_id' => "pm-{$name}", 'secret_key' => $payme],
                    ],
                ]),
                'click' => $click,
                'payme' => $payme,
            ];
        }
    }

    private function order(string $store, string $method, string $owner = 'STORE', array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $this->stores[$store]['store']->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => $method,
            'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    private function click(Order $order, string $key, int $action, string $amount = '50000', string $trans = '4242', string $service = 'svc-x')
    {
        $prepareId = $action === 1 ? (string) $order->id : '';
        $data = [
            'click_trans_id' => $trans, 'service_id' => $service, 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'amount' => $amount, 'action' => $action, 'error' => 0,
            'sign_time' => '2026-11-24 12:00:00',
            'sign_string' => md5($trans . $service . $key . $order->uuid . $prepareId . $amount . $action . '2026-11-24 12:00:00'),
        ];
        if ($action === 1) {
            $data['merchant_prepare_id'] = $order->id;
        }

        return $this->postJson('/api/v1/payments/click/webhook', $data);
    }

    private function payme(string $method, array $params, string $key)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 3, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $key),
        ]);
    }

    private function assertUnpaid(Order $order): void
    {
        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(OrderStatus::NEW, $fresh->status);
        $this->assertSame(0, Payment::where('order_id', $order->id)->where('status', PaymentStatus::PAID->value)->count());
    }

    public function test_a_stores_own_click_key_never_pays_another_stores_order(): void
    {
        $theirs = $this->order('y', 'click');
        $mine = $this->stores['x']['click'];

        $this->click($theirs, $mine, 0)->assertJson(['error' => -1]);
        $this->click($theirs, $mine, 1)->assertJson(['error' => -1]);
        // Whatever service the forger names: the key of the order's own account signs, nothing else
        $this->click($theirs, $mine, 1, service: 'svc-y')->assertJson(['error' => -1]);
        $this->assertUnpaid($theirs);

        // The order's own key still pays it
        $this->click($theirs, $this->stores['y']['click'], 1, service: 'svc-y')->assertJson(['error' => 0]);
        $this->assertSame(PaymentStatus::PAID, $theirs->fresh()->payment_status);
    }

    public function test_a_stores_own_payme_key_never_creates_or_performs_on_another_stores_order(): void
    {
        $theirs = $this->order('y', 'payme');
        $mine = $this->stores['x']['payme'];
        $account = ['order_id' => $theirs->uuid];

        $this->payme('CheckPerformTransaction', ['amount' => 5000000, 'account' => $account], $mine)->assertJsonPath('error.code', -32504);
        $this->payme('CreateTransaction', ['id' => 'forged-1', 'time' => 1764000000000, 'amount' => 5000000, 'account' => $account], $mine)
            ->assertJsonPath('error.code', -32504);
        $this->assertSame(0, Payment::where('provider_transaction_id', 'forged-1')->count());

        // Store Y's real transaction cannot be performed or cancelled with X's key, with or without X's own account
        $this->payme('CreateTransaction', ['id' => 'real-y', 'time' => 1764000000000, 'amount' => 5000000, 'account' => $account], $this->stores['y']['payme'])
            ->assertJsonPath('result.state', 1);
        $ownOrder = $this->order('x', 'payme');
        $this->payme('PerformTransaction', ['id' => 'real-y'], $mine)->assertJsonPath('error.code', -32504);
        $this->payme('PerformTransaction', ['id' => 'real-y', 'account' => ['order_id' => $ownOrder->uuid]], $mine)->assertJsonPath('error.code', -31003);
        $this->payme('CancelTransaction', ['id' => 'real-y', 'reason' => 3, 'account' => ['order_id' => $ownOrder->uuid]], $mine)->assertJsonPath('error.code', -31003);

        $this->assertUnpaid($theirs);
        $this->assertSame(PaymentStatus::PENDING, Payment::where('provider_transaction_id', 'real-y')->sole()->status);
        $this->assertUnpaid($ownOrder);
    }

    public function test_a_stores_own_key_never_pays_its_own_platform_collected_order(): void
    {
        // Placed while the store's account could not collect (or before it switched to its own): the platform's money
        $click = $this->order('x', 'click', 'PLATFORM');
        $payme = $this->order('x', 'payme', 'PLATFORM');

        $this->click($click, $this->stores['x']['click'], 1)->assertJson(['error' => -1]);
        $this->payme('CreateTransaction', ['id' => 'forged-2', 'time' => 1764000000000, 'amount' => 5000000, 'account' => ['order_id' => $payme->uuid]], $this->stores['x']['payme'])
            ->assertJsonPath('error.code', -32504);

        $this->assertUnpaid($click);
        $this->assertUnpaid($payme);
    }

    public function test_a_stores_own_key_never_pays_its_order_another_amount(): void
    {
        $click = $this->order('x', 'click');
        $payme = $this->order('x', 'payme');

        foreach (['49999.99', '50000.01', '5000', '50000.001'] as $amount) {
            $this->click($click, $this->stores['x']['click'], 1, $amount)->assertJson(['error' => -2]);
        }
        foreach ([4999999, 5000001, 500000] as $tiyin) {
            $this->payme('CreateTransaction', ['id' => "amt-{$tiyin}", 'time' => 1764000000000, 'amount' => $tiyin, 'account' => ['order_id' => $payme->uuid]], $this->stores['x']['payme'])
                ->assertJsonPath('error.code', -31001);
        }

        $this->assertUnpaid($click);
        $this->assertUnpaid($payme);
        $this->assertSame(0, Payment::where('provider', 'payme')->whereNotNull('provider_transaction_id')->count());
    }

    /**
     * What a forged "paid" on the store's own account can do: mark its own order paid (and auto-accept it). The money
     * never passed through RIZQIM and RIZQIM never pays it out: no wallet settlement, the wallet and the customer's
     * keshbek balance unchanged; delivery books the commission to the store's debt exactly as for an unpaid one.
     */
    public function test_a_forged_paid_on_the_stores_own_account_moves_no_platform_money(): void
    {
        $store = $this->stores['x']['store'];
        $order = $this->order('x', 'click');
        $balance = (string) $this->customer->fresh()->balance;

        $this->click($order, $this->stores['x']['click'], 1)->assertJson(['error' => 0]);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);

        foreach ([OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }

        $this->assertSame(0, StoreWalletTransaction::where('order_id', $order->id)->count());
        $this->assertSame(0, StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::TOPUP->value)->count());
        $this->assertSame('0.00', (string) $store->fresh()->wallet_balance);
        $this->assertSame('5000.00', (string) $store->fresh()->debt_balance);
        $this->assertSame($balance, (string) $this->customer->fresh()->balance);
    }
}
