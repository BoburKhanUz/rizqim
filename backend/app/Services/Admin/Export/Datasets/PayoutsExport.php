<?php

namespace App\Services\Admin\Export\Datasets;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Admin\Export\ExportCells;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Finance\Payouts\PayoutPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * P4-31: Moliya → Payoutlar → Excel. The queue's filters (status: open — the default —, pending, awaiting_second,
 * completed, rejected, all; store) and its rows (PayoutPresenter::row). The card is always masked here.
 */
final class PayoutsExport extends ExportDataset
{
    public function __construct(
        private readonly PayoutPresenter $presenter,
    ) {}

    public function key(): string
    {
        return 'finance_payouts';
    }

    public function sheet(): string
    {
        return 'Payoutlar';
    }

    public function label(): string
    {
        return "Moliya: payout so'rovlari";
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'pending', 'awaiting_second', 'completed', 'rejected', 'all'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
        ];
    }

    public function headers(): array
    {
        return ['ID', 'Ovqatxona', "Summa (so'm)", 'Karta', 'Holat', 'Ikkinchi tasdiq kerak', 'Birinchi tasdiqlagan', 'Birinchi tasdiq vaqti', "Ushlab qolingan qarz (so'm)", "Kartaga o'tkazilgan (so'm)", "So'ralgan", 'Qayta ishlangan'];
    }

    public function query(array $filters, User $viewer): Builder
    {
        $status = $filters['status'] ?? 'open';

        return PayoutRequest::query()
            ->when($status === 'open', fn (Builder $query) => $query->where('status', 'pending'))
            ->when($status === 'pending', fn (Builder $query) => $query->where('status', 'pending')->whereNull('first_approved_by'))
            ->when($status === 'awaiting_second', fn (Builder $query) => $query->where('status', 'pending')->whereNotNull('first_approved_by'))
            ->when(in_array($status, ['completed', 'rejected'], true), fn (Builder $query) => $query->where('status', $status))
            ->when(isset($filters['store_id']), fn (Builder $query) => $query->where('store_id', (int) $filters['store_id']));
    }

    public function keyColumn(): string
    {
        return 'payout_requests.id';
    }

    public function with(): array
    {
        return ['store' => fn ($store) => $store->withTrashed()->select(['id', 'name']), 'firstApprover:id,name'];
    }

    public function row(Model $record, ExportCells $cells): array
    {
        /** @var PayoutRequest $payout */
        $payout = $record;
        $row = $this->presenter->row($payout);

        return [
            (int) $payout->id,
            $cells->text($row['store']['name'] ?? null),
            $cells->money($row['amount']),
            $cells->text($row['card']),
            $cells->text($row['status_label']),
            $cells->yesNo($row['needs_second_approval']),
            $cells->text($row['first_approval']['by']['name'] ?? null),
            $cells->time($payout->first_approved_at),
            $cells->money($row['netted_amount']),
            $cells->money($row['transferred_amount']),
            $cells->time($payout->created_at),
            $cells->time($payout->processed_at),
        ];
    }
}
