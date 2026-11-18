<?php

namespace Tests\Feature\PaymentStore;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: the marketplace and webhook creation sites of payments set store_id themselves (the INSERT they send
 * carries it) to the parent's store: CreateOrderAction, the Click Complete, the Payme CreateTransaction for an order
 * and for a store's top-up. The POS sites are in PaymentStorePosPathsTest and PaymentStoreSyncPathsTest.
 */
class PaymentStoreCreationPathsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use CapturesPaymentInserts;

    private const CLICK_SECRET = 'platform_click_secret';

    private const PAYME_KEY = 'platform_payme_key';

    protected function setUp(): void
    {
        parent::setUp();

        // A store created first, so the store under test does not have id 1
        $this->makeStore('Earlier Store');
        $this->setUpLifecycleStore();
        config(['services.click.secret_key' => self::CLICK_SECRET, 'services.payme.secret_key' => self::PAYME_KEY]);
        Event::fake([PaymentPaid::class]);
    }

    public function test_a_marketplace_checkout(): void
    {
        $inserts = $this->paymentInserts(fn () => $this->checkout($this->customer));

        $this->assertInsertsCarryStore($inserts, $this->store->id);
    }

    public function test_a_click_payment(): void
    {
        $order = $this->onlineOrder('click');

        $inserts = $this->paymentInserts(function () use ($order) {
            $this->click(0, $order, '50000.00')->assertJson(['error' => 0]);
            $this->click(1, $order, '50000.00')->assertJson(['error' => 0, 'error_note' => 'Success']);
        });

        $this->assertInsertsCarryStore($inserts, $this->store->id);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_a_payme_payment_of_an_order(): void
    {
        $order = $this->onlineOrder('payme');

        $inserts = $this->paymentInserts(fn () => $this->payme('CreateTransaction', ['id' => 'trx-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])->assertJsonPath('result.state', 1));

        $this->assertInsertsCarryStore($inserts, $this->store->id);
    }

    public function test_a_payme_top_up_of_a_store(): void
    {
        $topup = StoreTopupRequest::create(['store_id' => $this->store->id, 'amount' => 100000, 'provider' => 'payme', 'status' => 'pending']);

        $inserts = $this->paymentInserts(function () use ($topup) {
            $this->payme('CreateTransaction', ['id' => 'trx-topup', 'time' => 1760000000000, 'amount' => 10000000, 'account' => ['order_id' => 'topup_' . $topup->uuid]])->assertJsonPath('result.state', 1);
            $this->payme('PerformTransaction', ['id' => 'trx-topup'])->assertJsonPath('result.state', 2);
        });

        $this->assertCount(1, $inserts);
        $this->assertSame($this->store->id, (int) $inserts[0]['store_id']);
        $payment = Payment::where('provider_transaction_id', 'trx-topup')->sole();
        $this->assertNull($payment->order_id);
        $this->assertSame($this->store->id, $payment->store_id);
        $this->assertSame('paid', $topup->fresh()->status);
    }

    private function onlineOrder(string $method): Order
    {
        return $this->makeOrder(OrderStatus::NEW, [
            'payment_method' => $method,
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
    }

    /**
     * PaymentClickWebhookTest::click(): a signed Prepare (0) or Complete (1) for the order.
     */
    private function click(int $action, Order $order, string $amount): TestResponse
    {
        $clickTransId = '9001';
        $signTime = '2026-10-18 12:00:00';
        $data = [
            'click_trans_id' => $clickTransId,
            'service_id' => '32104',
            'click_paydoc_id' => '555',
            'merchant_trans_id' => $order->uuid,
            'amount' => $amount,
            'action' => $action,
            'error' => 0,
            'error_note' => 'Success',
            'sign_time' => $signTime,
            'sign_string' => md5($clickTransId . '32104' . self::CLICK_SECRET . $order->uuid . ($action === 1 ? $order->id : '') . $amount . $action . $signTime),
        ];
        if ($action === 1) {
            $data['merchant_prepare_id'] = $order->id;
        }

        return $this->postJson('/api/v1/payments/click/webhook', $data);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function payme(string $method, array $params): TestResponse
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . self::PAYME_KEY),
        ]);
    }
}
