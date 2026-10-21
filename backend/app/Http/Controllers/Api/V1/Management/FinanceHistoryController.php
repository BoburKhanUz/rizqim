<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Enums\Permission;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Store;
use App\Services\Order\SalesReportService;
use App\Support\Money;
use App\Support\PayoutView;
use App\Support\UzValidation;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * P2-12: the store's money history for the partner web panel, read only (finance.view; the owner by default, an owner
 * may grant it to managers): the debt ledger and the wallet (what the store moneys page shows as two tables), payout
 * requests and customers' online payments, paginated, newest first. Writes stay where they are (POST /owner/payouts,
 * POST /owner/finance/top-up). Money is a string with two decimals; dates filter in the store's timezone.
 */
#[Group('Owner: finance history', "Qarz hisobi, hamyon, pul yechish so'rovlari va onlayn to'lovlar tarixi (P2-12).")]
class FinanceHistoryController extends ManagementController
{
    private const PAGE = 'current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null';

    private const TRANSACTION = 'array{id: int, type: string, amount: string, balance_before: string, balance_after: string, order: array{uuid: string, short_code: string|null}|null, reference_id: string|null, notes: string|null, created_at: string}';

    /**
     * Debt ledger
     *
     * The store's debt balance movements (store_transactions): commission (debt up), top-up (debt down),
     * subscription invoices and their voids, refunds and adjustments. A positive `amount` raises the debt.
     */
    #[QueryParameter('type', description: 'commission | topup | refund | adjustment | subscription | settlement | payout.', type: 'string', required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('per_page', description: '1–100, standart 25.', type: 'int', required: false)]
    #[QueryParameter('page', description: 'Sahifa raqami, 1 dan (Laravel sahifalari).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::TRANSACTION . '>, ' . self::PAGE . '}}')]
    public function ledger(Request $request): JsonResponse
    {
        return $this->transactions($request, 'store_transactions');
    }

    /**
     * Wallet
     *
     * The store's wallet movements (store_wallet_transactions): customers' online payments settled to the store
     * (wallet up) and payouts (wallet down).
     */
    #[QueryParameter('type', description: 'settlement | payout | ...', type: 'string', required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('per_page', description: '1–100, standart 25.', type: 'int', required: false)]
    #[QueryParameter('page', description: 'Sahifa raqami, 1 dan (Laravel sahifalari).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::TRANSACTION . '>, ' . self::PAGE . '}}')]
    public function wallet(Request $request): JsonResponse
    {
        return $this->transactions($request, 'store_wallet_transactions');
    }

    /**
     * Payout requests
     *
     * The store's requests to withdraw from the wallet and how the platform handled them. The card number is masked.
     */
    #[QueryParameter('status', description: 'pending | completed | rejected.', type: "'pending'|'completed'|'rejected'", required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('per_page', description: '1–100, standart 25.', type: 'int', required: false)]
    #[QueryParameter('page', description: 'Sahifa raqami, 1 dan (Laravel sahifalari).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PayoutView::SHAPE . '>, ' . self::PAGE . '}}')]
    public function payouts(Request $request): JsonResponse
    {
        $this->validateFilters($request, ['status' => 'nullable|string|in:pending,completed,rejected']);
        $store = $this->currentStore($request);

        $query = DB::table('payout_requests')
            ->where('store_id', $store->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')));
        $this->dateRange($query, 'payout_requests.created_at', $request, $store);

        $page = $query->orderByDesc('id')->paginate($this->perPage($request));
        // P2-30: the same masked shape as GET /owner/finance and POST /owner/payouts (PayoutView)
        $page->getCollection()->transform(fn (object $row): array => PayoutView::present($row));

        return $this->success($page);
    }

    /**
     * Online payments
     *
     * Customers' payments for the store's orders through Click, Payme… (the payments table), with the provider's
     * transaction id for reconciliation. A branch-bound caller sees their branch's orders only. Raw provider payloads
     * are never returned. `fiscal` (P3-14): the fiscal receipt Payme/Click registers for the payment — `status`
     * blocked | pending | failed | recorded and problem codes (`mxik_missing`, `store_tin_missing`...); null when
     * fiscal receipts are off. `fiscal_status=blocked` lists the payments that need the owner's attention.
     */
    #[QueryParameter('status', description: 'pending | paid | failed | cancelled | expired | refunded.', type: 'string', required: false)]
    #[QueryParameter('provider', description: 'click | payme | ...', type: 'string', required: false)]
    #[QueryParameter('fiscal_status', description: 'P3-14: blocked | pending | failed | recorded (fiskal chek holati).', type: 'string', required: false)]
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('per_page', description: '1–100, standart 25.', type: 'int', required: false)]
    #[QueryParameter('page', description: 'Sahifa raqami, 1 dan (Laravel sahifalari).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array{uuid: string, provider: string, provider_transaction_id: string|null, status: string, amount: string, paid_at: string|null, cancelled_at: string|null, created_at: string, order: array{uuid: string, short_code: string|null, branch_id: int|null, total_price: string}, fiscal: array{status: string, problems: list<string>}|null}>, ' . self::PAGE . '}}')]
    public function payments(Request $request): JsonResponse
    {
        $this->validateFilters($request, [
            'status' => ['nullable', 'string', Rule::in(array_map(fn (PaymentStatus $status) => $status->value, PaymentStatus::cases()))],
            'provider' => 'nullable|string|max:32',
            'branch_id' => UzValidation::rowId(),
            // P3-14: online fiscal receipt state (App\Services\Payment\Fiscal\OnlineFiscalRecorder)
            'fiscal_status' => ['nullable', 'string', Rule::in(\App\Services\Payment\Fiscal\OnlineFiscalRecorder::STATUSES)],
        ]);
        $store = $this->currentStore($request);
        $branch = $this->branchFilter($request, Permission::FINANCE_VIEW);

        $query = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.store_id', $store->id)
            ->when($branch !== null, fn (Builder $query) => $query->where('orders.branch_id', $branch?->id))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('payments.status', $request->input('status')))
            ->when($request->filled('provider'), fn (Builder $query) => $query->where('payments.provider', $request->input('provider')))
            ->when($request->filled('fiscal_status'), fn (Builder $query) => $query->whereRaw("payments.payload->'fiscal'->>'status' = ?", [$request->input('fiscal_status')]))
            ->select([
                'payments.id', 'payments.uuid', 'payments.provider', 'payments.provider_transaction_id', 'payments.status', 'payments.amount',
                'payments.paid_at', 'payments.cancelled_at', 'payments.created_at',
                'orders.uuid as order_uuid', 'orders.short_code as order_short_code', 'orders.branch_id as order_branch_id', 'orders.total_price as order_total',
                // P3-14: only the fiscal state, never the raw payload
                DB::raw("payments.payload->'fiscal'->>'status' as fiscal_status"),
                DB::raw("payments.payload->'fiscal'->'problems' as fiscal_problems"),
            ]);
        $this->dateRange($query, 'payments.created_at', $request, $store);

        $page = $query->orderByDesc('payments.id')->paginate($this->perPage($request));
        $page->getCollection()->transform(fn (object $row): array => $this->presentPayment($row));

        return $this->success($page);
    }

    private function transactions(Request $request, string $table): JsonResponse
    {
        $this->validateFilters($request, [
            'type' => ['nullable', 'string', Rule::in(array_map(fn (StoreTransactionType $type) => $type->value, StoreTransactionType::cases()))],
        ]);
        $store = $this->currentStore($request);

        $query = DB::table($table)
            ->leftJoin('orders', 'orders.id', '=', "{$table}.order_id")
            ->where("{$table}.store_id", $store->id)
            ->when($request->filled('type'), fn (Builder $query) => $query->where("{$table}.type", $request->input('type')))
            ->select(["{$table}.*", 'orders.uuid as order_uuid', 'orders.short_code as order_short_code']);
        $this->dateRange($query, "{$table}.created_at", $request, $store);

        $page = $query->orderByDesc("{$table}.id")->paginate($this->perPage($request));
        $page->getCollection()->transform(fn (object $row): array => $this->presentTransaction($row));

        return $this->success($page);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPayment(object $row): array
    {
        return [
            'uuid' => (string) $row->uuid,
            'provider' => (string) $row->provider,
            'provider_transaction_id' => $row->provider_transaction_id,
            'status' => (string) $row->status,
            'amount' => Money::of($row->amount)->toString(),
            'paid_at' => $this->iso($row->paid_at),
            'cancelled_at' => $this->iso($row->cancelled_at),
            'created_at' => (string) $this->iso($row->created_at),
            'order' => [
                'uuid' => (string) $row->order_uuid,
                'short_code' => $row->order_short_code,
                'branch_id' => $row->order_branch_id !== null ? (int) $row->order_branch_id : null,
                'total_price' => Money::of($row->order_total)->toString(),
            ],
            // P3-14: the online fiscal receipt's state; problem codes only (never names or provider data)
            'fiscal' => $row->fiscal_status !== null ? [
                'status' => (string) $row->fiscal_status,
                'problems' => array_values(array_filter((array) json_decode((string) $row->fiscal_problems, true), 'is_string')),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTransaction(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'type' => (string) $row->type,
            'amount' => Money::of($row->amount)->toString(),
            'balance_before' => Money::of($row->balance_before)->toString(),
            'balance_after' => Money::of($row->balance_after)->toString(),
            'order' => $row->order_uuid !== null ? ['uuid' => (string) $row->order_uuid, 'short_code' => $row->order_short_code] : null,
            'reference_id' => $row->reference_id,
            // Payout rows note the card they went to ("Payout completed to card: ...")
            'notes' => PayoutView::maskCardsIn($row->notes),
            'created_at' => (string) $this->iso($row->created_at),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function validateFilters(Request $request, array $extra): void
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'per_page' => 'nullable|integer|between:1,100',
            'page' => 'nullable|integer|between:1,100000',
        ] + $extra, UzValidation::messages([
            'from.date_format' => "Boshlanish sanasi noto'g'ri (YYYY-MM-DD).",
            'to.date_format' => "Tugash sanasi noto'g'ri (YYYY-MM-DD).",
            'to.after_or_equal' => "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
        ]), ['type' => 'Turi', 'status' => 'Holati', 'provider' => "To'lov tizimi", 'branch_id' => 'Filial', 'per_page' => 'Sahifadagi soni', 'page' => 'Sahifa']);
    }

    /**
     * `from` / `to` are whole days of the store's timezone; the columns hold UTC.
     */
    private function dateRange(Builder $query, string $column, Request $request, Store $store): void
    {
        $timezone = app(SalesReportService::class)->timezone($store);
        if ($request->filled('from')) {
            $query->where($column, '>=', CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->input('from'), $timezone)->utc()->format('Y-m-d H:i:s'));
        }
        if ($request->filled('to')) {
            $query->where($column, '<', CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->input('to'), $timezone)->addDay()->utc()->format('Y-m-d H:i:s'));
        }
    }

    private function perPage(Request $request): int
    {
        return $request->integer('per_page', 25);
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value, 'UTC')->toIso8601String();
    }
}
