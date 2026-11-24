<?php

namespace Tests\Feature\CustomerReceipts;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Jobs\SendCustomerReceiptNotice;
use App\Models\FiscalReceipt;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\SyncQueue;
use Illuminate\Testing\TestResponse;
use ReflectionProperty;
use RuntimeException;
use Tests\Feature\ManagementFixtures;
use Tests\TestCase;

/**
 * Wave P (receipts): the customer's receipt notice is a side effect. A queue that cannot take it (Redis down) must not
 * turn Payme's SetFiscalData into a system error after the receipt was committed, and must not mark the receipt as
 * notified (customer_notified_at stays empty: nothing was sent).
 */
class ReceiptNoticeQueueFailureTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    private const TRX = '61396aaed8b87a4c215ae556';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpManagement();
        $this->product->forceFill(['mxik_code' => '10202001001000001', 'package_code' => '1503985'])->save();
        $this->store->forceFill(['tin' => '305123456', 'tax_regime' => 'vat', 'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox']])->save();
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'store_payme_key']],
        ]);
    }

    /**
     * The sync queue refuses to take SendCustomerReceiptNotice, as a push to an unreachable Redis would.
     */
    private function breakQueueForTheNotice(): void
    {
        $manager = app('queue');
        $manager->addConnector('sync', fn () => new class implements ConnectorInterface
        {
            public function connect(array $config)
            {
                return new class(true) extends SyncQueue
                {
                    /**
                     * @param  object|string  $job  a job object when dispatched (the parent's docblock says string)
                     */
                    protected function executeJob($job, $data = '', $queue = null)
                    {
                        if ($job instanceof SendCustomerReceiptNotice) {
                            throw new RuntimeException('simulated: queue backend unreachable at push');
                        }

                        return parent::executeJob($job, $data, $queue);
                    }
                };
            }
        });
        (new ReflectionProperty($manager, 'connections'))->setValue($manager, []);
    }

    private function rpc(string $method, array $params): TestResponse
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params],
            ['Authorization' => 'Basic ' . base64_encode('Paycom:store_payme_key')]);
    }

    private function fiscalData(string $type): array
    {
        $perform = $type === 'PERFORM';

        return ['id' => self::TRX, 'type' => $type, 'fiscal_data' => [
            'receipt_id' => $perform ? 121 : 123, 'status_code' => 0, 'message' => 'accepted', 'terminal_id' => 'EP000000000025',
            'fiscal_sign' => $perform ? '800031554082' : '900031555055',
            'qr_code_url' => 'https://ofd.soliq.uz/epi?t=EP000000000025&r=' . ($perform ? 121 : 123),
            'date' => $perform ? '20220706221021' : '20220706231125',
        ]];
    }

    public function test_set_fiscal_data_answers_success_when_the_notice_cannot_be_queued(): void
    {
        $order = $this->checkout($this->customer, 3, 'payme');
        $this->rpc('CreateTransaction', ['id' => self::TRX, 'time' => 1760000000000, 'amount' => 15000000, 'account' => ['order_id' => $order->uuid]]);
        $this->rpc('PerformTransaction', ['id' => self::TRX])->assertJsonPath('result.state', 2);

        $this->breakQueueForTheNotice();

        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'))
            ->assertExactJson(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['success' => true]]);
        $sale = FiscalReceipt::where('type', FiscalReceiptType::SALE)->sole();
        $this->assertNull($sale->customer_notified_at);
        $this->assertSame('recorded', Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal']['status']);

        $this->rpc('CancelTransaction', ['id' => self::TRX, 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->rpc('SetFiscalData', $this->fiscalData('CANCEL'))
            ->assertExactJson(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['success' => true]]);
        $refund = FiscalReceipt::where('type', FiscalReceiptType::REFUND)->sole();
        $this->assertNull($refund->customer_notified_at);
    }
}
