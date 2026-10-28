<?php

namespace App\Services\Admin\Export\Datasets;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Pos\PosInvoices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * P4-31: POS va obunalar → Hisob-fakturalar → Excel. The list's filters (status — also `overdue` —, store, period,
 * dunning step, q) through the list's own query and rows (PosInvoices).
 */
final class InvoicesExport extends ExportDataset
{
    /** @var array<int, array<string, mixed>> invoice id => the list row */
    private array $rows = [];

    public function __construct(
        private readonly PosInvoices $invoices,
    ) {}

    public function key(): string
    {
        return 'pos_invoices';
    }

    public function sheet(): string
    {
        return 'Hisob-fakturalar';
    }

    public function label(): string
    {
        return 'POS: obuna hisob-fakturalari';
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([...array_map(fn (SubscriptionInvoiceStatus $status) => $status->value, SubscriptionInvoiceStatus::cases()), 'overdue'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'dunning' => ['nullable', Rule::in(PosInvoices::dunningStages())],
            'q' => 'nullable|string|min:2|max:60',
        ];
    }

    public function headers(): array
    {
        return ['Raqam', 'Turi', 'Davr', 'Ovqatxona', 'Tarif', "Summa (so'm)", "To'langan (so'm)", "Qolgan (so'm)", 'Holat', "To'lov muddati", 'Chiqarilgan', "To'langan vaqti", 'Kechikkan kunlar', 'Oxirgi eslatma', 'Eslatma vaqti'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        return $this->invoices->query($filters);
    }

    public function keyColumn(): string
    {
        return 'subscription_invoices.id';
    }

    public function with(): array
    {
        return ['plan:id,code,name'];
    }

    public function prepare(Collection $records): void
    {
        $this->rows = collect($this->invoices->rows($records->whereInstanceOf(SubscriptionInvoice::class)))->keyBy('uuid')->all();
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var SubscriptionInvoice $invoice */
        $invoice = $record;
        $row = $this->rows[$invoice->uuid] ?? [];

        return [
            $cells->text($invoice->number),
            $cells->text($row['kind'] ?? null),
            $cells->text($invoice->period),
            $cells->text($row['store']['name'] ?? null),
            $cells->text($row['plan']['name'] ?? null),
            $cells->money($row['amount'] ?? null),
            $cells->money($row['paid_amount'] ?? null),
            $cells->money($row['outstanding'] ?? null),
            $cells->text($row['status_label'] ?? null),
            $cells->text($row['due_date'] ?? null),
            $cells->time($invoice->issued_at),
            $cells->time($invoice->paid_at),
            $cells->number($row['overdue_days'] ?? 0),
            $cells->text($row['dunning_label'] ?? null),
            $cells->time(isset($row['dunning_at']) ? Carbon::parse($row['dunning_at']) : null),
        ];
    }
}
