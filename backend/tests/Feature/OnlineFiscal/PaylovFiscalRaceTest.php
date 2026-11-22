<?php

namespace Tests\Feature\OnlineFiscal;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\RefundPaylovPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Paylov\PaylovFixtures;
use Tests\TestCase;

/**
 * Review 1 (money) of the Paylov wave: RegisterPaylovFiscalReceipt holds a Payment model loaded before its (slow)
 * register call. Whatever is written meanwhile (a refund request in payload.paylov, a refund) must survive the receipt
 * being marked (OnlineFiscalRecorder::mark re-reads the row under its lock), and a refund that landed meanwhile still
 * gets its refund receipt.
 */
class PaylovFiscalRaceTest extends TestCase
{
    use RefreshDatabase;
    use OnlineFiscalFixtures;
    use PaylovFixtures;

    private const QR = 'https://ofd.soliq.uz/epi?t=EP000000000378&r=001&c=20260401063838&s=947030008479';

    /** Runs while Paylov is answering fiscalization/register (another request / job at the same time). */
    private ?\Closure $duringRegister = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOnlineFiscal();
        $this->lineBuilder->charges = ['delivery' => ['mxik' => '10112006002000000', 'package' => '1541880']];
        $this->store->forceFill(['payment_settings' => ['paylov' => ['enabled' => true]]])->save();
        $this->setUpPaylov();

        $ofd = fn (int $receiptId, string $sign) => ['receiptUrl' => self::QR, 'terminalId' => 'EP000000000378', 'receiptId' => $receiptId, 'fiscalSign' => $sign];
        $this->paylovRoutes = [
            '/merchant/fiscalization/register/' => function (Request $request) use ($ofd) {
                if ($this->duringRegister) {
                    $during = $this->duringRegister;
                    $this->duringRegister = null;
                    $during();
                }

                return Http::response(['result' => ['ofd' => $ofd(173723, '947030008479')]]);
            },
            '/merchant/fiscalization/status/' => fn () => Http::response(['result' => null, 'error' => ['code' => 'ofd_check_not_found', 'message' => 'x']], 400),
            '/merchant/fiscalization/refund/' => fn (Request $request) => Http::response(['result' => [
                'ofd' => ['refund' => $ofd(184636, '583109124612')],
                'sale' => $ofd((int) $request['receiptId'], '947030008479'),
            ]]),
        ];
    }

    private function payment(string $txn): Payment
    {
        return Payment::where('provider', 'paylov')->where('provider_transaction_id', $txn)->sole();
    }

    public function test_an_order_cancelled_while_the_receipt_is_being_registered_still_gets_its_money_back(): void
    {
        $order = $this->makeFiscalOrder('paylov');
        $txn = (string) Str::uuid();
        // The refund job is queued after the cancel commits; a busy queue runs it a little later
        Bus::fake([RefundPaylovPayment::class]);

        // The store cancels the paid order while the fiscal job waits for Paylov's register answer
        $this->duringRegister = function () use ($order) {
            app(OrderStatusService::class)->transition(Order::find($order->id), OrderStatus::CANCELED, null, ['cancel_reason' => CancelReason::STORE_CLOSED->value]);
        };

        $this->perform($order, $txn)->assertExactJson(self::answer());
        Bus::assertDispatched(RefundPaylovPayment::class);

        // The refund job runs now
        $outcome = app(PaylovPayments::class)->refund((int) $this->payment($txn)->id);

        $this->assertSame('refunded', $outcome, 'the refund request written by the cancel was wiped by the fiscal job: payload.paylov.refund = '
            . json_encode(PaylovPayments::state($this->payment($txn))['refund'] ?? null));
        $this->assertSame(PaymentStatus::REFUNDED, $this->payment($txn)->status);
    }

    public function test_a_payment_refunded_while_the_receipt_is_being_registered_gets_a_refund_receipt(): void
    {
        $order = $this->makeFiscalOrder('paylov');
        $txn = (string) Str::uuid();

        // Paylov's business panel cancels the transaction (cancel webhook) while the register call is in flight
        $this->duringRegister = function () use ($order, $txn) {
            // The cancel is real: Paylov's getTransactions reports it cancelled from now on
            $this->paylovStatus = 'cancel';
            $this->cancelWebhook($order, $txn)->assertOk();
        };

        $this->perform($order, $txn)->assertExactJson(self::answer());

        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertSame('recorded', $payment->payload['fiscal']['status'] ?? null, 'a sale receipt is recorded');
        // The sale receipt is on record for money that was given back: a refund receipt must follow
        $this->assertCount(1, $this->sentTo('/merchant/fiscalization/refund/'), 'no refund receipt was ever asked for');
    }
}
