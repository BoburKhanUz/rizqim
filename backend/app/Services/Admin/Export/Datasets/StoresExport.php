<?php

namespace App\Services\Admin\Export\Datasets;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Stores\StoreDirectory;
use App\Services\Admin\Stores\StorePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * P4-31: Ovqatxonalar → Excel. The list's filters through the list's own query (StoreDirectory) and its rows
 * (StorePresenter::rows: plan, debt, health), plus the store's phone (masked unless admin.pii.view).
 */
final class StoresExport extends ExportDataset
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly StoreDirectory $directory,
        private readonly StorePresenter $presenter,
    ) {}

    public function key(): string
    {
        return 'stores';
    }

    public function sheet(): string
    {
        return 'Ovqatxonalar';
    }

    public function label(): string
    {
        return "Ovqatxonalar ro'yxati";
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|min:2|max:100',
            'status' => ['nullable', Rule::enum(StoreStatus::class)],
            'region_id' => 'nullable|integer|min:1|max:999999999999999999',
            'health' => ['nullable', Rule::in(['good', 'attention', 'churn_risk', 'none'])],
            'plan' => 'nullable|string|max:64',
            'debt' => ['nullable', Rule::in(['has_debt', 'over_limit'])],
            'next_contact' => ['nullable', Rule::in(['due'])],
        ];
    }

    public function headers(): array
    {
        return ['ID', 'Nomi', 'Holat', 'Viloyat', 'Filiallar', 'Egasi', 'Telefon', 'Tarif', 'Tarif holati', "Qarz, jami (so'm)", "Obuna qarzi (so'm)", "Komissiya qarzi (so'm)", "Kredit limiti (so'm)", 'Limitdan oshgan', "Sog'liq", "Sog'liq bahosi", 'Keyingi aloqa', 'Yaratilgan'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        return $this->directory->query($filters)->withCount('branches');
    }

    public function keyColumn(): string
    {
        return 'stores.id';
    }

    public function with(): array
    {
        return ['branches' => fn ($branches) => $branches->select(['id', 'store_id', 'region_id'])->orderBy('id'), 'branches.region:id,name_uz'];
    }

    public function prepare(Collection $records): void
    {
        $this->rows = collect($this->presenter->rows($records->whereInstanceOf(Store::class)))->keyBy('id')->all();
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var Store $store */
        $store = $record;
        $row = $this->rows[(int) $store->id] ?? [];

        return [
            (int) $store->id,
            $cells->text($store->name),
            $cells->text($row['status_label'] ?? null),
            $cells->text($row['region']['name'] ?? null),
            (int) ($row['branches_count'] ?? 0),
            $cells->text($row['owner']['name'] ?? null),
            $cells->phone($store->phone),
            $cells->text($row['plan']['name'] ?? null),
            $cells->text($row['plan']['status_label'] ?? null),
            $cells->money($row['debt_balance'] ?? null),
            $cells->money($row['subscription_debt'] ?? null),
            $cells->money($row['commission_debt'] ?? null),
            $cells->money($row['credit_limit'] ?? null),
            $cells->yesNo($row['over_limit'] ?? null),
            $cells->text($row['health']['label'] ?? null),
            $cells->number($row['health']['score'] ?? null),
            $cells->text($row['next_contact_on'] ?? null),
            $cells->time($store->created_at),
        ];
    }
}
