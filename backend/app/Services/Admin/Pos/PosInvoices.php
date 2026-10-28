<?php

namespace App\Services\Admin\Pos;

use App\Enums\DunningStage;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Store;
use App\Models\SubscriptionDunningEvent;
use App\Models\SubscriptionInvoice;
use App\Services\Finance\Billing\BillingPresenter;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P4-24 Hisob-fakturalar: subscription invoices across stores for the Boshqaruv markazi. Reads only; marking paid and
 * voiding go through SubscriptionService (the controller). Overdue = still unpaid after the due date (Asia/Tashkent).
 */
class PosInvoices
{
    public const ROW = 'array{uuid: string, number: string, kind: string, period: string, store: array{id: int, name: string}|null, plan: array{code: string, name: string}|null, amount: string, paid_amount: string, outstanding: string, status: string, status_label: string, due_date: string, issued_at: string, paid_at: string|null, voided_at: string|null, overdue: bool, overdue_days: int, dunning_stage: string|null, dunning_label: string|null, dunning_at: string|null}';

    public function __construct(
        private readonly BillingPresenter $presenter,
    ) {}

    public function today(): string
    {
        return Carbon::now((string) config('billing.timezone'))->toDateString();
    }

    /**
     * @param  array<string, mixed>  $filters  status (issued|paid|void|overdue), store_id, period (YYYY-MM), dunning (a DunningStage), q (number)
     * @return Builder<SubscriptionInvoice>
     */
    public function query(array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $query = SubscriptionInvoice::query()
            ->when($status === 'overdue', fn (Builder $q) => $this->overdue($q))
            ->when($status !== null && $status !== 'overdue', fn (Builder $q) => $q->where('status', $status))
            ->when(filled($filters['store_id'] ?? null), fn (Builder $q) => $q->where('store_id', (int) $filters['store_id']))
            ->when(filled($filters['period'] ?? null), fn (Builder $q) => $q->where('period', $filters['period']))
            ->when(filled($filters['q'] ?? null), fn (Builder $q) => $q->where('number', 'like', '%' . addcslashes(trim((string) $filters['q']), '%_\\') . '%'));

        if (filled($filters['dunning'] ?? null)) {
            // The latest step of the invoice's reminders is the one filtered on
            $stage = (string) $filters['dunning'];
            $query->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('subscription_dunning_events as d')
                ->whereColumn('d.subscription_invoice_id', 'subscription_invoices.id')
                ->where('d.stage', $stage)
                ->whereRaw('d.id = (select max(x.id) from subscription_dunning_events x where x.subscription_invoice_id = subscription_invoices.id)'));
        }

        return $query;
    }

    /**
     * @param  Builder<SubscriptionInvoice>  $query
     * @return Builder<SubscriptionInvoice>
     */
    public function overdue(Builder $query): Builder
    {
        return $query->where('status', SubscriptionInvoiceStatus::ISSUED->value)->where('due_date', '<', $this->today());
    }

    /**
     * @return array{issued: int, overdue: int, paid: int, void: int, outstanding: string}
     */
    public function counts(): array
    {
        $byStatus = SubscriptionInvoice::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        $outstanding = SubscriptionInvoice::query()->where('status', SubscriptionInvoiceStatus::ISSUED->value)
            ->selectRaw('COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0) AS total')->value('total');

        return [
            'issued' => (int) ($byStatus[SubscriptionInvoiceStatus::ISSUED->value] ?? 0),
            'overdue' => $this->overdue(SubscriptionInvoice::query())->count(),
            'paid' => (int) ($byStatus[SubscriptionInvoiceStatus::PAID->value] ?? 0),
            'void' => (int) ($byStatus[SubscriptionInvoiceStatus::VOID->value] ?? 0),
            'outstanding' => Money::of($outstanding ?? 0)->toString(),
        ];
    }

    /**
     * @param  iterable<SubscriptionInvoice>  $invoices
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $invoices): array
    {
        $invoices = collect($invoices);
        $latest = $this->latestDunning($invoices->pluck('id')->all());
        $stores = Store::withTrashed()->whereIn('id', $invoices->pluck('store_id')->unique()->all())->pluck('name', 'id');
        $invoices->each(fn (SubscriptionInvoice $invoice) => $invoice->loadMissing('plan:id,code,name'));

        return $invoices->map(fn (SubscriptionInvoice $invoice) => $this->row($invoice, $stores[$invoice->store_id] ?? null, $latest[$invoice->id] ?? null))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(SubscriptionInvoice $invoice, ?string $storeName, ?SubscriptionDunningEvent $dunning): array
    {
        $overdueDays = $this->overdueDays($invoice);

        return [
            'uuid' => $invoice->uuid,
            'number' => $invoice->number,
            'kind' => $invoice->kind->value,
            'period' => $invoice->period,
            'store' => $storeName !== null ? ['id' => (int) $invoice->store_id, 'name' => $storeName] : null,
            'plan' => $invoice->plan ? ['code' => $invoice->plan->code, 'name' => $invoice->plan->name] : null,
            'amount' => $invoice->total()->toString(),
            'paid_amount' => Money::of($invoice->paid_amount)->toString(),
            'outstanding' => $invoice->outstanding()->toString(),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'due_date' => $invoice->due_date->toDateString(),
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'voided_at' => $invoice->voided_at?->toIso8601String(),
            'overdue' => $overdueDays > 0,
            'overdue_days' => $overdueDays,
            'dunning_stage' => $dunning?->stage->value,
            'dunning_label' => $dunning?->stage->label(),
            'dunning_at' => $dunning?->occurred_at->toIso8601String(),
        ];
    }

    /**
     * The invoice card: the row, the invoice as A1's presenter shows it (lines and every dunning step), the store's
     * debt and the void reason.
     *
     * @return array<string, mixed>
     */
    public function detail(SubscriptionInvoice $invoice): array
    {
        $store = Store::withTrashed()->find($invoice->store_id);
        $latest = SubscriptionDunningEvent::where('subscription_invoice_id', $invoice->id)->orderByDesc('id')->first();
        $invoice->loadMissing('plan:id,code,name');

        return [
            'invoice' => [...$this->row($invoice, $store?->name, $latest), ...$this->presenter->invoice($invoice), 'void_reason' => $invoice->void_reason],
            'store' => $store === null ? null : [
                'id' => (int) $store->id,
                'name' => (string) $store->name,
                'status' => $store->status->value,
                'debt_balance' => Money::of($store->debt_balance)->toString(),
                'subscription_debt' => Money::of($store->subscription_debt)->toString(),
                'commission_debt' => $store->commissionDebt()->toString(),
            ],
        ];
    }

    private function overdueDays(SubscriptionInvoice $invoice): int
    {
        if ($invoice->status !== SubscriptionInvoiceStatus::ISSUED) {
            return 0;
        }
        $due = Carbon::parse($invoice->due_date->toDateString(), (string) config('billing.timezone'))->startOfDay();
        $today = Carbon::parse($this->today(), (string) config('billing.timezone'))->startOfDay();

        return $today->greaterThan($due) ? (int) $due->diffInDays($today) : 0;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, SubscriptionDunningEvent>
     */
    private function latestDunning(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $latest = [];
        foreach (SubscriptionDunningEvent::whereIn('subscription_invoice_id', $ids)->orderBy('id')->get() as $event) {
            $latest[$event->subscription_invoice_id] = $event;
        }

        return $latest;
    }

    /**
     * @return list<string>
     */
    public static function dunningStages(): array
    {
        return array_map(fn (DunningStage $stage) => $stage->value, DunningStage::cases());
    }
}
