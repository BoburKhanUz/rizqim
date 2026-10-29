<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalService;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\FiscalReceipt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Order\SalesReportService;
use App\Support\UzValidation;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P3-11: the store's fiscal receipts for the web panel, read only (finance.view or reports.view; staff bound to a
 * branch, and a terminal PIN session, see their branch only), and "qayta yuborish" for a pending or failed one
 * (settings.manage). Money is a two-decimal string; dates filter in the store's timezone.
 */
#[Group('Owner: fiscal', "Fiskal sozlamalar (STIR, soliq rejimi, drayver, xizmatlar MXIK kodlari), fiskal cheklar ro'yxati va qayta yuborish (P3-11).")]
class FiscalReceiptController extends ManagementController
{
    private const PAGE = 'current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null';

    public const LINE = 'array{name: string, mxik_code: string, package_code: string|null, unit: string, quantity: string, unit_price: string, amount: string, vat_rate: string, vat_amount: string, no_vat: bool, commissioner_tin: string|null, marking_code: string|null}';

    public const PAYMENT = 'array{form: \'cash\'|\'card\'|\'qr\', amount: string, prepayment: bool, credit: bool, card_type: string|null, transaction_id: string|null, card_last4: string|null}';

    public const RECEIPT = 'array{uuid: string, type: \'sale\'|\'refund\'|\'advance\'|\'credit\', status: \'pending\'|\'issued\'|\'failed\', payment_form: \'cash\'|\'card\'|\'qr\'|\'mixed\', driver: string, provider: string|null, non_fiscal: bool, tax_regime: string|null, total: string, vat_total: string, fiscal_sign: string|null, receipt_number: string|null, qr_url: string|null, terminal_id: string|null, error: string|null, attempts: int, next_attempt_at: string|null, issued_at: string|null, created_at: string, branch_id: int|null, device_id: int|null, subject_type: string|null, subject_id: int|null, original_receipt_uuid: string|null, lines: list<' . self::LINE . '>, payments: list<' . self::PAYMENT . '>}';

    /**
     * Fiscal receipts
     *
     * Newest first, paginated. Filters: `status`, `type`, `payment_form`, `from`/`to` (days of the store's
     * timezone), `branch_id`, `subject_type` with `subject_id` (e.g. the receipts of one order).
     */
    #[QueryParameter('status', description: 'pending | issued | failed.', type: "'pending'|'issued'|'failed'", required: false)]
    #[QueryParameter('type', description: 'sale | refund | advance | credit.', type: "'sale'|'refund'|'advance'|'credit'", required: false)]
    #[QueryParameter('payment_form', description: 'cash | card | qr | mixed.', type: "'cash'|'card'|'qr'|'mixed'", required: false)]
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[QueryParameter('subject_type', description: 'Chek nimaga tegishli: order, order_bill, booking_payment ...', type: 'string', required: false)]
    #[QueryParameter('subject_id', description: 'subject_type bilan birga.', type: 'int', required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD (do\'kon vaqti).', type: 'string', required: false)]
    #[QueryParameter('per_page', description: '1–100, standart 25.', type: 'int', required: false)]
    #[QueryParameter('page', description: 'Sahifa raqami, 1 dan (Laravel sahifalari).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::RECEIPT . '>, ' . self::PAGE . '}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', Rule::in(FiscalReceiptStatus::values())],
            'type' => ['nullable', 'string', Rule::in(array_map(fn (FiscalReceiptType $type) => $type->value, FiscalReceiptType::cases()))],
            'payment_form' => ['nullable', 'string', Rule::in(array_map(fn (FiscalPaymentForm $form) => $form->value, FiscalPaymentForm::cases()))],
            'branch_id' => UzValidation::rowId(),
            'subject_type' => 'nullable|string|max:64|required_with:subject_id',
            'subject_id' => UzValidation::rowId(),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'per_page' => 'nullable|integer|between:1,100',
            'page' => 'nullable|integer|between:1,100000',
        ], UzValidation::messages([
            'from.date_format' => "Boshlanish sanasi noto'g'ri (YYYY-MM-DD).",
            'to.date_format' => "Tugash sanasi noto'g'ri (YYYY-MM-DD).",
            'to.after_or_equal' => "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
        ]), ['status' => 'Holati', 'type' => 'Chek turi', 'payment_form' => "To'lov shakli", 'branch_id' => 'Filial', 'subject_type' => 'Obyekt turi', 'subject_id' => 'Obyekt', 'per_page' => 'Sahifadagi soni', 'page' => 'Sahifa']);

        $store = $this->currentStore($request);
        $branch = $this->branchFilter($request, $this->readPermission($request));
        $timezone = app(SalesReportService::class)->timezone($store);

        $query = FiscalReceipt::query()
            ->with('originalReceipt:id,uuid')
            ->where('store_id', $store->id)
            ->when($branch !== null, fn (Builder $query) => $query->where('branch_id', $branch?->id))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->input('type')))
            ->when($request->filled('payment_form'), fn (Builder $query) => $query->where('payment_form', $request->input('payment_form')))
            ->when($request->filled('subject_type'), fn (Builder $query) => $query->where('subject_type', $request->input('subject_type')))
            ->when($request->filled('subject_id'), fn (Builder $query) => $query->where('subject_id', (int) $request->input('subject_id')))
            ->when($request->filled('from'), fn (Builder $query) => $query->where('created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->input('from'), $timezone)->utc()->format('Y-m-d H:i:s')))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->input('to'), $timezone)->addDay()->utc()->format('Y-m-d H:i:s')));

        $page = $query->orderByDesc('id')->paginate($request->integer('per_page', 25));
        $page->getCollection()->transform(fn (FiscalReceipt $receipt): array => self::present($receipt));

        return $this->success($page);
    }

    /**
     * A fiscal receipt
     *
     * Another store's receipt: 404; another branch's (for branch-bound staff): 403.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::RECEIPT . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $receipt = $this->receipt($request, $uuid, $this->readPermission($request));

        return $this->success(self::present($receipt));
    }

    /**
     * Retry a receipt now
     *
     * settings.manage. A pending or failed receipt is sent to the provider at once (a failed one becomes pending
     * first); the answer is the receipt afterwards. An issued receipt, or one recorded from Payme/Click: 422.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::RECEIPT . '}')]
    public function retry(Request $request, string $uuid, FiscalService $fiscal, AuditLogger $audit): JsonResponse
    {
        $receipt = $this->receipt($request, $uuid, Permission::SETTINGS_MANAGE);

        if ($receipt->status === FiscalReceiptStatus::ISSUED || $receipt->driver === 'external') {
            return $this->error("Chek allaqachon ro'yxatdan o'tgan.", ['reason' => 'already_issued'], 422);
        }

        $before = ['status' => $receipt->status->value, 'attempts' => $receipt->attempts];
        $receipt = $fiscal->retryNow($receipt);
        $audit->record('fiscal.receipt_retried', $receipt, $before, ['status' => $receipt->status->value, 'attempts' => $receipt->attempts], [], ['store_id' => $receipt->store_id, 'branch_id' => $receipt->branch_id]);

        return $this->success(self::present($receipt->load('originalReceipt:id,uuid')), $receipt->status === FiscalReceiptStatus::ISSUED
            ? "Chek ro'yxatdan o'tdi."
            : "Chek hali ro'yxatdan o'tmadi: " . ($receipt->error ?? "provayder javob bermadi."));
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(FiscalReceipt $receipt): array
    {
        return [
            'uuid' => $receipt->uuid,
            'type' => $receipt->type->value,
            'status' => $receipt->status->value,
            'payment_form' => $receipt->payment_form->value,
            'driver' => $receipt->driver,
            'provider' => $receipt->provider,
            'non_fiscal' => (bool) $receipt->non_fiscal,
            'tax_regime' => $receipt->tax_regime,
            'total' => (string) $receipt->total,
            'vat_total' => (string) $receipt->vat_total,
            'fiscal_sign' => $receipt->fiscal_sign,
            'receipt_number' => $receipt->receipt_number,
            'qr_url' => $receipt->qr_url,
            'terminal_id' => $receipt->terminal_id,
            'error' => $receipt->error,
            'attempts' => (int) $receipt->attempts,
            'next_attempt_at' => $receipt->next_attempt_at?->toIso8601String(),
            'issued_at' => $receipt->issued_at?->toIso8601String(),
            'created_at' => (string) $receipt->created_at?->toIso8601String(),
            'branch_id' => $receipt->branch_id !== null ? (int) $receipt->branch_id : null,
            'device_id' => $receipt->device_id !== null ? (int) $receipt->device_id : null,
            'subject_type' => $receipt->subject_type,
            'subject_id' => $receipt->subject_id,
            'original_receipt_uuid' => $receipt->originalReceipt?->uuid,
            'lines' => $receipt->lines ?? [],
            // Through the snapshot, so a receipt stored before a flag existed answers in today's shape (credit: false)
            'payments' => \App\Domain\Fiscal\FiscalSnapshot::payments(array_map(
                fn (array $row) => \App\Domain\Fiscal\FiscalSnapshot::payment($row),
                array_values(array_filter((array) ($receipt->payments ?? []), 'is_array')),
            )),
        ];
    }

    /**
     * finance.view when the caller has it, else reports.view (the route let in one of them): the branch scoping of
     * the list and of a receipt is checked with it.
     */
    private function readPermission(Request $request): Permission
    {
        $user = $request->user();

        return $user instanceof User && $this->permissions->has($user, Permission::FINANCE_VIEW) ? Permission::FINANCE_VIEW : Permission::REPORTS_VIEW;
    }

    private function receipt(Request $request, string $uuid, Permission $permission): FiscalReceipt
    {
        $receipt = preg_match('/^[0-9a-f-]{36}$/i', $uuid)
            ? FiscalReceipt::with('originalReceipt:id,uuid')->where('store_id', $this->storeId($request))->where('uuid', $uuid)->first()
            : null;
        if (!$receipt instanceof FiscalReceipt) {
            throw new HttpResponseException($this->error('Chek topilmadi.', [], 404));
        }

        $this->authorizeFor($request, $permission, $receipt);

        return $receipt;
    }
}
