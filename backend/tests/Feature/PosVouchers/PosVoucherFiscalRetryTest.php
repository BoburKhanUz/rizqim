<?php

namespace Tests\Feature\PosVouchers;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalIssueResult;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Models\FiscalReceipt;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Review C12 (P6 wave 4): a certificate sale whose ADVANCE receipt could not be asked for (fiscal_status `error`) or
 * that the provider refused (`failed`) is sent again by the scheduled pos:vouchers-retry-receipts (the same receipt
 * key: never a second receipt), from where the fiscal queue's own retries take it.
 */
class PosVoucherFiscalRetryTest extends TestCase
{
    use PosVouchersFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpVouchers();
        $this->openShift();
    }

    public function test_an_error_advance_receipt_is_issued_by_the_retry_and_only_once(): void
    {
        $this->app->instance(FiscalIssuer::class, new class extends \Tests\Feature\PosBilling\FakeFiscalIssuer
        {
            public function issue(FiscalDocument $document): FiscalIssueResult
            {
                throw new RuntimeException('provider down');
            }
        });
        $sold = $this->sell('100000', 'card', ['card_type' => 'humo', 'transaction_id' => 'RRN7'])->assertCreated()
            ->assertJsonPath('data.fiscal.status', 'error')->assertJsonPath('data.fiscal.receipt_uuid', null)->json('data');
        $this->assertSame(0, FiscalReceipt::count());

        $this->app->forgetInstance(FiscalIssuer::class);
        $this->artisan('pos:vouchers-retry-receipts')->assertSuccessful();

        $voucher = Voucher::where('uuid', $sold['uuid'])->sole();
        $receipt = FiscalReceipt::sole();
        $this->assertSame('issued', $voucher->fiscal_status);
        $this->assertSame($receipt->uuid, $voucher->fiscal_receipt_uuid);
        $this->assertSame('advance', $receipt->type->value);
        $this->assertSame('voucher', $receipt->subject_type);
        $this->assertSame('100000.00', (string) $receipt->total);
        $this->assertSame('card', $receipt->payments[0]['form']);

        // Again: nothing to do, still one receipt
        $this->artisan('pos:vouchers-retry-receipts')->assertSuccessful();
        $this->assertSame(1, FiscalReceipt::count());
    }

    public function test_a_failed_advance_receipt_is_sent_again_as_the_same_receipt(): void
    {
        $sold = $this->sell('50000')->assertCreated()->assertJsonPath('data.fiscal.status', 'issued')->json('data');
        $receipt = FiscalReceipt::sole();
        $receipt->forceFill(['status' => FiscalReceiptStatus::FAILED, 'error' => 'rad etildi'])->save();
        Voucher::where('uuid', $sold['uuid'])->update(['fiscal_status' => 'failed']);

        $this->artisan('pos:vouchers-retry-receipts')->assertSuccessful();

        $this->assertSame(1, FiscalReceipt::count());
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->fresh()->status);
        $this->assertSame('issued', Voucher::where('uuid', $sold['uuid'])->value('fiscal_status'));
    }
}
