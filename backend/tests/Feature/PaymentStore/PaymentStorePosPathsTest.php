<?php

namespace Tests\Feature\PaymentStore;

use App\Events\Broadcast\QrPaymentBroadcast;
use App\Jobs\SubmitClickFiscalData;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Feature\PosQr\PosQrFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: a till's payments (PaymentService::pay, the one POS creation site: the bill's tenders, a QR confirmed by
 * the provider's webhook, a banquet settlement's advance offset and an offline bill.pay all go through it) set
 * payments.store_id themselves to the order's store.
 */
class PaymentStorePosPathsTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use CapturesPaymentInserts;

    protected function setUp(): void
    {
        parent::setUp();

        // A store created first, so the store under test does not have id 1
        $this->makeStore('Earlier Store');
        $this->setUpQr();
        $this->openShift();
    }

    public function test_a_bill_paid_with_two_tenders(): void
    {
        [, $bill] = $this->billed([['Osh', '1', '143000']]);

        $inserts = $this->paymentInserts(fn () => $this->pay($bill, [
            ['tender' => 'cash', 'amount' => '100000', 'tendered_amount' => '100000'],
            ['tender' => 'card', 'amount' => '43000', 'card_type' => 'uzcard', 'transaction_id' => 'T-1'],
        ])->assertOk()->assertJsonPath('data.bill.status', 'paid'));

        $this->assertInsertsCarryStore($inserts, $this->store->id, 2);
    }

    public function test_a_qr_paid_through_the_providers_webhook(): void
    {
        Bus::fake([SubmitClickFiscalData::class]);
        Event::fake([QrPaymentBroadcast::class]);
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'click')->assertCreated()->json('data.qr.uuid'));

        $inserts = $this->paymentInserts(function () use ($qr) {
            $this->click(0, $qr)->assertJsonPath('error', 0);
            $this->click(1, $qr)->assertJsonPath('error', 0);
        });

        $this->assertInsertsCarryStore($inserts, $this->store->id);
        $this->assertSame($this->store->id, Payment::findOrFail($qr->refresh()->payment_id)->store_id);
    }
}
