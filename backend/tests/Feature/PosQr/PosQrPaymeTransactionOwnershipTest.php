<?php

namespace Tests\Feature\PosQr;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security review F2: a POS QR never takes over a Payme transaction id. A store with its own cashbox key and QR payments
 * could send CreateTransaction {id: T, account: pos_qr_<its QR>}; every later Payme call for T — a platform-collected
 * order's payment, or another cashbox's — was then routed to the QR handler and refused (-32504). Now a call about a
 * transaction id reaches a QR only when a QR of the cashbox whose key signed it holds the id and none of that
 * cashbox's payments does, and within one cashbox an id is a payment's or a QR's, never both.
 */
class PosQrPaymeTransactionOwnershipTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    private int $tiyin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpQr();
        $this->openShift();
    }

    private function paymeQr(): PosQrPayment
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'payme')->assertCreated()->json('data.qr.uuid'));
        $this->tiyin = Money::of($qr->amount)->toTiyin();

        return $qr;
    }

    private function onlineOrder(Store $store, string $owner): Order
    {
        $customer = User::create(['name' => 'Mijoz', 'phone' => '+99890' . random_int(1000000, 9999999), 'password' => bcrypt('x')]);

        return Order::create(['store_id' => $store->id, 'user_id' => $customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value, 'order_type' => 'pickup', 'payment_method' => 'payme', 'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PENDING->value]);
    }

    private function create(string $id, string $account, int $amount, string $key)
    {
        return $this->payme('CreateTransaction', ['id' => $id, 'time' => 1764000000000, 'amount' => $amount, 'account' => ['order_id' => $account]], $key);
    }

    public function test_a_qr_never_captures_the_transaction_id_of_a_platform_collected_payment(): void
    {
        $order = $this->onlineOrder($this->store, 'PLATFORM');
        $this->create('pm-real-1', $order->uuid, 5000000, 'platform_payme_key')->assertJsonPath('result.state', 1);

        // The store, with its own key, holds its QR with the platform payment's id
        $qr = $this->paymeQr();
        $this->create('pm-real-1', $qr->account(), $this->tiyin, self::PAYME_SECRET);

        // Every real Payme call for the id still reaches the order's payment
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->payme('CheckTransaction', ['id' => 'pm-real-1'], 'platform_payme_key')->assertJsonPath('result.state', 1)->assertJsonPath('result.transaction', (string) $payment->id);
        $this->payme('PerformTransaction', ['id' => 'pm-real-1'], 'platform_payme_key')->assertJsonPath('result.state', 2)->assertJsonPath('result.transaction', (string) $payment->id);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertNotSame('paid', $qr->refresh()->status->value);

        // And the platform's statement lists it as the order's
        $rows = $this->payme('GetStatement', ['from' => 1763999999999, 'to' => 1764000000001], 'platform_payme_key')->json('result.transactions');
        $this->assertSame([[(string) $payment->id, 2]], array_map(fn ($row) => [$row['transaction'], $row['state']], $rows));
    }

    public function test_a_qr_never_captures_the_transaction_id_of_another_cashboxs_payment(): void
    {
        $other = Store::forceCreate(['name' => 'Boshqa', 'slug' => 'boshqa-pm', 'status' => 'active', 'payment_account_type' => 'own_account', 'credit_limit' => -1000000,
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'other', 'secret_key' => 'other_payme_key']]]);
        $order = $this->onlineOrder($other, 'STORE');
        $this->create('pm-real-2', $order->uuid, 5000000, 'other_payme_key')->assertJsonPath('result.state', 1);

        $qr = $this->paymeQr();
        $this->create('pm-real-2', $qr->account(), $this->tiyin, self::PAYME_SECRET);

        $this->payme('PerformTransaction', ['id' => 'pm-real-2'], 'other_payme_key')->assertJsonPath('result.state', 2);
        $this->payme('CancelTransaction', ['id' => 'pm-real-2', 'reason' => 3], 'other_payme_key')->assertJsonPath('result.state', -2);
        $this->assertSame(PaymentStatus::REFUNDED, Payment::where('order_id', $order->id)->sole()->status);
    }

    public function test_within_one_cashbox_an_id_is_a_payments_or_a_qrs_never_both(): void
    {
        // The store's own online order holds the id first: its QR is refused it
        $order = $this->onlineOrder($this->store, 'STORE');
        $this->create('pm-own-1', $order->uuid, 5000000, self::PAYME_SECRET)->assertJsonPath('result.state', 1);
        $qr = $this->paymeQr();
        $this->create('pm-own-1', $qr->account(), $this->tiyin, self::PAYME_SECRET)->assertJsonPath('error.code', -31050);
        $this->assertNull($qr->refresh()->provider_transaction_id);
        $this->payme('PerformTransaction', ['id' => 'pm-own-1'])->assertJsonPath('result.state', 2);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);

        // A QR holds an id first: an order of the cashbox is refused it, and the QR keeps it
        $this->create('pm-qr-1', $qr->account(), $this->tiyin, self::PAYME_SECRET)->assertJsonPath('result.state', 1);
        $second = $this->onlineOrder($this->store, 'STORE');
        $this->create('pm-qr-1', $second->uuid, 5000000, self::PAYME_SECRET)->assertJsonPath('error.code', -31050);
        $this->assertSame(0, Payment::where('order_id', $second->id)->count());
        $this->payme('PerformTransaction', ['id' => 'pm-qr-1'])->assertJsonPath('result.state', 2);
        $this->assertSame('paid', $qr->refresh()->status->value);
    }
}
