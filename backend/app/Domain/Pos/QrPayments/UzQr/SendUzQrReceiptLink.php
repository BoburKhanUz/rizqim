<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Pos\QrPayments\Providers\UzQrProvider;
use App\Domain\Pos\QrPayments\QrProviders;
use App\Models\FiscalReceipt;
use App\Models\PosQrPayment;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave P: sends the link of a bill's fiscalised sale receipt (fiscal_receipts.qr_url) to UzQR for each UzQR request
 * that paid the bill (UzQrGateway::sendReceiptLink; SetRetail10 does it after fiscalisation). Queued after commit:
 * the sale never waits for it or fails with it. Idempotent: a link already sent (the same URL) is not sent again.
 * A failure the operator may get over (no answer, 5xx) is retried with backoff; then, or on a refusal, it is
 * `failed`: logged and shown on the request (pos_qr_payments.provider_payload.receipt_link, QrPaymentPresenter
 * `receipt_link`). A later announcement of the receipt (a retry, the owner's) sends it again.
 */
class SendUzQrReceiptLink implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public int $timeout = 60;

    public function __construct(public int $qrId, public int $receiptId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 14400];
    }

    public function handle(QrProviders $providers): void
    {
        $qr = PosQrPayment::find($this->qrId);
        // Tenancy: the receipt must be the request's store's
        $receipt = $qr !== null ? FiscalReceipt::where('store_id', $qr->store_id)->find($this->receiptId) : null;
        $url = $receipt?->qr_url;
        if ($qr === null || $receipt === null || $receipt->status !== FiscalReceiptStatus::ISSUED || !is_string($url) || $url === '') {
            return;
        }
        $link = (array) (($qr->provider_payload ?? [])['receipt_link'] ?? []);
        if (($link['status'] ?? null) === 'sent' && ($link['url'] ?? null) === $url) {
            return;
        }

        try {
            $driver = $providers->of($qr);
            $gateway = $driver instanceof UzQrProvider ? $driver->gateway() : null;
            if ($gateway === null) {
                throw new UzQrOperatorException('UzQR gateway is not configured', 'not_configured', false);
            }
            $gateway->sendReceiptLink($qr, $url, Store::findOrFail($qr->store_id));
        } catch (Throwable $e) {
            $transient = !$e instanceof UzQrOperatorException || $e->transient;
            if ($transient && $this->job !== null && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
                $this->mark($qr, ['status' => 'pending', 'url' => $url, 'receipt' => $receipt->uuid, 'error' => mb_substr($e->getMessage(), 0, 300)]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 14400);

                return;
            }
            Log::warning('UzQR receipt link not sent', ['qr' => $qr->uuid, 'receipt' => $receipt->uuid, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            $this->mark($qr, ['status' => 'failed', 'url' => $url, 'receipt' => $receipt->uuid, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return;
        }

        $this->mark($qr, ['status' => 'sent', 'url' => $url, 'receipt' => $receipt->uuid, 'error' => null]);
    }

    public function failed(?Throwable $e): void
    {
        $qr = PosQrPayment::find($this->qrId);
        if ($qr !== null) {
            Log::warning('UzQR receipt link not sent', ['qr' => $qr->uuid, 'error' => mb_substr((string) $e?->getMessage(), 0, 300)]);
            $this->mark($qr, ['status' => 'failed', 'error' => mb_substr((string) $e?->getMessage(), 0, 300)]);
        }
    }

    /**
     * Merges the link's state into the request's payload under its lock (a refund writes the same column).
     *
     * @param  array<string, mixed>  $state
     */
    private function mark(PosQrPayment $qr, array $state): void
    {
        DB::transaction(function () use ($qr, $state) {
            $row = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
            $payload = $row->provider_payload ?? [];
            $payload['receipt_link'] = array_merge((array) ($payload['receipt_link'] ?? []), $state, ['at' => now()->toIso8601String()]);
            $row->forceFill(['provider_payload' => $payload])->save();
        });
    }
}
