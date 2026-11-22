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
 *
 * Review 12: only an https link on the tax committee's receipt host (qr_payments.uzqr.receipt_hosts, ofd.soliq.uz)
 * goes; any other is `failed` without a call. Review 11: send() on a sync queue inside a request runs it after the
 * answer was sent (the cashier never waits for the operator); with a real queue it is queued after commit as before.
 */
class SendUzQrReceiptLink implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public int $timeout = 60;

    public function __construct(public int $qrId, public int $receiptId) {}

    /**
     * Sends it now on a queue worker (after commit), or — on a sync queue inside a request ($inRequest: by default,
     * not running in the console) — after the answer went out.
     */
    public static function send(int $qrId, int $receiptId, ?bool $inRequest = null): void
    {
        $inRequest ??= !app()->runningInConsole();
        if ($inRequest && config('queue.connections.' . config('queue.default') . '.driver') === 'sync') {
            app()->terminating(fn () => self::dispatchSync($qrId, $receiptId));

            return;
        }
        self::dispatch($qrId, $receiptId);
    }

    /**
     * Whether $url is a receipt link the operator may be given: https, no credentials or port, on an allowed host.
     */
    public static function allowedLink(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = array_map('strtolower', array_filter((array) config('qr_payments.uzqr.receipt_hosts', []), 'is_string'));

        return $host !== '' && in_array($host, $allowed, true) && preg_match('/[\s\x00-\x1F]/', $url) !== 1;
    }

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
        if (!self::allowedLink($url)) {
            Log::warning('UzQR receipt link refused: not an https tax receipt link', ['qr' => $qr->uuid, 'receipt' => $receipt->uuid]);
            $this->mark($qr, ['status' => 'failed', 'url' => null, 'receipt' => $receipt->uuid, 'error' => 'not an https link on an allowed tax receipt host']);

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
