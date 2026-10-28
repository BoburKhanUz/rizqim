<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\StoreTransactionType;
use App\Http\Requests\Admin\AdminRules;
use App\Models\FinanceAdjustment;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Admin\AdminAudit;
use App\Services\Finance\Payouts\PayoutService;
use App\Services\Finance\SettlementRecoveryService;
use App\Support\Money;
use App\Support\PayoutView;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * P4-23: Moliya — the summary behind the tabs and "Bugun", balance top-ups (Click, Payme), settlement failures with a
 * retry (SettlementRecoveryService, the same path RetryFailedSettlementsJob takes), store balances and debts (the
 * over-credit-limit list) and a store's ledger. Reads: admin.finance.view; the retry admin.finance.manage + step-up +
 * `reason` (audited as admin.finance.settlement_failures.retried).
 */
#[Group('Admin')]
class AdminFinanceController extends AdminController
{
    private const TOPUP = 'array{id: int, uuid: string, store: array{id: int, name: string}|null, amount: string, provider: string, status: string, status_label: string, provider_transaction_id: string|null, stuck: bool, transaction_id: int|null, created_at: string|null, paid_at: string|null}';

    private const FAILURE = 'array{uuid: string, short_code: string|null, store: array{id: int, name: string}|null, total: string, commission: string, payment_method: string|null, payment_owner: string|null, failed_at: string|null, attempts: int, max_attempts: int, gave_up: bool, error: string|null}';

    private const BALANCE = 'array{store: array{id: int, name: string, status: string, status_label: string}, wallet_balance: string, debt_balance: string, subscription_debt: string, commission_debt: string, credit_limit: string, over_limit: bool, is_order_locked: bool, pending_payouts: string}';

    public function __construct(
        private readonly SettlementRecoveryService $settlements,
    ) {}

    /**
     * Moliya summary
     *
     * The counts behind the tabs and the second-approval queue: open payouts (`pending` — no approval yet,
     * `awaiting_second`), adjustments waiting for a second approval, settlement failures (and how many gave up), stuck
     * top-ups, stores over their credit limit, the store totals; `threshold` — from this amount two members approve.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{payouts: array{pending: int, awaiting_second: int, open_amount: string}, adjustments: array{pending: int}, settlement_failures: array{count: int, gave_up: int}, topups: array{stuck: int}, balances: array{over_limit: int, wallet_total: string, debt_total: string, subscription_debt_total: string}, threshold: string}}')]
    public function summary(): JsonResponse
    {
        $pending = PayoutRequest::query()->where('status', 'pending');
        $totals = $this->totals();

        return $this->success([
            'payouts' => [
                'pending' => (clone $pending)->whereNull('first_approved_by')->count(),
                'awaiting_second' => (clone $pending)->whereNotNull('first_approved_by')->count(),
                'open_amount' => Money::of((string) (clone $pending)->sum('amount'))->toString(),
            ],
            'adjustments' => ['pending' => FinanceAdjustment::where('status', FinanceAdjustment::PENDING)->count()],
            'settlement_failures' => [
                'count' => Order::whereNotNull('settlement_failed_at')->count(),
                'gave_up' => Order::whereNotNull('settlement_failed_at')->where('settlement_attempts', '>=', $this->settlements->maxAttempts())->count(),
            ],
            'topups' => ['stuck' => $this->stuckTopups(StoreTopupRequest::query())->count()],
            'balances' => [
                'over_limit' => $totals['over_limit'],
                'wallet_total' => $totals['wallet_total'],
                'debt_total' => $totals['debt_total'],
                'subscription_debt_total' => $totals['subscription_debt_total'],
            ],
            'threshold' => PayoutService::threshold()->toString(),
        ]);
    }

    /**
     * Balance top-ups
     *
     * Owners' Click and Payme top-ups of their debt: `status` (pending, paid, cancelled, all — the default),
     * `provider` (click, payme), `store_id`, `stuck=1` — pending for more than 30 minutes in the last 3 days (the owner
     * left the payment page, or the provider's callback never came: check the provider's cabinet). Newest first,
     * cursor pages. `transaction_id` — the debt ledger row a paid one wrote.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::TOPUP . '>, next_cursor: string|null, per_page: int, counts: array{pending: int, stuck: int}}}')]
    public function topups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'paid', 'cancelled', 'all'])],
            'provider' => ['nullable', Rule::in(['click', 'payme'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'stuck' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $status = $data['status'] ?? 'all';

        $query = StoreTopupRequest::query()
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when(isset($data['provider']), fn (Builder $query) => $query->where('provider', $data['provider']))
            ->when(isset($data['store_id']), fn (Builder $query) => $query->where('store_id', (int) $data['store_id']));
        if (filter_var($data['stuck'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query = $this->stuckTopups($query);
        }

        $page = $query->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();
        $items = collect($page->items());
        $transactions = StoreTransaction::where('type', StoreTransactionType::TOPUP->value)
            ->whereIn('reference_id', $items->pluck('uuid')->map(fn ($uuid) => (string) $uuid)->all())
            ->pluck('id', 'reference_id');
        $stuckBefore = now()->subMinutes((int) config('finance.topup_stuck_minutes', 30));

        return $this->success([
            'data' => $items->map(fn (StoreTopupRequest $topup) => [
                'id' => (int) $topup->id,
                'uuid' => (string) $topup->uuid,
                'store' => $topup->store ? ['id' => (int) $topup->store->id, 'name' => (string) $topup->store->name] : null,
                'amount' => Money::of((string) $topup->getRawOriginal('amount'))->toString(),
                'provider' => (string) $topup->provider,
                'status' => (string) $topup->status,
                'status_label' => match ((string) $topup->status) {
                    'pending' => "To'lov kutilmoqda",
                    'paid' => "To'langan",
                    'cancelled' => 'Bekor qilingan',
                    default => (string) $topup->status,
                },
                'provider_transaction_id' => $topup->provider_transaction_id,
                'stuck' => $topup->status === 'pending' && $topup->created_at !== null && $topup->created_at->lessThan($stuckBefore),
                'transaction_id' => isset($transactions[(string) $topup->uuid]) ? (int) $transactions[(string) $topup->uuid] : null,
                'created_at' => $topup->created_at?->toIso8601String(),
                'paid_at' => $topup->paid_at?->toIso8601String(),
            ])->values()->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'counts' => [
                'pending' => StoreTopupRequest::where('status', 'pending')->count(),
                'stuck' => $this->stuckTopups(StoreTopupRequest::query())->count(),
            ],
        ]);
    }

    /**
     * Settlement failures
     *
     * Delivered orders whose ledger booking (commission, the store's money, the referral reward) failed and is retried
     * every 10 minutes (RetryFailedSettlementsJob) until `max_attempts`; `gave_up` — the automatic retries stopped.
     * Oldest first.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::FAILURE . '>, count: int, max_attempts: int}}')]
    public function settlementFailures(): JsonResponse
    {
        $orders = Order::query()->whereNotNull('settlement_failed_at')
            ->with(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])
            ->orderBy('settlement_failed_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return $this->success([
            'data' => $orders->map(fn (Order $order) => $this->failure($order))->values()->all(),
            'count' => Order::whereNotNull('settlement_failed_at')->count(),
            'max_attempts' => $this->settlements->maxAttempts(),
        ]);
    }

    /**
     * Retry a failed settlement
     *
     * Books the order again now through the same path as the automatic retries (also after they gave up). `reason`
     * required. `booked` — whether it is booked now; a failure stays on the list with its new error (its attempts
     * go up). An order booked or refunded meanwhile is cleared (`booked: false`, `order: null`). 404 for an order
     * without a failed booking.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{booked: bool, order: ' . self::FAILURE . '|null}}')]
    public function retrySettlement(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $order = Order::where('uuid', $uuid)->whereNotNull('settlement_failed_at')->first();
        if ($order === null) {
            return $this->refuse("Bu buyurtmaning hisob-kitobida xato yo'q.", 'not_found', 404);
        }

        $attemptsBefore = (int) $order->settlement_attempts;
        $booked = $this->settlements->retry((int) $order->id);
        $order->refresh();
        $still = $order->settlement_failed_at !== null;

        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)
            ->changes(['settlement_failed' => true, 'settlement_attempts' => $attemptsBefore], ['settlement_failed' => $still, 'settlement_attempts' => (int) $order->settlement_attempts])
            ->meta(['booked' => $booked, 'short_code' => $order->short_code]);

        return $this->success(
            ['booked' => $booked, 'order' => $still ? $this->failure($order->load(['store' => fn ($store) => $store->withTrashed()->select(['id', 'name'])])) : null],
            $booked ? 'Hisob-kitob yozildi.' : ($still ? 'Yana yozilmadi: ' . $order->getAttribute('settlement_error') : "Buyurtma endi yetkazilgan holatda emas: hisob-kitob kerak emas."),
        );
    }

    /**
     * Store balances and debts
     *
     * `filter`: all (the default), debt (owes anything), over_limit (the commission debt is over the credit limit —
     * Mini App orders are refused), subscription_debt (unpaid invoices), wallet (RIZQIM holds money for it); `q` — the
     * store's name. Largest first (debt; the wallet for `wallet`), pages of `per_page` (1–100, default 50). `totals`
     * — over all stores. Amounts are decimal strings; the commission debt is debt − subscription debt.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::BALANCE . '>, page: int, per_page: int, total: int, totals: array{over_limit: int, wallet_total: string, debt_total: string, subscription_debt_total: string, commission_debt_total: string}}}')]
    public function balances(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'debt', 'over_limit', 'subscription_debt', 'wallet'])],
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:100',
        ], UzValidation::messages());
        $filter = $data['filter'] ?? 'all';
        $perPage = (int) ($data['per_page'] ?? 50);

        $query = Store::query()
            ->when($filter === 'debt', fn (Builder $query) => $query->where('debt_balance', '>', 0))
            ->when($filter === 'over_limit', fn (Builder $query) => $query->whereRaw(self::OVER_LIMIT))
            ->when($filter === 'subscription_debt', fn (Builder $query) => $query->where('subscription_debt', '>', 0))
            ->when($filter === 'wallet', fn (Builder $query) => $query->where('wallet_balance', '>', 0))
            ->when(isset($data['q']) && trim((string) $data['q']) !== '', fn (Builder $query) => $query->where('name', 'ilike', '%' . addcslashes(trim((string) $data['q']), '%_\\') . '%'));

        $page = $query->orderByDesc($filter === 'wallet' ? 'wallet_balance' : 'debt_balance')->orderBy('id')
            ->paginate($perPage, ['*'], 'page', (int) ($data['page'] ?? 1));
        $stores = collect($page->items());
        $pending = PayoutRequest::where('status', 'pending')->whereIn('store_id', $stores->pluck('id'))
            ->groupBy('store_id')->selectRaw('store_id, SUM(amount) AS total')->toBase()->get()->keyBy('store_id');

        return $this->success([
            'data' => $stores->map(fn (Store $store) => $this->balanceRow($store, Money::of((string) ($pending->get($store->id)->total ?? '0'))))->values()->all(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'totals' => $this->totals(),
        ]);
    }

    /**
     * A store's ledger
     *
     * `ledger`: debt (the debt ledger: commission, top-ups, netting, subscription invoices, refunds, adjustments; the
     * default) or wallet (settlements, payouts, refunds, adjustments). Newest first, cursor pages (`per_page` 1–100,
     * default 50). Card numbers in notes are masked. A netting row names what it closed (`subscription_invoice`),
     * a payout's rows carry `payout_request_id`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{store: array{id: int, name: string}, data: list<array{id: int, type: string, amount: string, balance_before: string, balance_after: string, notes: string|null, order: array{uuid: string, short_code: string|null}|null, subscription_invoice: array{id: int, number: string, period: string}|null, payout_request_id: int|null, reference_id: string|null, performed_by: array{id: int, name: string}|null, created_at: string|null}>, next_cursor: string|null, per_page: int}}')]
    public function ledger(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'ledger' => ['nullable', Rule::in(['debt', 'wallet'])],
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $store = Store::withTrashed()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $wallet = ($data['ledger'] ?? 'debt') === 'wallet';
        $model = $wallet ? StoreWalletTransaction::class : StoreTransaction::class;
        $page = $model::query()->where('store_id', $store->id)
            ->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 50))
            ->withQueryString();
        $rows = collect($page->items());

        $orders = Order::withTrashed()->whereIn('id', $rows->pluck('order_id')->filter()->unique()->all())->get(['id', 'uuid', 'short_code'])->keyBy('id');
        $invoices = $wallet ? collect() : \App\Models\SubscriptionInvoice::whereIn('id', $rows->pluck('subscription_invoice_id')->filter()->unique()->all())->get(['id', 'number', 'period'])->keyBy('id');
        $people = \App\Models\User::whereIn('id', $rows->pluck('performed_by')->filter()->unique()->all())->pluck('name', 'id');

        return $this->success([
            'store' => ['id' => (int) $store->id, 'name' => (string) $store->name],
            'data' => $rows->map(function (StoreTransaction|StoreWalletTransaction $row) use ($orders, $invoices, $people) {
                $order = $row->order_id !== null ? $orders->get($row->order_id) : null;
                $invoiceId = $row->getAttribute('subscription_invoice_id');
                $invoice = $invoiceId !== null ? $invoices->get($invoiceId) : null;

                return [
                    'id' => (int) $row->id,
                    'type' => $row->type instanceof \BackedEnum ? (string) $row->type->value : (string) $row->type,
                    'amount' => Money::of((string) $row->getRawOriginal('amount'))->toString(),
                    'balance_before' => Money::of((string) $row->getRawOriginal('balance_before'))->toString(),
                    'balance_after' => Money::of((string) $row->getRawOriginal('balance_after'))->toString(),
                    'notes' => PayoutView::maskCardsIn($row->notes),
                    'order' => $order ? ['uuid' => (string) $order->uuid, 'short_code' => $order->short_code] : null,
                    'subscription_invoice' => $invoice ? ['id' => (int) $invoice->id, 'number' => (string) $invoice->number, 'period' => (string) $invoice->period] : null,
                    'payout_request_id' => $row->getAttribute('payout_request_id') !== null ? (int) $row->getAttribute('payout_request_id') : null,
                    'reference_id' => $row->reference_id,
                    'performed_by' => $row->performed_by !== null && isset($people[$row->performed_by]) ? ['id' => (int) $row->performed_by, 'name' => (string) $people[$row->performed_by]] : null,
                    'created_at' => $row->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function balanceRow(Store $store, Money $pendingPayouts): array
    {
        return [
            'store' => [
                'id' => (int) $store->id,
                'name' => (string) $store->name,
                'status' => $store->status->value,
                'status_label' => $store->status->getLabel(),
            ],
            'wallet_balance' => Money::of($store->wallet_balance)->toString(),
            'debt_balance' => Money::of($store->debt_balance)->toString(),
            'subscription_debt' => Money::of($store->subscription_debt ?? 0)->toString(),
            'commission_debt' => $store->commissionDebt()->toString(),
            'credit_limit' => Money::of($store->credit_limit)->toString(),
            'over_limit' => $store->exceedsCreditLimit(),
            'is_order_locked' => $store->is_order_locked,
            'pending_payouts' => $pendingPayouts->toString(),
        ];
    }

    /** The commission debt (debt − subscription debt) over abs(credit_limit): Store::exceedsCreditLimit in SQL. */
    private const OVER_LIMIT = '(debt_balance - COALESCE(subscription_debt, 0)) > ABS(COALESCE(credit_limit, 0))';

    /**
     * @return array{over_limit: int, wallet_total: string, debt_total: string, subscription_debt_total: string, commission_debt_total: string}
     */
    private function totals(): array
    {
        $row = DB::table('stores')->whereNull('deleted_at')->selectRaw(
            'COUNT(*) FILTER (WHERE ' . self::OVER_LIMIT . ') AS over_limit, '
            . 'COALESCE(SUM(wallet_balance) FILTER (WHERE wallet_balance > 0), 0) AS wallet_total, '
            . 'COALESCE(SUM(debt_balance) FILTER (WHERE debt_balance > 0), 0) AS debt_total, '
            . 'COALESCE(SUM(subscription_debt), 0) AS subscription_debt_total'
        )->first();

        $debt = Money::of((string) ($row->debt_total ?? '0'));
        $subscription = Money::of((string) ($row->subscription_debt_total ?? '0'));

        return [
            'over_limit' => (int) ($row->over_limit ?? 0),
            'wallet_total' => Money::of((string) ($row->wallet_total ?? '0'))->toString(),
            'debt_total' => $debt->toString(),
            'subscription_debt_total' => $subscription->toString(),
            'commission_debt_total' => Money::max(Money::zero(), $debt->minus($subscription))->toString(),
        ];
    }

    /**
     * @param  Builder<StoreTopupRequest>  $query
     * @return Builder<StoreTopupRequest>
     */
    private function stuckTopups(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes((int) config('finance.topup_stuck_minutes', 30)))
            ->where('created_at', '>=', now()->subHours(72));
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(Order $order): array
    {
        $max = $this->settlements->maxAttempts();

        return [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'store' => $order->store ? ['id' => (int) $order->store->id, 'name' => (string) $order->store->name] : null,
            'total' => Money::of($order->total_price)->toString(),
            'commission' => Money::of($order->commission_amount ?? 0)->toString(),
            'payment_method' => $order->payment_method,
            'payment_owner' => $order->payment_owner,
            'failed_at' => $order->settlement_failed_at?->toIso8601String(),
            'attempts' => (int) $order->settlement_attempts,
            'max_attempts' => $max,
            'gave_up' => (int) $order->settlement_attempts >= $max,
            'error' => $order->getAttribute('settlement_error'),
        ];
    }
}
