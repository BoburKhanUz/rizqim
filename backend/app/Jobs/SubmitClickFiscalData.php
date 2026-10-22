<?php

namespace App\Jobs;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Fiscal\ClickFiscalItems;
use App\Services\Payment\Fiscal\ClickMerchantApi;
use App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P3-14: after a Click payment completes, sends the order's receipt items to Click (submit_items) so Click registers
 * the fiscal receipt with the restaurant as komitent, then reads the receipt link back (ofd_data) and records it in
 * fiscal_receipts. Runs on the queue, so the Complete callback never waits for it; outages and a receipt that is not
 * ready yet are retried with backoff. https://docs.click.uz/merchant-api/fiscalization
 */
class SubmitClickFiscalData implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;

    public int $timeout = 60;

    public function __construct(public int $paymentId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600, 7200, 14400];
    }

    public function handle(OnlineFiscalReceiptBuilder $receipts, OnlineFiscalRecorder $recorder, ClickMerchantApi $click): void
    {
        $payment = Payment::with('order.store')->find($this->paymentId);
        $order = $payment?->order;

        if (!$payment || !$order || $payment->provider !== 'click' || $payment->status !== PaymentStatus::PAID) {
            return;
        }

        $fiscal = is_array($payment->payload['fiscal'] ?? null) ? $payment->payload['fiscal'] : [];
        if (($fiscal['status'] ?? null) === OnlineFiscalRecorder::STATUS_RECORDED) {
            return;
        }

        $receipt = $receipts->build($order, true);
        if (!$receipt->enabled) {
            return;
        }

        $credentials = $click->credentials($order);
        if ($credentials === null) {
            $receipt = $receipt->withProblems(['click_credentials_missing']);
        }
        if (!$receipt->isIssuable() || $credentials === null) {
            $recorder->blocked($order, 'click', $receipt, $payment);

            return;
        }

        $serviceId = (int) ($payment->payload['service_id'] ?? $credentials['service_id']);
        $paymentId = (int) $payment->provider_transaction_id;

        try {
            if (empty($fiscal['items_submitted_at'])) {
                $response = $click->submitItems($credentials, $serviceId, $paymentId, ClickFiscalItems::from($receipt), $receipt->total->toTiyin());
                if (!$this->succeeded($response)) {
                    $this->retryOrGiveUp($payment, $recorder, 'click_submit_failed', $this->error($response), $this->isPermanent($response));

                    return;
                }

                $recorder->mark($payment, [
                    'provider' => 'click',
                    'status' => OnlineFiscalRecorder::STATUS_PENDING,
                    'problems' => [],
                    'items_submitted_at' => now()->toIso8601String(),
                ]);
            }

            $response = $click->fiscalData($credentials, $serviceId, $paymentId);
        } catch (ConnectionException $e) {
            $this->retryOrGiveUp($payment, $recorder, 'click_submit_failed', $e->getMessage());

            return;
        }

        $qrUrl = $response->successful() ? $response->json('qrCodeURL') : null;
        if (!is_string($qrUrl) || $qrUrl === '') {
            // Click has not registered the receipt yet: ask again later
            $this->retryOrGiveUp($payment, $recorder, 'click_qr_missing', $this->error($response), $this->isPermanent($response));

            return;
        }

        $providerData = ['payment_id' => $paymentId, 'service_id' => $serviceId, 'qr_code_url' => $qrUrl] + ClickMerchantApi::parseQrUrl($qrUrl);
        $result = $recorder->record($order, $payment, 'click', FiscalReceiptType::SALE, "click:{$paymentId}:sale", $providerData);

        $recorder->mark($payment, [
            'status' => OnlineFiscalRecorder::STATUS_RECORDED,
            'problems' => [],
            'qr_code_url' => $qrUrl,
            'receipt_uuid' => $result?->receiptUuid,
        ]);
    }

    /**
     * The worker gave up after an unexpected exception (or a timeout).
     */
    public function failed(?Throwable $e): void
    {
        $payment = Payment::find($this->paymentId);
        if ($payment) {
            $this->giveUp($payment, app(OnlineFiscalRecorder::class), 'click_submit_failed', (string) $e?->getMessage());
        }
    }

    protected function succeeded(Response $response): bool
    {
        return $response->successful() && (int) $response->json('error_code', -1) === 0;
    }

    /**
     * A request Click refuses outright (bad credentials, unknown payment, invalid items) is not retried; outages,
     * rate limits and Click-side errors are.
     */
    protected function isPermanent(Response $response): bool
    {
        return $response->clientError() && $response->status() !== 429 && $response->status() !== 404;
    }

    protected function error(Response $response): string
    {
        return mb_substr("HTTP {$response->status()}: " . $response->body(), 0, 500);
    }

    protected function retryOrGiveUp(Payment $payment, OnlineFiscalRecorder $recorder, string $problem, string $error, bool $permanent = false): void
    {
        // The sync driver (tests, local) cannot retry later, so it gives up at once like a last attempt
        if (!$permanent && $this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 14400);

            return;
        }

        $this->giveUp($payment, $recorder, $problem, $error);
    }

    protected function giveUp(Payment $payment, OnlineFiscalRecorder $recorder, string $problem, string $error): void
    {
        Log::warning('Click fiscal data failed', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'attempts' => $this->attempts(),
            'problem' => $problem,
            'error' => $error,
        ]);

        $recorder->mark($payment, [
            'provider' => 'click',
            'status' => OnlineFiscalRecorder::STATUS_FAILED,
            'problems' => [$problem],
        ]);
    }
}
