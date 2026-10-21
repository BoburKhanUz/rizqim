<?php

namespace App\Domain\Fiscal;

use App\Domain\Fiscal\Gateways\FiscalGatewayException;
use App\Domain\Fiscal\Gateways\FiscalGatewayManager;
use App\Domain\Fiscal\Gateways\FiscalProviderResponse;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\FiscalReceipt;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FiscalIssuer on top of the fiscal_receipts table and the store's FiscalGateway (P3-11).
 *
 * issue(): validates the document, returns the receipt already stored under (store, idempotency key) if there is
 * one, else writes the receipt as `pending` and then asks the driver. The provider is asked only once the caller's
 * transaction (if any) has committed, so a sale that rolls back is never registered; inside a transaction the
 * returned result is therefore `pending` (read the receipt again after the commit). A provider that is unreachable
 * leaves the receipt `pending`: RetryFiscalReceipt tries again with backoff, and the fiscal:retry-pending sweeper
 * picks up whatever a lost job or a dead worker left behind. A provider that refuses the receipt, or retries that
 * run out (config fiscal.retry.max_attempts), make it `failed`; the owner can retry it by hand (retryNow).
 */
class FiscalService implements FiscalIssuer
{
    public function __construct(
        private readonly FiscalGatewayManager $gateways,
        private readonly FiscalDocumentValidator $validator,
    ) {}

    public function issue(FiscalDocument $document): FiscalIssueResult
    {
        $store = $this->store($document);

        $existing = $this->existing($document);
        if ($existing !== null) {
            return $this->repeat($existing, $document);
        }

        $original = $this->original($document);
        $this->validator->validate($document, $store, $original);

        $receipt = $this->create($document, $store, $original, [
            'driver' => FiscalSettings::of($store)->driver(),
            'status' => FiscalReceiptStatus::PENDING,
            'next_attempt_at' => now(),
        ]);
        if (!$receipt->wasRecentlyCreated) {
            return $this->repeat($receipt, $document);
        }

        $id = (int) $receipt->id;
        DB::afterCommit(fn () => $this->attempt($id));

        return ($receipt->fresh() ?? $receipt)->toResult();
    }

    public function recordExternal(FiscalDocument $document, string $provider, array $providerData): FiscalIssueResult
    {
        $store = $this->store($document);
        $provider = mb_substr(trim($provider), 0, 32);
        if ($provider === '' || trim($document->idempotencyKey) === '') {
            throw FiscalException::invalid("Tashqi chek uchun provayder nomi va chek kaliti kerak.");
        }

        $data = $this->externalFields($providerData);
        $existing = $this->existing($document);
        if ($existing !== null) {
            if ($existing->status !== FiscalReceiptStatus::ISSUED) {
                $this->markIssued($existing, new FiscalProviderResponse(
                    fiscalSign: $data['fiscal_sign'], receiptNumber: $data['receipt_number'], qrUrl: $data['qr_url'],
                    terminalId: $data['terminal_id'], payload: $providerData,
                ));
            }

            return ($existing->fresh() ?? $existing)->toResult();
        }

        // The provider has registered this receipt already: it is stored whatever its shape, a problem only noted
        $original = $this->original($document, lenient: true);
        $warning = null;
        try {
            $this->validator->validate($document, $store, $original);
        } catch (FiscalException $e) {
            $warning = $e->getMessage();
            Log::warning('External fiscal receipt recorded despite a document problem', ['store_id' => $store->id, 'provider' => $provider, 'problem' => $warning]);
        }

        $receipt = $this->create($document, $store, $original, [
            'driver' => 'external',
            'provider' => $provider,
            'status' => FiscalReceiptStatus::ISSUED,
            'fiscal_sign' => $data['fiscal_sign'],
            'receipt_number' => $data['receipt_number'],
            'qr_url' => $data['qr_url'],
            'terminal_id' => $data['terminal_id'],
            'provider_payload' => $providerData,
            'error' => $warning,
            'issued_at' => now(),
        ]);

        return $receipt->toResult();
    }

    public function hasPending(string $subjectType, int $subjectId): bool
    {
        return FiscalReceipt::forSubject($subjectType, $subjectId)->where('status', FiscalReceiptStatus::PENDING->value)->exists();
    }

    /**
     * One attempt at registering a pending receipt: claimed atomically (pending, due, or any pending one with
     * $force), so two workers never send it at once; a worker that dies mid-call leaves it claimed until
     * config('fiscal.retry.lease_seconds') passes. Null when there was nothing to do.
     */
    public function attempt(int $receiptId, bool $force = false): ?FiscalReceipt
    {
        $now = now();
        $claimed = FiscalReceipt::whereKey($receiptId)
            ->where('status', FiscalReceiptStatus::PENDING->value)
            ->when(!$force, fn ($query) => $query->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now)))
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'next_attempt_at' => $now->copy()->addSeconds(max(10, (int) config('fiscal.retry.lease_seconds', 120))),
                'updated_at' => $now,
            ]);
        if ($claimed === 0) {
            return null;
        }

        $receipt = FiscalReceipt::with(['store', 'originalReceipt'])->findOrFail($receiptId);
        $store = $receipt->store;

        try {
            if (!$store instanceof Store) {
                throw FiscalGatewayException::rejected("Do'kon topilmadi.");
            }
            $gateway = $this->gateways->driver($receipt->driver);
            // A retry asks first: the provider may have taken the receipt just before an earlier attempt timed out
            $response = ($receipt->attempts > 1 ? $gateway->status($receipt, $store) : null)
                ?? $gateway->register($receipt, $receipt->toDocument(), $store);
            $this->markIssued($receipt, $response);
        } catch (FiscalGatewayException $e) {
            $e->retryable ? $this->scheduleRetry($receipt, $e->getMessage()) : $this->markFailed($receipt, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->scheduleRetry($receipt, "Fiskal provayder bilan bog'lanib bo'lmadi.");
        }

        return $receipt->fresh();
    }

    /**
     * "Qayta yuborish" from the panel: a failed receipt becomes pending again and is tried at once.
     */
    public function retryNow(FiscalReceipt $receipt): FiscalReceipt
    {
        if ($receipt->status === FiscalReceiptStatus::FAILED) {
            FiscalReceipt::whereKey($receipt->id)->where('status', FiscalReceiptStatus::FAILED->value)
                ->update(['status' => FiscalReceiptStatus::PENDING->value, 'next_attempt_at' => now(), 'updated_at' => now()]);
        }

        $this->attempt((int) $receipt->id, force: true);

        return $receipt->fresh() ?? $receipt;
    }

    /**
     * The scheduler's safety net (fiscal:retry-pending): every pending receipt that is due gets an attempt; one that
     * already had all its attempts is failed. Returns how many receipts were attempted.
     */
    public function sweep(int $limit = 100): int
    {
        $max = $this->maxAttempts();

        FiscalReceipt::where('status', FiscalReceiptStatus::PENDING->value)
            ->where('attempts', '>=', $max)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(fn (FiscalReceipt $receipt) => $this->markFailed($receipt, $receipt->error ?? "Urinishlar tugadi."));

        $ids = FiscalReceipt::where('status', FiscalReceiptStatus::PENDING->value)
            ->where('attempts', '<', $max)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $attempted = 0;
        foreach ($ids as $id) {
            try {
                if ($this->attempt((int) $id) !== null) {
                    $attempted++;
                }
            } catch (Throwable $e) {
                // One receipt never holds up the others; the next run tries again
                report($e);
            }
        }

        return $attempted;
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('fiscal.retry.max_attempts', 12));
    }

    private function store(FiscalDocument $document): Store
    {
        $store = Store::find($document->storeId);
        if (!$store instanceof Store) {
            throw FiscalException::invalid("Do'kon topilmadi.");
        }

        return $store;
    }

    private function existing(FiscalDocument $document): ?FiscalReceipt
    {
        return FiscalReceipt::where('store_id', $document->storeId)->where('idempotency_key', $document->idempotencyKey)->first();
    }

    /**
     * The same key again: the stored receipt, unless it is a different receipt (type or total). A pending one that
     * is due gets another attempt right away.
     */
    private function repeat(FiscalReceipt $receipt, FiscalDocument $document): FiscalIssueResult
    {
        if ($receipt->type !== $document->type || !$document->total->equals($receipt->total)) {
            throw FiscalException::idempotencyConflict($receipt->uuid);
        }

        if ($receipt->status === FiscalReceiptStatus::PENDING && $receipt->driver !== 'external'
            && ($receipt->next_attempt_at === null || $receipt->next_attempt_at->lte(now()))) {
            $id = (int) $receipt->id;
            DB::afterCommit(fn () => $this->attempt($id));
        }

        return ($receipt->fresh() ?? $receipt)->toResult();
    }

    private function original(FiscalDocument $document, bool $lenient = false): ?FiscalReceipt
    {
        if ($document->originalReceiptUuid === null) {
            return null;
        }

        $original = preg_match('/^[0-9a-f-]{36}$/i', $document->originalReceiptUuid)
            ? FiscalReceipt::where('store_id', $document->storeId)->where('uuid', $document->originalReceiptUuid)->first()
            : null;
        if ($original === null && !$lenient) {
            throw FiscalException::originalReceipt('Asl chek topilmadi.');
        }

        return $original;
    }

    /**
     * Writes the receipt; under a concurrent issue with the same key, the row the other request wrote. The insert
     * runs in its own (nested) transaction, so a unique violation never aborts the caller's transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function create(FiscalDocument $document, Store $store, ?FiscalReceipt $original, array $attributes): FiscalReceipt
    {
        $regime = TaxRegime::ofStore($store->tax_regime);
        $vatTotal = array_reduce($document->lines, fn ($sum, FiscalLine $line) => $sum->plus($line->vatAmount), \App\Support\Money::zero());

        try {
            return DB::transaction(fn () => FiscalReceipt::create($attributes + [
                'store_id' => $store->id,
                'branch_id' => $document->branchId,
                'device_id' => $document->deviceId,
                'type' => $document->type,
                'payment_form' => FiscalSnapshot::paymentForm($document->payments),
                'idempotency_key' => $document->idempotencyKey,
                'subject_type' => $document->subjectType,
                'subject_id' => $document->subjectId,
                'tax_regime' => $regime->value,
                'total' => $document->total->toString(),
                'vat_total' => $vatTotal->toString(),
                'lines' => FiscalSnapshot::lines($document->lines, $regime),
                'payments' => FiscalSnapshot::payments($document->payments),
                'original_receipt_id' => $original?->id,
            ]));
        } catch (UniqueConstraintViolationException $e) {
            return $this->existing($document) ?? throw $e;
        }
    }

    private function markIssued(FiscalReceipt $receipt, FiscalProviderResponse $response): void
    {
        $receipt->forceFill([
            'status' => FiscalReceiptStatus::ISSUED,
            'fiscal_sign' => $response->fiscalSign,
            'receipt_number' => $response->receiptNumber,
            'qr_url' => $response->qrUrl,
            'terminal_id' => $response->terminalId,
            'non_fiscal' => $response->nonFiscal,
            'provider_payload' => $response->payload ?: null,
            'error' => null,
            'next_attempt_at' => null,
            'issued_at' => $response->issuedAt ?? now(),
        ])->save();

        FiscalReceiptUpdatedBroadcast::dispatch($receipt);
    }

    private function markFailed(FiscalReceipt $receipt, string $error): void
    {
        $receipt->forceFill([
            'status' => FiscalReceiptStatus::FAILED,
            'error' => mb_substr($error, 0, 2000),
            'next_attempt_at' => null,
        ])->save();

        FiscalReceiptUpdatedBroadcast::dispatch($receipt);
    }

    private function scheduleRetry(FiscalReceipt $receipt, string $error): void
    {
        $attempts = (int) $receipt->attempts;
        if ($attempts >= $this->maxAttempts()) {
            $this->markFailed($receipt, "{$attempts} marta urinildi: {$error}");

            return;
        }

        $backoff = array_values(array_map('intval', (array) config('fiscal.retry.backoff_seconds', [60])));
        $delay = max(1, $backoff[min($attempts, count($backoff)) - 1] ?? 60);
        $next = now()->addSeconds($delay);

        $receipt->forceFill(['error' => mb_substr($error, 0, 2000), 'next_attempt_at' => $next])->save();

        RetryFiscalReceipt::dispatch((int) $receipt->id)
            ->delay($next)
            ->onQueue((string) config('fiscal.retry.queue', 'default'))
            ->afterCommit();
    }

    /**
     * The fiscal sign, receipt number, QR link and terminal of a Payme (SetFiscalData: fiscal_data) or Click answer,
     * whichever spelling it uses.
     *
     * @param  array<string, mixed>  $data
     * @return array{fiscal_sign: string|null, receipt_number: string|null, qr_url: string|null, terminal_id: string|null}
     */
    private function externalFields(array $data): array
    {
        $flat = $data;
        foreach (['fiscal_data', 'fiscalData', 'receipt'] as $nested) {
            if (is_array($data[$nested] ?? null)) {
                $flat = $data[$nested] + $flat;
            }
        }

        $pick = function (array $keys, int $max) use ($flat): ?string {
            foreach ($keys as $key) {
                $value = Arr::get($flat, $key);
                if (is_scalar($value) && (string) $value !== '') {
                    return mb_substr((string) $value, 0, $max);
                }
            }

            return null;
        };

        return [
            'fiscal_sign' => $pick(['fiscal_sign', 'fiscalSign', 'FiscalSign', 'sign'], 64),
            'receipt_number' => $pick(['receipt_number', 'receipt_id', 'receiptId', 'ReceiptId', 'receipt_seq'], 64),
            'qr_url' => $pick(['qr_code_url', 'qr_url', 'qrCodeURL', 'qrCodeUrl', 'QRCodeURL'], 1000),
            'terminal_id' => $pick(['terminal_id', 'terminalId', 'TerminalID', 'fiscal_module_id'], 64),
        ];
    }
}
