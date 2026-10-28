<?php

namespace App\Services\Admin\Pos;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalSnapshot;
use App\Models\FiscalReceipt;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P4-24 Fiskal cheklar: failed and pending fiscal receipts of every store, why they did not go through, and the errors
 * per provider (the receipt's driver: the fiscal provider that was asked; `external` is a receipt Payme/Click registered
 * themselves, which cannot be retried here). Retrying goes through FiscalService (the controller).
 */
class PosFiscal
{
    public const ROW = 'array{uuid: string, type: string, status: string, driver: string, provider: string|null, non_fiscal: bool, total: string, error: string|null, attempts: int, next_attempt_at: string|null, created_at: string, issued_at: string|null, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, retryable: bool, subject_type: string|null, subject_id: int|null}';

    /**
     * @param  array<string, mixed>  $filters  status (open = failed or pending, the default; failed; pending; issued; all), driver, store_id, from / to (dates, Asia/Tashkent), q (uuid start, receipt number)
     * @return Builder<FiscalReceipt>
     */
    public function query(array $filters): Builder
    {
        $status = (string) ($filters['status'] ?? 'open');
        $timezone = (string) config('billing.timezone');
        $query = FiscalReceipt::query();

        match ($status) {
            'all' => null,
            'open' => $query->whereIn('status', [FiscalReceiptStatus::FAILED->value, FiscalReceiptStatus::PENDING->value]),
            default => $query->where('status', $status),
        };

        return $query
            ->when(filled($filters['driver'] ?? null), fn (Builder $q) => $q->where('driver', $filters['driver']))
            ->when(filled($filters['store_id'] ?? null), fn (Builder $q) => $q->where('store_id', (int) $filters['store_id']))
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['from'], $timezone)->startOfDay()->utc()))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q->where('created_at', '<', Carbon::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc()))
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters) {
                $term = strtolower(trim((string) $filters['q']));
                $q->where(fn (Builder $inner) => $inner
                    ->where('uuid', 'like', addcslashes($term, '%_\\') . '%')
                    ->orWhere('receipt_number', 'like', addcslashes($term, '%_\\') . '%'));
            });
    }

    /**
     * Failed and pending counts, in all and per provider (driver).
     *
     * @return array{failed: int, pending: int, by_driver: list<array{driver: string, failed: int, pending: int, last_error: string|null}>}
     */
    public function summary(): array
    {
        $rows = FiscalReceipt::query()->toBase()
            ->whereIn('status', [FiscalReceiptStatus::FAILED->value, FiscalReceiptStatus::PENDING->value])
            ->selectRaw('driver, status, COUNT(*) AS n')->groupBy('driver', 'status')->get();

        $byDriver = [];
        foreach ($rows as $row) {
            $driver = (string) $row->driver;
            $byDriver[$driver] ??= ['driver' => $driver, 'failed' => 0, 'pending' => 0, 'last_error' => null];
            $byDriver[$driver][(string) $row->status] = (int) $row->n;
        }
        foreach ($byDriver as $driver => $row) {
            $error = FiscalReceipt::query()->where('driver', $driver)
                ->where('status', FiscalReceiptStatus::FAILED->value)->whereNotNull('error')->orderByDesc('id')->value('error');
            $byDriver[$driver]['last_error'] = $error !== null ? mb_substr((string) $error, 0, 200) : null;
        }
        $byDriver = array_values($byDriver);
        usort($byDriver, fn (array $a, array $b) => [$b['failed'], $b['pending'], $a['driver']] <=> [$a['failed'], $a['pending'], $b['driver']]);

        return [
            'failed' => (int) array_sum(array_column($byDriver, 'failed')),
            'pending' => (int) array_sum(array_column($byDriver, 'pending')),
            'by_driver' => $byDriver,
        ];
    }

    /**
     * @param  Builder<FiscalReceipt>  $query
     * @return Builder<FiscalReceipt>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'branch:id,name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function row(FiscalReceipt $receipt): array
    {
        return [
            'uuid' => $receipt->uuid,
            'type' => $receipt->type->value,
            'status' => $receipt->status->value,
            'driver' => $receipt->driver,
            'provider' => $receipt->provider,
            'non_fiscal' => (bool) $receipt->non_fiscal,
            'total' => Money::of($receipt->total)->toString(),
            'error' => $receipt->error !== null ? mb_substr((string) $receipt->error, 0, 300) : null,
            'attempts' => (int) $receipt->attempts,
            'next_attempt_at' => $receipt->next_attempt_at?->toIso8601String(),
            'created_at' => (string) $receipt->created_at?->toIso8601String(),
            'issued_at' => $receipt->issued_at?->toIso8601String(),
            'store' => $receipt->store ? ['id' => (int) $receipt->store->id, 'name' => (string) $receipt->store->name] : null,
            'branch' => $receipt->branch ? ['id' => (int) $receipt->branch->id, 'name' => (string) $receipt->branch->name] : null,
            'retryable' => $this->retryable($receipt),
            'subject_type' => $receipt->subject_type,
            'subject_id' => $receipt->subject_id !== null ? (int) $receipt->subject_id : null,
        ];
    }

    /**
     * The receipt card: the row, the lines and payments as they were sent and the receipt this one reverses. The
     * provider's raw answer stays out.
     *
     * @return array<string, mixed>
     */
    public function detail(FiscalReceipt $receipt): array
    {
        $receipt->loadMissing(['originalReceipt:id,uuid', 'store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'branch:id,name']);

        return [
            ...$this->row($receipt),
            'full_error' => $receipt->error,
            'payment_form' => $receipt->payment_form->value,
            'tax_regime' => $receipt->tax_regime,
            'vat_total' => Money::of($receipt->vat_total)->toString(),
            'fiscal_sign' => $receipt->fiscal_sign,
            'receipt_number' => $receipt->receipt_number,
            'terminal_id' => $receipt->terminal_id,
            'original_receipt_uuid' => $receipt->originalReceipt?->uuid,
            'sold_at' => $receipt->sold_at?->toIso8601String(),
            'lines' => $receipt->lines ?? [],
            'payments' => FiscalSnapshot::payments(array_map(
                fn (array $row) => FiscalSnapshot::payment($row),
                array_values(array_filter((array) ($receipt->payments ?? []), 'is_array')),
            )),
        ];
    }

    public function retryable(FiscalReceipt $receipt): bool
    {
        return $receipt->status !== FiscalReceiptStatus::ISSUED && $receipt->driver !== 'external';
    }
}
