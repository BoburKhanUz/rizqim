<?php

namespace Tests\Feature\OnlineFiscal;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Paylov\PaylovFixtures;
use Tests\TestCase;

/**
 * Wave P: the fiscal receipt of an online Paylov payment, registered once the payment is confirmed:
 * fiscalization/register {transactionId, items} (developer.paylov.uz/subscribe/ofd/register), a receipt already
 * generated read back with fiscalization/status (subscribe/ofd/get-receipt), and on a full refund the refund receipt:
 * fiscalization/status?refunded=true, else fiscalization/refund {receiptId} (subscribe/ofd/refund-receipt). Recorded
 * as the Payme/Click receipts are (OnlineFiscalRecorder, payload.fiscal).
 */
class PaylovFiscalReceiptTest extends TestCase
{
    use RefreshDatabase;
    use OnlineFiscalFixtures;
    use PaylovFixtures;

    private const QR = 'https://ofd.soliq.uz/epi?t=EP000000000378&r=001&c=20260401063838&s=947030008479';

    /** @var array<string, mixed>|null what register answers: null for success, else ['code' => ..., 'status' => ...] */
    private ?array $registerError = null;

    private bool $statusHasRefund = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOnlineFiscal();
        $this->lineBuilder->charges = ['delivery' => ['mxik' => '10112006002000000', 'package' => '1541880']];
        $this->store->forceFill(['payment_settings' => ['paylov' => ['enabled' => true]]])->save();
        $this->setUpPaylov();

        $ofd = fn (int $receiptId, string $sign) => ['receiptUrl' => self::QR, 'terminalId' => 'EP000000000378', 'receiptId' => $receiptId, 'fiscalSign' => $sign];
        $this->paylovRoutes = [
            '/merchant/fiscalization/register/' => fn (Request $request) => $this->registerError
                ? Http::response(['result' => null, 'error' => ['code' => $this->registerError['code'], 'message' => $this->registerError['code']]], $this->registerError['status'] ?? 400)
                : Http::response(['result' => ['ofd' => $ofd(173723, '947030008479')]]),
            '/merchant/fiscalization/status/' => function (Request $request) use ($ofd) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $refunded = ($query['refunded'] ?? '') === 'true';
                if ($refunded && !$this->statusHasRefund) {
                    return Http::response(['result' => null, 'error' => ['code' => 'ofd_check_not_found', 'message' => 'ofd_check_not_found']], 400);
                }

                return Http::response(['result' => ['success' => true, 'isRefund' => $refunded] + $ofd($refunded ? 184636 : 173723, $refunded ? '583109124612' : '947030008479')]);
            },
            '/merchant/fiscalization/refund/' => fn (Request $request) => Http::response(['result' => [
                'ofd' => ['refund' => $ofd(184636, '583109124612')],
                'sale' => $ofd((int) $request['receiptId'], '947030008479'),
            ]]),
        ];
    }

    private function pay(Order $order): string
    {
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());

        return $txn;
    }

    private function fiscal(string $txn): array
    {
        return Payment::where('provider', 'paylov')->where('provider_transaction_id', $txn)->sole()->payload['fiscal'] ?? [];
    }

    public function test_a_confirmed_payment_registers_the_receipt_with_the_orders_lines_and_records_it(): void
    {
        $order = $this->makeFiscalOrder('paylov');

        $txn = $this->pay($order);

        $register = $this->sentTo('/merchant/fiscalization/register/');
        $this->assertCount(1, $register);
        $this->assertSame('POST', $register[0]->method());
        $this->assertSame('Bearer ' . $this->accessToken, $register[0]->header('Authorization')[0]);
        $this->assertSame(['transactionId', 'items'], array_keys($register[0]->data()));
        $this->assertSame($txn, $register[0]['transactionId']);

        $items = $register[0]['items'];
        $this->assertCount(3, $items);
        $this->assertSame(['title', 'price', 'discount', 'count', 'code', 'vat_percent', 'package_code', 'tin'], array_keys($items[0]));
        $this->assertSame(['Osh', 2, '10202001001000001', 12, '1503985', '305123456'], [$items[0]['title'], $items[0]['count'], $items[0]['code'], $items[0]['vat_percent'], $items[0]['package_code'], $items[0]['tin']]);
        // A weighed quantity is one position at the line amount, the quantity in its title
        $this->assertSame(1, $items[1]['count']);
        $this->assertStringStartsWith('Somsa (1', $items[1]['title']);
        // Paylov has no `shipping`: the delivery fee is its own line with the store's delivery MXIK
        $this->assertSame(['10112006002000000', '1541880', 1500000, 1, 0], [$items[2]['code'], $items[2]['package_code'], $items[2]['price'], $items[2]['count'], $items[2]['discount']]);
        // Integer tiyin adding up exactly to the transaction (invalid_amount otherwise)
        $this->assertSame(13499999, array_sum(array_map(fn (array $item) => $item['price'] * $item['count'] - $item['discount'], $items)));
        foreach ($items as $item) {
            $this->assertIsInt($item['price']);
            $this->assertLessThanOrEqual($item['price'] * $item['count'], $item['discount']);
            $this->assertArrayNotHasKey('pinfl', $item);
        }

        $fiscal = $this->fiscal($txn);
        $this->assertSame(['paylov', 'recorded', 173723, self::QR], [$fiscal['provider'], $fiscal['status'], $fiscal['receipt_id'], $fiscal['qr_code_url']]);
        $recorded = $this->issuer->recorded["paylov:{$txn}:sale"];
        $this->assertSame([FiscalReceiptType::SALE, 'paylov'], [$recorded['document']->type, $recorded['provider']]);
        $this->assertSame(['transaction_id' => $txn, 'receipt_id' => 173723, 'qr_code_url' => self::QR, 'terminal_id' => 'EP000000000378', 'fiscal_sign' => '947030008479'], $recorded['data']);
        $this->assertSame($fiscal['receipt_uuid'], $recorded['result']->receiptUuid);
    }

    public function test_no_receipt_is_registered_before_paylov_confirms_the_payment(): void
    {
        $order = $this->makeFiscalOrder('paylov');
        $this->paylovStatus = 'pending';

        $txn = $this->pay($order);
        $this->assertCount(0, $this->sentTo('/merchant/fiscalization/register/'));
        $this->assertSame([], $this->fiscal($txn));

        $this->paylovStatus = 'payed';
        Artisan::call('payments:paylov-reconcile');
        $this->assertCount(1, $this->sentTo('/merchant/fiscalization/register/'));
        $this->assertSame('recorded', $this->fiscal($txn)['status']);
    }

    public function test_a_store_without_fiscal_receipts_registers_none_and_one_that_cannot_issue_is_blocked(): void
    {
        $this->setFiscal(false, '305123456');
        $txn = $this->pay($this->makeFiscalOrder('paylov'));
        $this->assertSame([], $this->fiscal($txn));

        $this->setFiscal(true, null);
        $txn = $this->pay($this->makeFiscalOrder('paylov'));
        $this->assertSame(['paylov', 'blocked', ['store_tin_missing']], [$this->fiscal($txn)['provider'], $this->fiscal($txn)['status'], $this->fiscal($txn)['problems']]);

        $this->assertCount(0, $this->sentTo('/merchant/fiscalization/register/'));
    }

    public function test_a_receipt_paylov_already_generated_is_read_back_and_recorded(): void
    {
        $this->registerError = ['code' => 'ofd_check_already_generated'];

        $txn = $this->pay($this->makeFiscalOrder('paylov'));

        $status = $this->sentTo('/merchant/fiscalization/status/');
        $this->assertCount(1, $status);
        $this->assertSame(self::BASE . '/merchant/fiscalization/status/?refunded=false&transactionId=' . $txn, $status[0]->url());
        $this->assertSame(['recorded', 173723], [$this->fiscal($txn)['status'], $this->fiscal($txn)['receipt_id']]);
    }

    public function test_a_receipt_paylov_refuses_is_marked_failed_and_the_payment_stays_paid(): void
    {
        $this->registerError = ['code' => 'ofd_commitent_or_subcommission_not_active'];

        $txn = $this->pay($this->makeFiscalOrder('paylov'));

        $this->assertSame(['failed', ['paylov_ofd_commitent_or_subcommission_not_active']], [$this->fiscal($txn)['status'], $this->fiscal($txn)['problems']]);
        $this->assertSame(PaymentStatus::PAID, Payment::where('provider_transaction_id', $txn)->sole()->status);
        $this->assertSame([], $this->issuer->recorded);
    }

    public function test_a_full_refund_records_the_refund_receipt_asking_paylov_for_one_only_when_it_has_none(): void
    {
        $order = $this->makeFiscalOrder('paylov');
        $txn = $this->pay($order);

        app(OrderStatusService::class)->transition($order->fresh(), OrderStatus::CANCELED, null, ['cancel_reason' => CancelReason::STORE_CLOSED->value]);

        $this->assertSame(PaymentStatus::REFUNDED, Payment::where('provider_transaction_id', $txn)->sole()->status);
        // The cancellation did not carry a refund receipt: Paylov is asked for one with the sale's receiptId
        $refund = $this->sentTo('/merchant/fiscalization/refund/');
        $this->assertCount(1, $refund);
        $this->assertSame(['receiptId' => 173723], $refund[0]->data());

        $fiscal = $this->fiscal($txn);
        $this->assertSame(['recorded', 184636], [$fiscal['refund_status'], $fiscal['refund_receipt_id']]);
        $recorded = $this->issuer->recorded["paylov:{$txn}:refund"];
        $this->assertSame(FiscalReceiptType::REFUND, $recorded['document']->type);
        $this->assertSame($fiscal['receipt_uuid'], $recorded['document']->originalReceiptUuid);

        // Paylov's own cancel webhook afterwards records nothing twice
        $this->cancelWebhook($order, $txn)->assertOk();
        $this->assertCount(1, $this->sentTo('/merchant/fiscalization/refund/'));

        // When cancelling already produced the refund receipt, it is only read back
        $this->statusHasRefund = true;
        $second = $this->makeFiscalOrder('paylov');
        $secondTxn = $this->pay($second);
        app(OrderStatusService::class)->transition($second->fresh(), OrderStatus::CANCELED, null, ['cancel_reason' => CancelReason::STORE_CLOSED->value]);
        $this->assertCount(1, $this->sentTo('/merchant/fiscalization/refund/'));
        $this->assertSame(184636, $this->fiscal($secondTxn)['refund_receipt_id']);
    }
}
