<?php

namespace App\Services\Admin\Export\Datasets;

use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Stores\StoreLabels;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * P4-31: Moliya → Balanslar va qarzlar → Excel. The list's filters: filter (all, debt, over_limit, subscription_debt,
 * wallet) and q (the store's name). The commission debt is the debt less the subscription debt; "over the limit" is the
 * commission debt over the credit limit, as in the list.
 */
final class BalancesExport extends ExportDataset
{
    /** The commission debt (debt − subscription debt) over abs(credit_limit): Store::exceedsCreditLimit in SQL (the list's) */
    private const OVER_LIMIT = '(debt_balance - COALESCE(subscription_debt, 0)) > ABS(COALESCE(credit_limit, 0))';

    /** @var array<int, string> store id => the amount of its open payout requests */
    private array $pending = [];

    public function key(): string
    {
        return 'finance_balances';
    }

    public function sheet(): string
    {
        return 'Balanslar';
    }

    public function label(): string
    {
        return 'Moliya: ovqatxonalar balansi va qarzi';
    }

    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['all', 'debt', 'over_limit', 'subscription_debt', 'wallet'])],
            'q' => 'nullable|string|max:100',
        ];
    }

    public function headers(): array
    {
        return ['ID', 'Ovqatxona', 'Holat', "Hamyon (so'm)", "Qarz, jami (so'm)", "Obuna qarzi (so'm)", "Komissiya qarzi (so'm)", "Kredit limiti (so'm)", 'Limitdan oshgan', 'Buyurtmalar yopilgan', "Kutilayotgan payoutlar (so'm)"];
    }

    public function query(array $filters, User $viewer): Builder
    {
        $filter = $filters['filter'] ?? 'all';

        return Store::query()
            ->when($filter === 'debt', fn (Builder $query) => $query->where('debt_balance', '>', 0))
            ->when($filter === 'over_limit', fn (Builder $query) => $query->whereRaw(self::OVER_LIMIT))
            ->when($filter === 'subscription_debt', fn (Builder $query) => $query->where('subscription_debt', '>', 0))
            ->when($filter === 'wallet', fn (Builder $query) => $query->where('wallet_balance', '>', 0))
            ->when(isset($filters['q']) && trim((string) $filters['q']) !== '', fn (Builder $query) => $query->where('name', 'ilike', '%' . addcslashes(trim((string) $filters['q']), '%_\\') . '%'));
    }

    public function keyColumn(): string
    {
        return 'stores.id';
    }

    public function prepare(Collection $records): void
    {
        $this->pending = PayoutRequest::query()->where('status', 'pending')->whereIn('store_id', $records->pluck('id'))
            ->groupBy('store_id')->selectRaw('store_id, SUM(amount) AS total')->toBase()->get()
            ->mapWithKeys(fn ($row) => [(int) $row->store_id => (string) $row->total])->all();
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var Store $store */
        $store = $record;

        return [
            (int) $store->id,
            $cells->text($store->name),
            StoreLabels::status($store->status),
            $cells->money(Money::of($store->wallet_balance)->toString()),
            $cells->money(Money::of($store->debt_balance)->toString()),
            $cells->money(Money::of($store->subscription_debt ?? 0)->toString()),
            $cells->money($store->commissionDebt()->toString()),
            $cells->money(Money::of($store->credit_limit)->abs()->toString()),
            $cells->yesNo($store->exceedsCreditLimit()),
            $cells->yesNo((bool) $store->is_order_locked),
            $cells->money($this->pending[(int) $store->id] ?? '0'),
        ];
    }
}
