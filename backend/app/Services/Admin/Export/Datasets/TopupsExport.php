<?php

namespace App\Services\Admin\Export\Datasets;

use App\Models\StoreTopupRequest;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * P4-31: Moliya → Balans to'ldirishlar → Excel. The list's filters: status (pending, paid, cancelled, all — the
 * default), provider (click, payme), store, stuck (pending for more than 30 minutes in the last 3 days).
 */
final class TopupsExport extends ExportDataset
{
    private const STATUS_LABELS = ['pending' => "To'lov kutilmoqda", 'paid' => "To'langan", 'cancelled' => 'Bekor qilingan'];

    public function key(): string
    {
        return 'finance_topups';
    }

    public function sheet(): string
    {
        return "Balans to'ldirishlar";
    }

    public function label(): string
    {
        return "Moliya: balans to'ldirishlar (Click, Payme)";
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'paid', 'cancelled', 'all'])],
            'provider' => ['nullable', Rule::in(['click', 'payme'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'stuck' => 'nullable|boolean',
        ];
    }

    public function headers(): array
    {
        return ['ID', 'Ovqatxona', "Summa (so'm)", 'Provayder', 'Holat', 'Provayder tranzaksiyasi', 'Qotib qolgan', 'Yaratilgan', "To'langan"];
    }

    public function query(array $filters, User $viewer): Builder
    {
        $status = $filters['status'] ?? 'all';
        $query = StoreTopupRequest::query()
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when(isset($filters['provider']), fn (Builder $query) => $query->where('provider', $filters['provider']))
            ->when(isset($filters['store_id']), fn (Builder $query) => $query->where('store_id', (int) $filters['store_id']));

        if (filter_var($filters['stuck'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes((int) config('finance.topup_stuck_minutes', 30)))
                ->where('created_at', '>=', now()->subHours(72));
        }

        return $query;
    }

    public function keyColumn(): string
    {
        return 'store_topup_requests.id';
    }

    public function with(): array
    {
        return ['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])];
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var StoreTopupRequest $topup */
        $topup = $record;
        $stuckBefore = now()->subMinutes((int) config('finance.topup_stuck_minutes', 30));
        $stuck = $topup->status === 'pending' && $topup->created_at !== null && $topup->created_at->lessThan($stuckBefore);

        return [
            (int) $topup->id,
            $cells->text($topup->store?->name),
            $cells->money(Money::of((string) $topup->getRawOriginal('amount'))->toString()),
            $cells->text($topup->provider),
            self::STATUS_LABELS[(string) $topup->status] ?? $cells->text($topup->status),
            $cells->text($topup->provider_transaction_id),
            $cells->yesNo($stuck),
            $cells->time($topup->created_at),
            $cells->time($topup->paid_at),
        ];
    }
}
