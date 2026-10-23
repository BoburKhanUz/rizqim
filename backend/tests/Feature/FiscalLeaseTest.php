<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\Gateways\FiscalGateway;
use App\Domain\Fiscal\Gateways\FiscalGatewayException;
use App\Domain\Fiscal\Gateways\FiscalProviderResponse;
use App\Models\FiscalReceipt;
use App\Models\Store;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review P3-35 (review A #7): an attempt registering a receipt holds a lease. A manual retry while it runs never sends
 * the receipt a second time, and an attempt whose lease another one took over never overwrites that one's answer (a
 * late `failed` never replaces `issued`). A refused receipt asked for again (reissue) is sent again.
 */
class FiscalLeaseTest extends TestCase
{
    use FiscalFixtures;
    use RefreshDatabase;

    private object $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFiscal();

        $this->gateway = new class implements FiscalGateway {
            public int $registered = 0;

            /** @var (Closure(FiscalReceipt): FiscalProviderResponse)|null */
            public ?Closure $during = null;

            public function register(FiscalReceipt $receipt, \App\Domain\Fiscal\FiscalDocument $document, Store $store): FiscalProviderResponse
            {
                $this->registered++;
                $during = $this->during;
                $this->during = null;

                return $during !== null ? $during($receipt) : new FiscalProviderResponse(fiscalSign: '000000000042', receiptNumber: (string) $this->registered, qrUrl: 'https://example.test/q', terminalId: 'VK-1');
            }

            public function status(FiscalReceipt $receipt, Store $store): ?FiscalProviderResponse
            {
                return null;
            }
        };
        $this->app->instance('lease-gateway', $this->gateway);
        config(['fiscal.drivers.lease' => 'lease-gateway']);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'lease']])->save();
    }

    public function test_a_manual_retry_while_an_attempt_runs_never_sends_the_receipt_twice(): void
    {
        $service = app(FiscalService::class);
        $this->gateway->during = function (FiscalReceipt $receipt) use ($service) {
            // The owner presses "Qayta yuborish" while the first attempt waits for the provider
            $service->retryNow($receipt);

            return new FiscalProviderResponse(fiscalSign: '000000000042', receiptNumber: '1', qrUrl: 'https://example.test/q', terminalId: 'VK-1');
        };

        $result = $this->issuer()->issue($this->document());

        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertSame(1, $this->gateway->registered);
        $this->assertSame(1, $receipt->attempts);
        $this->assertNull($receipt->lease_token);
    }

    public function test_an_attempt_whose_lease_ran_out_never_overwrites_the_answer_of_the_one_that_took_over(): void
    {
        $service = app(FiscalService::class);
        $this->gateway->during = function (FiscalReceipt $receipt) use ($service) {
            // The first attempt hangs past its lease; the sweeper's attempt takes over and the provider issues it
            $this->travel(config('fiscal.retry.lease_seconds', 120) + 1)->seconds();
            $this->assertSame(FiscalReceiptStatus::ISSUED, $service->attempt((int) $receipt->id)?->status);

            // ... and then the first attempt's late answer comes: refused
            throw FiscalGatewayException::rejected('Kech javob: rad etildi.');
        };

        $result = $this->issuer()->issue($this->document());

        $receipt = $this->receipt($result->receiptUuid);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertNull($receipt->error);
        $this->assertSame(2, $this->gateway->registered);
    }

    public function test_reissue_sends_a_refused_receipt_again_and_issue_does_not(): void
    {
        $this->gateway->during = fn () => throw FiscalGatewayException::rejected('Rad etildi.');
        $document = $this->document(key: 'sale-1');
        $this->assertSame('failed', $this->issuer()->issue($document)->status);

        // issue(): the stored answer
        $this->assertSame('failed', $this->issuer()->issue($document)->status);
        $this->assertSame(1, $this->gateway->registered);

        // reissue(): sent again, the same receipt
        $again = $this->issuer()->reissue($document);
        $this->assertSame('issued', $again->status);
        $this->assertSame(2, $this->gateway->registered);
        $this->assertSame(1, FiscalReceipt::where('idempotency_key', 'sale-1')->count());
    }
}
