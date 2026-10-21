<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\Gateways\FiscalGateway;
use App\Domain\Fiscal\Gateways\FiscalGatewayException;
use App\Domain\Fiscal\Gateways\FiscalProviderResponse;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\FiscalReceipt;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P3-11: FiscalIssuer: registering receipts through the sandbox and null drivers, idempotency, the document rules,
 * pending → retry → issued, failures, external (Payme/Click) receipts and refund links.
 */
class FiscalIssuerTest extends TestCase
{
    use RefreshDatabase;
    use FiscalFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFiscal();
    }

    private function expectReason(string $reason, callable $call, ?string $message = null): void
    {
        try {
            $call();
            $this->fail("Expected FiscalException {$reason}");
        } catch (FiscalException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
            if ($message !== null) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_a_sale_is_registered_by_the_sandbox_with_a_repeatable_fiscal_sign_and_qr(): void
    {
        Event::fake([FiscalReceiptUpdatedBroadcast::class]);

        $result = $this->issuer()->issue($this->document(lines: [$this->line('100000'), $this->line('12000', self::CHOY_MXIK, name: 'Choy')]));

        $this->assertTrue($result->isIssued());
        $this->assertMatchesRegularExpression('/^\d{12}$/', (string) $result->fiscalSign);
        $this->assertStringStartsWith('https://fiscal-sandbox.invalid/check?t=SANDBOX-', (string) $result->qrUrl);

        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame('sandbox', $receipt->driver);
        $this->assertTrue($receipt->non_fiscal);
        $this->assertSame('112000.00', $receipt->total);
        $this->assertSame('12000.00', $receipt->vat_total); // 10714.29 + 1285.71
        $this->assertSame(FiscalPaymentForm::CASH, $receipt->payment_form);
        $this->assertSame(1, $receipt->attempts);
        $this->assertNotNull($receipt->receipt_number);
        $this->assertSame('vat', $receipt->tax_regime);
        $this->assertSame(['order', 1], [$receipt->subject_type, $receipt->subject_id]);
        $this->assertSame('10714.29', $receipt->lines[0]['vat_amount']);
        Event::assertDispatched(FiscalReceiptUpdatedBroadcast::class, fn ($event) => $event->uuid === $receipt->uuid && $event->status === 'issued'
            && $event->broadcastOn()[1]->name === "private-pos-branch.{$this->branch->id}");

        // The same receipt always gets the same answer from the sandbox
        $again = app(\App\Domain\Fiscal\Gateways\SandboxFiscalGateway::class)->register($receipt, $receipt->toDocument(), $this->store);
        $this->assertSame($receipt->fiscal_sign, $again->fiscalSign);
        $this->assertSame($receipt->qr_url, $again->qrUrl);
    }

    public function test_a_store_outside_fiscal_mode_records_non_fiscal_receipts(): void
    {
        $this->store->forceFill(['fiscal_settings' => ['enabled' => false, 'driver' => 'sandbox']])->save();

        $result = $this->issuer()->issue($this->document());

        $this->assertTrue($result->isIssued());
        $this->assertNull($result->fiscalSign);
        $this->assertNull($result->qrUrl);
        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame('null', $receipt->driver);
        $this->assertTrue($receipt->non_fiscal);
        $this->assertSame('Fiskal emas', $receipt->provider_payload['note']);
    }

    public function test_the_same_idempotency_key_returns_the_same_receipt(): void
    {
        $document = $this->document(key: 'bill-42-close');

        $first = $this->issuer()->issue($document);
        $second = $this->issuer()->issue($document);

        $this->assertSame($first->receiptUuid, $second->receiptUuid);
        $this->assertSame($first->fiscalSign, $second->fiscalSign);
        $this->assertSame(1, FiscalReceipt::count());
        $this->assertSame(1, $this->receipt($first->receiptUuid)->attempts);

        // The key belongs to the store: another store may use the same one
        $this->fiscalMode($this->foreignStore);
        $foreign = $this->issuer()->issue($this->document(key: 'bill-42-close', store: $this->foreignStore));
        $this->assertNotSame($first->receiptUuid, $foreign->receiptUuid);

        // A different receipt under a used key is refused
        $this->expectReason(FiscalException::IDEMPOTENCY_CONFLICT, fn () => $this->issuer()->issue($this->document(lines: [$this->line('5000')], key: 'bill-42-close')));
    }

    public function test_documents_that_do_not_add_up_are_refused(): void
    {
        $cases = [
            'lines vs total' => [fn () => $this->document(lines: [$this->line('100000')], total: '90000', payments: [$this->cash('90000')]), 'qatorlari summasi'],
            'payments vs total' => [fn () => $this->document(payments: [$this->cash('50000')]), "To'lovlar summasi"],
            'no lines' => [fn () => $this->document(lines: [], total: '1000', payments: [$this->cash('1000')]), 'kamida bitta qator'],
            'no payments' => [fn () => $this->document(payments: []), "kamida bitta to'lov"],
            'mixed is not a payment' => [fn () => $this->document(payments: [new FiscalPayment(FiscalPaymentForm::MIXED, Money::of(100000))]), 'aralash'],
            'wrong vat' => [fn () => $this->document(lines: [new \App\Domain\Fiscal\FiscalLine('Osh', self::OSH_MXIK, null, 'dona', \App\Support\Quantity::of(1), Money::of(1000), Money::of(1000), '12', Money::of('120'))]), 'QQS summasi'],
            'bad mxik' => [fn () => $this->document(lines: [$this->line('1000', '123')]), '17 ta raqam'],
            'zero quantity' => [fn () => $this->document(lines: [new \App\Domain\Fiscal\FiscalLine('Osh', self::OSH_MXIK, null, 'dona', \App\Support\Quantity::zero(), Money::of(1000), Money::of(1000), '0', Money::zero())]), 'miqdor'],
            'prepayment on an advance' => [fn () => $this->document(FiscalReceiptType::ADVANCE, [$this->line('100000', '10202002004000001')], [$this->cash('100000', true)]), 'faqat sotuv chekida'],
            'advance with a dish' => [fn () => $this->document(FiscalReceiptType::ADVANCE, [$this->line('100000')]), "Bo'nak (avans) chekida bitta qator"],
            'advance with two lines' => [fn () => $this->document(FiscalReceiptType::ADVANCE, [$this->line('1000', '10202002004000001'), $this->line('1000', '10202002004000001')]), "Bo'nak (avans) chekida bitta qator"],
            'credit with a dish' => [fn () => $this->document(FiscalReceiptType::CREDIT, [$this->line('100000')]), 'Kredit chekida bitta qator'],
            'foreign branch' => [fn () => new FiscalDocument(FiscalReceiptType::SALE, $this->store->id, $this->foreignBranch->id, null, [$this->line('1000')], [$this->cash('1000')], Money::of(1000), 'k-fb'), 'Filial'],
        ];

        foreach ($cases as $label => [$make, $message]) {
            $this->expectReason(FiscalException::INVALID_DOCUMENT, fn () => $this->issuer()->issue($make()), $message);
        }
        $this->expectReason(FiscalException::MXIK_MISSING, fn () => $this->issuer()->issue($this->document(lines: [$this->line('1000', '')])), "MXIK'siz");
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND)), 'asl sotuv cheki');

        $this->assertSame(0, FiscalReceipt::count());
    }

    public function test_a_turnover_tax_store_puts_no_vat_on_its_receipts(): void
    {
        $this->store->forceFill(['tax_regime' => 'turnover'])->save();

        $this->expectReason(FiscalException::INVALID_DOCUMENT, fn () => $this->issuer()->issue($this->document()), "aylanma solig'ida");

        $result = $this->issuer()->issue($this->document(lines: [$this->line('100000', rate: '0')]));
        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame('0.00', $receipt->vat_total);
        $this->assertTrue($receipt->lines[0]['no_vat']);
        $this->assertSame('turnover', $receipt->tax_regime);
    }

    public function test_the_payment_form_is_mixed_only_for_money_received_in_several_forms(): void
    {
        $mixed = $this->issuer()->issue($this->document(payments: [$this->cash('40000'), $this->card('60000')]));
        $this->assertSame(FiscalPaymentForm::MIXED, $this->receipt($mixed->receiptUuid)->payment_form);
        $this->assertEquals([
            ['form' => 'cash', 'amount' => '40000.00', 'prepayment' => false, 'credit' => false, 'card_type' => null, 'transaction_id' => null],
            ['form' => 'card', 'amount' => '60000.00', 'prepayment' => false, 'credit' => false, 'card_type' => 'personal', 'transaction_id' => 'TX-1'],
        ], $this->receipt($mixed->receiptUuid)->payments);

        // The advance offset is not money received now
        $offset = $this->issuer()->issue($this->document(payments: [new FiscalPayment(FiscalPaymentForm::QR, Money::of(30000), true), $this->cash('70000')]));
        $this->assertSame(FiscalPaymentForm::CASH, $this->receipt($offset->receiptUuid)->payment_form);

        $prepaid = $this->issuer()->issue($this->document(payments: [new FiscalPayment(FiscalPaymentForm::QR, Money::of(100000), true)]));
        $this->assertSame(FiscalPaymentForm::QR, $this->receipt($prepaid->receiptUuid)->payment_form);
    }

    public function test_the_four_receipt_types_in_the_sandbox(): void
    {
        // Booking day: advance (bo'nak) of 1 000 000 by QR: one deposit line, no fiscal sign, no QR
        $deposit = $this->lines()->forCharge($this->store, 'deposit', Money::of(1000000));
        $advance = $this->issuer()->issue($this->document(FiscalReceiptType::ADVANCE, [$deposit], [new FiscalPayment(FiscalPaymentForm::QR, Money::of(1000000))], subjectType: 'booking_payment', subjectId: 7));
        $this->assertTrue($advance->isIssued());
        $this->assertNull($advance->fiscalSign);
        $this->assertNull($advance->qrUrl);
        $this->assertNotNull($this->receipt($advance->receiptUuid)->receipt_number);

        // Thursday: the full 4 480 000 sale, 1 000 000 of it offset by the advance, the rest cash and card
        $room = '10202002001000001';
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'charges' => ['room_fee' => ['mxik' => $room], 'service_charge' => ['mxik' => '10202002005000001']]]])->save();
        $item = $this->orderItem('76', '50000'); // 3 800 000 of food
        $lines = [
            $this->lines()->forOrderItem($item, \App\Support\Quantity::of(76), Money::of(3800000)),
            $this->lines()->forCharge($this->store, 'room_fee', Money::of(300000)),
            $this->lines()->forCharge($this->store, 'service_charge', Money::of(380000)),
        ];
        $sale = $this->issuer()->issue($this->document(FiscalReceiptType::SALE, $lines, [
            new FiscalPayment(FiscalPaymentForm::QR, Money::of(1000000), true),
            $this->cash('2000000'),
            $this->card('1480000'),
        ], original: $advance->receiptUuid, subjectType: 'order', subjectId: $item->order_id));
        $this->assertTrue($sale->isIssued());
        $this->assertNotNull($sale->fiscalSign);
        $saleReceipt = $this->receipt($sale->receiptUuid);
        $this->assertSame('4480000.00', $saleReceipt->total);
        // VAT is rounded per line: 407142.86 + 32142.86 + 40714.29 (12/112 of 4 480 000 is 480 000 exactly)
        $this->assertSame('480000.01', $saleReceipt->vat_total);
        $this->assertSame(FiscalPaymentForm::MIXED, $saleReceipt->payment_form);
        $this->assertSame($advance->receiptUuid, $saleReceipt->originalReceipt?->uuid);

        // Nasiya repayment: a credit receipt, no fiscal sign
        $credit = $this->issuer()->issue($this->document(FiscalReceiptType::CREDIT, [$this->lines()->forCharge($this->store, 'credit', Money::of(500000))], original: $sale->receiptUuid));
        $this->assertTrue($credit->isIssued());
        $this->assertNull($credit->fiscalSign);

        // A refund of part of the sale is fiscal and points at the sale
        $refund = $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('100000')], original: $sale->receiptUuid));
        $this->assertTrue($refund->isIssued());
        $this->assertNotNull($refund->fiscalSign);
        $this->assertSame($saleReceipt->id, $this->receipt($refund->receiptUuid)->original_receipt_id);

        $this->assertSame(['advance', 'sale', 'credit', 'refund'], FiscalReceipt::orderBy('id')->pluck('type')->map->value->all());
    }

    public function test_refunds_name_an_issued_sale_and_never_exceed_what_is_left_of_it(): void
    {
        $sale = $this->issuer()->issue($this->document(lines: [$this->line('100000')]));
        $deposit = $this->lines()->forCharge($this->store, 'deposit', Money::of(50000));
        $advance = $this->issuer()->issue($this->document(FiscalReceiptType::ADVANCE, [$deposit]));

        $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('60000')], original: $sale->receiptUuid));
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('50000')], original: $sale->receiptUuid)), 'qolgan summadan (40000.00)');
        $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('40000')], original: $sale->receiptUuid));

        // A cancelled booking's deposit may be given back with a refund of its advance receipt (P3-17's option)
        $depositRefund = $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$deposit], original: $advance->receiptUuid));
        $this->assertTrue($depositRefund->isIssued());
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('1000')], original: $advance->receiptUuid)), 'qolgan summadan (0.00)');
        $credit = $this->issuer()->issue($this->document(FiscalReceiptType::CREDIT, [$this->lines()->forCharge($this->store, 'credit', Money::of(1000))], original: $sale->receiptUuid));
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('1000')], original: $credit->receiptUuid)), "Faqat sotuv yoki bo'nak");
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('1000')], original: (string) \Illuminate\Support\Str::uuid())), 'topilmadi');

        // Another store's sale cannot be refunded here
        $this->fiscalMode($this->foreignStore);
        $foreignSale = $this->issuer()->issue($this->document(store: $this->foreignStore));
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('1000')], original: $foreignSale->receiptUuid)), 'topilmadi');

        // A sale the provider has not confirmed yet cannot be refunded
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 5]]])->save();
        $pending = $this->issuer()->issue($this->document());
        $this->assertSame('pending', $pending->status);
        $this->expectReason(FiscalException::ORIGINAL_RECEIPT, fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('1000')], original: $pending->receiptUuid)), "ro'yxatdan o'tmagan");
    }

    public function test_an_unreachable_provider_leaves_the_sale_pending_until_a_retry_issues_it(): void
    {
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 1]]])->save();

        $result = $this->issuer()->issue($this->document(subjectType: 'order_bill', subjectId: 9));

        $this->assertSame('pending', $result->status);
        $this->assertStringContainsString('javob bermadi', (string) $result->error);
        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame(1, $receipt->attempts);
        $this->assertEqualsWithDelta(now()->addSeconds(30)->timestamp, $receipt->next_attempt_at?->timestamp, 2);
        $this->assertTrue($this->issuer()->hasPending('order_bill', 9));
        $this->assertFalse($this->issuer()->hasPending('order_bill', 10));

        // The queued retry (sync in tests) ran at once and found it not due yet; the sweeper does nothing either
        $this->artisan('fiscal:retry-pending')->assertSuccessful();
        $this->assertSame(1, $receipt->fresh()->attempts);

        $this->travel(31)->seconds();
        $this->artisan('fiscal:retry-pending')->expectsOutput('1 receipt(s) attempted')->assertSuccessful();

        $receipt->refresh();
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertSame(2, $receipt->attempts);
        $this->assertNull($receipt->error);
        $this->assertNull($receipt->next_attempt_at);
        $this->assertMatchesRegularExpression('/^\d{12}$/', (string) $receipt->fiscal_sign);
        $this->assertFalse($this->issuer()->hasPending('order_bill', 9));

        // Issuing the same document again now returns the issued receipt
        $this->assertTrue($this->issuer()->issue($receipt->toDocument())->isIssued());
    }

    public function test_the_retry_job_registers_a_due_receipt_and_backs_off_after_each_failure(): void
    {
        config(['fiscal.retry.backoff_seconds' => [30, 60, 120]]);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 3]]])->save();
        $receipt = $this->receipt($this->issuer()->issue($this->document())->receiptUuid);

        $this->travel(31)->seconds();
        (new \App\Domain\Fiscal\RetryFiscalReceipt($receipt->id))->handle(app(FiscalService::class));
        $receipt->refresh();
        $this->assertSame([2, 'pending'], [$receipt->attempts, $receipt->status->value]);
        $this->assertEqualsWithDelta(now()->addSeconds(60)->timestamp, $receipt->next_attempt_at?->timestamp, 2);

        $this->travel(61)->seconds();
        app(FiscalService::class)->attempt($receipt->id);
        $this->assertEqualsWithDelta(now()->addSeconds(120)->timestamp, $receipt->fresh()->next_attempt_at?->timestamp, 2);

        $this->travel(121)->seconds();
        $this->assertSame(FiscalReceiptStatus::ISSUED, app(FiscalService::class)->attempt($receipt->id)?->status);
        // Nothing left to do: a late job is a no-op
        $this->assertNull(app(FiscalService::class)->attempt($receipt->id));
    }

    public function test_a_refused_receipt_fails_at_once_and_retries_that_run_out_fail_too(): void
    {
        Event::fake([FiscalReceiptUpdatedBroadcast::class]);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['reject' => true]]])->save();

        $rejected = $this->issuer()->issue($this->document());
        $this->assertSame('failed', $rejected->status);
        $this->assertStringContainsString('rad etdi', (string) $rejected->error);
        $this->assertFalse($this->issuer()->hasPending('order', 1)); // failed is not "waiting for the provider"
        Event::assertDispatched(FiscalReceiptUpdatedBroadcast::class, fn ($event) => $event->status === 'failed');

        config(['fiscal.retry.max_attempts' => 2]);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 9]]])->save();
        $receipt = $this->receipt($this->issuer()->issue($this->document())->receiptUuid);
        $this->travel(31)->seconds();
        $this->artisan('fiscal:retry-pending')->assertSuccessful();
        $receipt->refresh();
        $this->assertSame(FiscalReceiptStatus::FAILED, $receipt->status);
        $this->assertStringStartsWith('2 marta urinildi:', (string) $receipt->error);

        // The owner fixes the cause and retries by hand
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox']])->save();
        $retried = app(FiscalService::class)->retryNow($receipt);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $retried->status);
        $this->assertSame(3, $retried->attempts);
    }

    public function test_any_driver_error_keeps_the_receipt_pending_and_a_retry_asks_the_provider_first(): void
    {
        $gateway = new class implements FiscalGateway {
            public int $registered = 0;

            public bool $failNext = true;

            public ?FiscalProviderResponse $known = null;

            public function register(FiscalReceipt $receipt, \App\Domain\Fiscal\FiscalDocument $document, Store $store): FiscalProviderResponse
            {
                $this->registered++;
                // The provider takes the receipt, but the answer never arrives
                $this->known = new FiscalProviderResponse(fiscalSign: '000000000042', receiptNumber: '42', qrUrl: 'https://example.test/q', terminalId: 'VK-1');
                if ($this->failNext) {
                    $this->failNext = false;
                    throw new \RuntimeException('cURL error 28: timed out');
                }

                return $this->known;
            }

            public function status(FiscalReceipt $receipt, Store $store): ?FiscalProviderResponse
            {
                return $this->known;
            }
        };
        $this->app->instance('fake-gateway', $gateway);
        config(['fiscal.drivers.fake' => 'fake-gateway']);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'fake']])->save();

        $result = $this->issuer()->issue($this->document());
        $this->assertSame('pending', $result->status);
        $this->assertSame("Fiskal provayder bilan bog'lanib bo'lmadi.", $result->error); // no internals leak

        $this->travel(31)->seconds();
        $this->artisan('fiscal:retry-pending')->assertSuccessful();

        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertSame('000000000042', $receipt->fiscal_sign);
        $this->assertFalse($receipt->non_fiscal);
        $this->assertSame(1, $gateway->registered); // found by the status lookup, not sent twice
    }

    public function test_inside_a_transaction_the_provider_is_asked_only_after_the_commit(): void
    {
        $uuid = DB::transaction(function () {
            $result = $this->issuer()->issue($this->document(key: 'tx-1'));
            $this->assertSame('pending', $result->status);
            $this->assertSame(0, $this->receipt($result->receiptUuid)->attempts);

            return $result->receiptUuid;
        });
        $this->assertSame(FiscalReceiptStatus::ISSUED, $this->receipt($uuid)->status);

        // A sale that rolls back leaves no receipt, and the provider never heard of it
        try {
            DB::transaction(function () {
                $this->issuer()->issue($this->document(key: 'tx-2'));
                throw new \RuntimeException('bill close failed');
            });
        } catch (\RuntimeException) {
        }
        $this->assertFalse(FiscalReceipt::where('idempotency_key', 'tx-2')->exists());
    }

    public function test_payme_and_click_receipts_are_recorded_as_issued(): void
    {
        $payme = $this->issuer()->recordExternal($this->document(key: 'payme:tx-1', withBranch: false), 'payme', [
            'id' => 'tx-1',
            'type' => 'PERFORM',
            'fiscal_data' => [
                'status_code' => 0, 'message' => 'accepted', 'terminal_id' => 'EP000000000025', 'receipt_id' => 2207,
                'date' => '20261022123000', 'fiscal_sign' => '384598238471', 'qr_code_url' => 'https://ofd.soliq.uz/epi?t=EP000000000025&r=2207',
            ],
        ]);

        $this->assertTrue($payme->isIssued());
        $receipt = $this->receipt($payme->receiptUuid);
        $this->assertSame(['external', 'payme'], [$receipt->driver, $receipt->provider]);
        $this->assertSame(['384598238471', '2207', 'EP000000000025'], [$receipt->fiscal_sign, $receipt->receipt_number, $receipt->terminal_id]);
        $this->assertSame('https://ofd.soliq.uz/epi?t=EP000000000025&r=2207', $receipt->qr_url);
        $this->assertFalse($receipt->non_fiscal);
        $this->assertNull($receipt->error);

        // Repeated callbacks keep one receipt
        $this->assertSame($payme->receiptUuid, $this->issuer()->recordExternal($this->document(key: 'payme:tx-1', withBranch: false), 'payme', [])->receiptUuid);
        $this->assertSame(1, FiscalReceipt::where('provider', 'payme')->count());

        // Click's spelling; a document problem never loses a receipt the provider already registered
        $click = $this->issuer()->recordExternal($this->document(key: 'click:77', payments: [$this->cash('1000')], withBranch: false), 'click', ['fiscalSign' => '111', 'qrCodeURL' => 'https://ofd.soliq.uz/epi?x=1', 'terminalId' => 'T1']);
        $clickReceipt = $this->receipt($click->receiptUuid);
        $this->assertSame(['issued', '111', 'T1'], [$clickReceipt->status->value, $clickReceipt->fiscal_sign, $clickReceipt->terminal_id]);
        $this->assertStringContainsString("To'lovlar summasi", (string) $clickReceipt->error);
    }

    public function test_has_pending_follows_the_subject(): void
    {
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'sandbox' => ['fail_attempts' => 1]]])->save();
        $this->issuer()->issue($this->document(subjectType: 'order', subjectId: 5));

        $this->assertTrue($this->issuer()->hasPending('order', 5));
        $this->assertFalse($this->issuer()->hasPending('order_bill', 5));
        $this->assertFalse($this->issuer()->hasPending('order', 6));
    }
}
