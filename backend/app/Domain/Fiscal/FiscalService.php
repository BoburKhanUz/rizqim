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
use Illuminate\Support\Str;
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
 * run out (config fiscal.retry.max_attempts), make it `failed`; the owner can retry it by hand (retryNow), and the
 * POS asks again for it with reissue() (review P3-35).
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

        // Validated under the lock (M3): two refunds of one sale are checked one after the other
        $receipt = $this->create($document, $store, $original, [
            // P6-16: a smart kassa's receipts go through the provider's virtual kassa bound to it (once it is the store's)
            'driver' => FiscalSettings::of($store)->driverFor($document->deviceId !== null ? \App\Models\Device::find($document->deviceId) : null, $store),
            'status' => FiscalReceiptStatus::PENDING,
            'next_attempt_at' => now(),
        ], fn () => $this->validator->validate($document, $store, $original));
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
            return $this->recordedAgain($existing, $data, $providerData);
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
        if (!$receipt->wasRecentlyCreated) {
            // Written meanwhile by a concurrent request with the same key
            return $this->recordedAgain($receipt, $data, $providerData);
        }

        return $receipt->toResult();
    }

    /**
     * @param  array{fiscal_sign: string|null, receipt_number: string|null, qr_url: string|null, terminal_id: string|null}  $data
     * @param  array<string, mixed>  $providerData
     */
    private function recordedAgain(FiscalReceipt $existing, array $data, array $providerData): FiscalIssueResult
    {
        if ($existing->status !== FiscalReceiptStatus::ISSUED) {
            $this->markIssued($existing, new FiscalProviderResponse(
                fiscalSign: $data['fiscal_sign'], receiptNumber: $data['receipt_number'], qrUrl: $data['qr_url'],
                terminalId: $data['terminal_id'], payload: $providerData,
            ));
        }

        return ($existing->fresh() ?? $existing)->toResult();
    }

    public function hasPending(string $subjectType, int $subjectId): bool
    {
        return FiscalReceipt::forSubject($subjectType, $subjectId)->where('status', FiscalReceiptStatus::PENDING->value)->exists();
    }

    /**
     * One attempt at registering a pending receipt: claimed atomically (pending, due, or any pending one with
     * $force), so two workers never send it at once; a worker that dies mid-call leaves it claimed until
     * config('fiscal.retry.lease_seconds') passes. Review P3-35: the claim is a lease (`lease_token`, `leased_until`)
     * that even $force respects, and only the attempt holding it writes the answer. Null when there was nothing to do.
     */
    public function attempt(int $receiptId, bool $force = false): ?FiscalReceipt
    {
        $now = now();
        $lease = $now->copy()->addSeconds(max(10, (int) config('fiscal.retry.lease_seconds', 120)));
        $token = (string) Str::uuid();
        $claimed = FiscalReceipt::whereKey($receiptId)
            ->where('status', FiscalReceiptStatus::PENDING->value)
            ->where(fn ($query) => $query->whereNull('leased_until')->orWhere('leased_until', '<=', $now))
            ->when(!$force, fn ($query) => $query->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now)))
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'next_attempt_at' => $lease,
                'leased_until' => $lease,
                'lease_token' => $token,
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
            // M3: no foreign key keeps the original; a month taken offline (archived) takes it out of the table. A
            // refund is never sent without the receipt it refunds
            if ($receipt->original_receipt_id !== null && $receipt->originalReceipt === null) {
                throw FiscalGatewayException::rejected("Asl chek bazada yo'q (arxivga o'tkazilgan): bu chekni tizim orqali yuborib bo'lmaydi.");
            }
            $gateway = $this->gateways->driver($receipt->driver);
            // A retry asks first: the provider may have taken the receipt just before an earlier attempt timed out
            $response = ($receipt->attempts > 1 ? $gateway->status($receipt, $store) : null)
                ?? $gateway->register($receipt, $receipt->toDocument(), $store);
            $this->markIssued($receipt, $response, $token);
        } catch (FiscalGatewayException $e) {
            $e->retryable ? $this->scheduleRetry($receipt, $e->getMessage(), $token) : $this->markFailed($receipt, $e->getMessage(), $token);
        } catch (Throwable $e) {
            report($e);
            $this->scheduleRetry($receipt, "Fiskal provayder bilan bog'lanib bo'lmadi.", $token);
        }

        return $receipt->fresh();
    }

    /**
     * "Qayta yuborish" from the panel: a failed receipt becomes pending again and is tried at once (unless an attempt
     * is running right now: that one answers).
     */
    public function retryNow(FiscalReceipt $receipt): FiscalReceipt
    {
        $this->reopen($receipt);
        $this->attempt((int) $receipt->id, force: true);

        return $receipt->fresh() ?? $receipt;
    }

    /**
     * Review P3-35: issue(), and a receipt the provider refused under the same key is sent again (the same receipt,
     * never a second one): the till's "retry", the order's close and the shift's close ask through here.
     */
    public function reissue(FiscalDocument $document): FiscalIssueResult
    {
        $existing = $this->existing($document);
        if ($existing === null || $existing->status !== FiscalReceiptStatus::FAILED || $existing->driver === 'external') {
            return $this->issue($document);
        }
        if ($existing->type !== $document->type || !$document->total->equals($existing->total)) {
            throw FiscalException::idempotencyConflict($existing->uuid);
        }

        $this->reopen($existing);
        $id = (int) $existing->id;
        DB::afterCommit(fn () => $this->attempt($id, force: true));

        return ($existing->fresh() ?? $existing)->toResult();
    }

    /**
     * A failed receipt back to pending, due now.
     */
    private function reopen(FiscalReceipt $receipt): void
    {
        FiscalReceipt::whereKey($receipt->id)->where('status', FiscalReceiptStatus::FAILED->value)
            ->update(['status' => FiscalReceiptStatus::PENDING->value, 'next_attempt_at' => now(), 'leased_until' => null, 'lease_token' => null, 'updated_at' => now()]);
    }

    /**
     * The scheduler's safety net (fiscal:retry-pending): every pending receipt that is due gets an attempt; one that
     * already had all its attempts is failed. Returns how many receipts were attempted.
     *
     * M3: the retry queries look only at months the window config('fiscal.retry.max_age_days') reaches (partition
     * pruning: the sweep runs every minute; the stale query reads the older online months through their (status,
     * created_at) index, one empty probe each). A receipt still pending beyond the window is not left pending for ever:
     * it becomes `failed` with a message (the owner's "Qayta yuborish" asks the provider first), which the failed
     * receipt lists and the fiscal_failures alert show.
     */
    public function sweep(int $limit = 100): int
    {
        $max = $this->maxAttempts();
        $since = now()->subDays($this->maxAgeDays());

        FiscalReceipt::where('status', FiscalReceiptStatus::PENDING->value)
            ->where('created_at', '<', $since)
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->each(fn (FiscalReceipt $receipt) => $this->markFailed($receipt, "{$this->maxAgeDays()} kundan ortiq ro'yxatdan o'tmay turdi: avtomatik urinish to'xtatildi. Qo'lda qayta yuboring." . ($receipt->error !== null ? " Oxirgi xato: {$receipt->error}" : '')));

        FiscalReceipt::where('status', FiscalReceiptStatus::PENDING->value)
            ->where('created_at', '>=', $since)
            ->where('attempts', '>=', $max)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(fn (FiscalReceipt $receipt) => $this->markFailed($receipt, $receipt->error ?? "Urinishlar tugadi."));

        $ids = FiscalReceipt::where('status', FiscalReceiptStatus::PENDING->value)
            ->where('created_at', '>=', $since)
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

    /**
     * How far back the sweep looks (days): a pending receipt older than this is failed, not retried.
     */
    public function maxAgeDays(): int
    {
        return max(1, (int) config('fiscal.retry.max_age_days', 30));
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

    /**
     * The receipt stored under the document's (store, key), found through fiscal_receipt_keys: one index probe there
     * and one in the receipt's own month (no probe per partition). A key whose receipt is in an archived month throws
     * (a second receipt is never written under it). $thorough (under the lock, before an insert): a key missing from
     * fiscal_receipt_keys is also looked for in fiscal_receipts itself, for a database where the trigger was missing
     * for a while (PartitionConverter --rollback).
     */
    private function existing(FiscalDocument $document, bool $thorough = false): ?FiscalReceipt
    {
        $key = DB::table('fiscal_receipt_keys')->where('store_id', $document->storeId)->where('idempotency_key', $document->idempotencyKey)->first();
        if ($key !== null) {
            return $this->stored($key) ?? throw FiscalException::keyArchived((string) $key->uuid, $this->month($key));
        }

        return $thorough
            ? FiscalReceipt::where('store_id', $document->storeId)->where('idempotency_key', $document->idempotencyKey)->first()
            : null;
    }

    /**
     * The receipt a fiscal_receipt_keys row names, read in its month only; null when that month is archived.
     */
    private function stored(object $key): ?FiscalReceipt
    {
        return FiscalReceipt::whereKey((int) $key->receipt_id)->where('created_at', $key->receipt_created_at)->first();
    }

    private function month(object $key): string
    {
        return substr((string) $key->receipt_created_at, 0, 7);
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

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $document->originalReceiptUuid)) {
            return $lenient ? null : throw FiscalException::originalReceipt('Asl chek topilmadi.');
        }

        // M3: through fiscal_receipt_keys (global UNIQUE uuid), then the receipt's own month; the row is gone when its
        // month is archived (a refund needs its status, total and earlier refunds: refused with a clear message)
        $key = DB::table('fiscal_receipt_keys')->where('uuid', $document->originalReceiptUuid)->first();
        if ($key !== null && (int) $key->store_id === $document->storeId) {
            $original = $this->stored($key);
            if ($original === null && !$lenient) {
                throw FiscalException::originalArchived($this->month($key));
            }

            return $original;
        }
        $original = $key === null
            ? FiscalReceipt::where('store_id', $document->storeId)->where('uuid', $document->originalReceiptUuid)->first()
            : null;
        if ($original === null && !$lenient) {
            throw FiscalException::originalReceipt('Asl chek topilmadi.');
        }

        return $original;
    }

    /**
     * Writes the receipt, or returns the one stored under the same key (wasRecentlyCreated false). M3: in one
     * transaction (its own, or a savepoint of the caller's): the advisory lock on (store, key) (FiscalReceiptLock),
     * for a refund also on the refunded receipt (two refunds of one sale are validated one after the other), then the
     * lookup, then $validate, then the insert. The lock is held until the caller's outer transaction ends; a unique
     * violation (fiscal_receipt_keys) rolls back only the savepoint, never the caller's transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  (\Closure(): void)|null  $validate
     */
    private function create(FiscalDocument $document, Store $store, ?FiscalReceipt $original, array $attributes, ?\Closure $validate = null): FiscalReceipt
    {
        $regime = TaxRegime::ofStore($store->tax_regime);
        $vatTotal = array_reduce($document->lines, fn ($sum, FiscalLine $line) => $sum->plus($line->vatAmount), \App\Support\Money::zero());
        $storeId = (int) $store->id;

        try {
            return DB::transaction(function () use ($document, $store, $original, $attributes, $validate, $regime, $vatTotal, $storeId) {
                FiscalReceiptLock::acquire($storeId, $document->idempotencyKey);
                if ($original !== null && $document->type === FiscalReceiptType::REFUND) {
                    FiscalReceiptLock::acquireRefundsOf((int) $original->id);
                }

                $existing = $this->existing($document, thorough: true);
                if ($existing !== null) {
                    return $existing;
                }
                if ($validate !== null) {
                    $validate();
                }

                return FiscalReceiptLock::writing($storeId, $document->idempotencyKey, fn () => FiscalReceipt::create($attributes + [
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
                    'sold_at' => $document->soldAt,
                ]));
            });
        } catch (UniqueConstraintViolationException $e) {
            // Only a path without the lock (or a uuid collision) gets here: the receipt that holds the key
            return $this->existing($document, thorough: true) ?? throw $e;
        }
    }

    /**
     * The provider registered it. Written by the attempt holding the lease ($token), or with no token (a receipt the
     * payment provider registered itself) over anything but issued; a provider's "issued" also wins over a `failed`
     * an earlier attempt wrote.
     */
    private function markIssued(FiscalReceipt $receipt, FiscalProviderResponse $response, ?string $token = null): void
    {
        $this->settle($receipt, fn (FiscalReceipt $row) => $row->status === FiscalReceiptStatus::FAILED
            || ($row->status === FiscalReceiptStatus::PENDING && ($token === null || $row->lease_token === $token)), [
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
            ]);
    }

    /**
     * Refused or out of attempts: only a pending receipt, by the attempt holding the lease ($token), or with no token
     * (the sweeper) one no attempt holds now.
     */
    private function markFailed(FiscalReceipt $receipt, string $error, ?string $token = null): void
    {
        $this->settle($receipt, fn (FiscalReceipt $row) => $this->mayAnswer($row, $token), [
            'status' => FiscalReceiptStatus::FAILED,
            'error' => mb_substr($error, 0, 2000),
            'next_attempt_at' => null,
        ]);
    }

    private function scheduleRetry(FiscalReceipt $receipt, string $error, string $token): void
    {
        $attempts = (int) $receipt->attempts;
        if ($attempts >= $this->maxAttempts()) {
            $this->markFailed($receipt, "{$attempts} marta urinildi: {$error}", $token);

            return;
        }

        $backoff = array_values(array_map('intval', (array) config('fiscal.retry.backoff_seconds', [60])));
        $delay = max(1, $backoff[min($attempts, count($backoff)) - 1] ?? 60);
        $next = now()->addSeconds($delay);

        $written = $this->settle($receipt, fn (FiscalReceipt $row) => $this->mayAnswer($row, $token), [
            'error' => mb_substr($error, 0, 2000),
            'next_attempt_at' => $next,
        ], broadcast: false);
        if (!$written) {
            return;
        }

        RetryFiscalReceipt::dispatch((int) $receipt->id)
            ->delay($next)
            ->onQueue((string) config('fiscal.retry.queue', 'default'))
            ->afterCommit();
    }

    private function mayAnswer(FiscalReceipt $row, ?string $token): bool
    {
        if ($row->status !== FiscalReceiptStatus::PENDING) {
            return false;
        }

        return $token !== null
            ? $row->lease_token === $token
            : $row->leased_until === null || $row->leased_until->lte(now());
    }

    /**
     * Writes $attributes (and drops the lease) when the row, locked, still lets this writer answer; the receipt model
     * then holds the row. Announces the change (fiscal.receipt_updated) after it is written.
     *
     * @param  \Closure(FiscalReceipt): bool  $allowed
     * @param  array<string, mixed>  $attributes
     */
    private function settle(FiscalReceipt $receipt, \Closure $allowed, array $attributes, bool $broadcast = true): bool
    {
        $written = DB::transaction(function () use ($receipt, $allowed, $attributes) {
            $row = FiscalReceipt::whereKey($receipt->id)->lockForUpdate()->first();
            if ($row === null || !$allowed($row)) {
                return false;
            }

            $row->forceFill($attributes + ['lease_token' => null, 'leased_until' => null])->save();
            $receipt->setRawAttributes($row->getAttributes(), true);

            return true;
        });

        if ($written && $broadcast) {
            FiscalReceiptUpdatedBroadcast::dispatch($receipt);
        }

        return $written;
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
