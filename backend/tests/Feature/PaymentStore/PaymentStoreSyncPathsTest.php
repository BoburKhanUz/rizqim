<?php

namespace Tests\Feature\PaymentStore;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: money a till took offline: a synced bill.pay (PaymentService) and a payment the server cannot put on its
 * bill (PosSyncApplier::unallocated, the order was cancelled meanwhile) both carry the order's store.
 */
class PaymentStoreSyncPathsTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;
    use CapturesPaymentInserts;

    protected function setUp(): void
    {
        parent::setUp();

        // A store created first, so the store under test does not have id 1
        $this->makeStore('Earlier Store');
        $this->setUpPosSync();
    }

    public function test_an_offline_sale_synced_later(): void
    {
        $sale = $this->walkInSale();

        $inserts = $this->paymentInserts(fn () => $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied'));

        $this->assertInsertsCarryStore($inserts, $this->store->id);
    }

    public function test_an_offline_payment_of_an_order_cancelled_on_the_server(): void
    {
        // PosSyncConflictsTest::test_an_order_cancelled_on_the_server_rejects_the_round_and_keeps_the_payment_with_a_refund_flag
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $approval = (string) $this->tillAs('POST', '/pos/approvals', ['permission' => 'orders.cancel', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])->assertCreated()->json('data.token');
        $this->tillAs('POST', "/pos/orders/{$order->uuid}/cancel", ['version' => $order->version, 'reason' => 'Mijoz ketdi', 'approval_token' => $approval])->assertOk();

        $inserts = $this->paymentInserts(fn () => $this->sync([$sale['ops'][1], $sale['ops'][2]])->assertOk()->assertJsonPath('data.results.1.reason', 'refund_needed'));

        $this->assertInsertsCarryStore($inserts, $this->store->id);
        $this->assertNull(Payment::where('order_id', $order->id)->sole()->bill_id);
    }
}
