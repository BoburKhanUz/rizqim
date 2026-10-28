<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\FinanceAdjustment;
use App\Models\Store;
use App\Services\Admin\AdminAudit;
use App\Services\Finance\Adjustments\AdjustmentException;
use App\Services\Finance\Adjustments\AdjustmentKind;
use App\Services\Finance\Adjustments\AdjustmentPresenter;
use App\Services\Finance\Adjustments\AdjustmentService;
use App\Services\Finance\Payouts\PayoutService;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P4-23: Moliya → Qo'lda tuzatish. Reads need admin.finance.view; making, approving and rejecting admin.finance.manage,
 * a step-up and a `reason`, with an Idempotency-Key. From 1 000 000 so'm an adjustment waits for a DIFFERENT member's
 * approval before its ledger row is written (App\Services\Finance\Adjustments\AdjustmentService). Audited on the
 * adjustment (admin.finance.adjustments.created / .approved / .rejected).
 */
#[Group('Admin')]
class AdminFinanceAdjustmentController extends AdminController
{
    public function __construct(
        private readonly AdjustmentService $adjustments,
        private readonly AdjustmentPresenter $presenter,
    ) {}

    /**
     * Manual adjustments
     *
     * `status`: pending (waiting for a second approval), applied, rejected, all (the default); `store_id`; newest
     * first, cursor pages (`per_page` 1–100, default 30). `kinds` — what an adjustment can change, with labels;
     * `threshold` — from this amount a second member approves.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . AdjustmentPresenter::SHAPE . '>, next_cursor: string|null, per_page: int, counts: array{pending: int}, kinds: list<array{value: string, label: string, ledger: string, sign: int}>, threshold: string}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([FinanceAdjustment::PENDING, FinanceAdjustment::APPLIED, FinanceAdjustment::REJECTED, 'all'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $status = $data['status'] ?? 'all';

        $page = FinanceAdjustment::query()
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when(isset($data['store_id']), fn (Builder $query) => $query->where('store_id', (int) $data['store_id']))
            ->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        $member = $this->member($request);

        return $this->success([
            'data' => collect($page->items())->map(fn (FinanceAdjustment $adjustment) => $this->presenter->present($adjustment, $member))->values()->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'counts' => ['pending' => FinanceAdjustment::where('status', FinanceAdjustment::PENDING)->count()],
            'kinds' => array_map(fn (AdjustmentKind $kind) => ['value' => $kind->value, 'label' => $kind->label(), 'ledger' => $kind->ledger(), 'sign' => $kind->sign()], AdjustmentKind::cases()),
            'threshold' => PayoutService::threshold()->toString(),
        ]);
    }

    /**
     * An adjustment
     *
     * With `transactions` — the ledger rows it wrote once applied.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . AdjustmentPresenter::SHAPE . '}')]
    public function show(Request $request, int $id): JsonResponse
    {
        $adjustment = FinanceAdjustment::find($id);
        if ($adjustment === null) {
            return $this->refuse('Tuzatish topilmadi.', 'not_found', 404);
        }

        return $this->success($this->presenter->present($adjustment, $this->member($request), true));
    }

    /**
     * The adjustment's document
     *
     * The file as it was uploaded (private storage). 404 when it has none.
     */
    public function document(Request $request, int $id): JsonResponse|StreamedResponse
    {
        $adjustment = FinanceAdjustment::find($id);
        $disk = Storage::disk($this->adjustments->disk());
        if ($adjustment === null || $adjustment->document_path === null || !$disk->exists($adjustment->document_path)) {
            return $this->refuse('Hujjat topilmadi.', 'not_found', 404);
        }

        return $disk->download($adjustment->document_path, (string) $adjustment->document_name, array_filter([
            'Content-Type' => $adjustment->document_mime,
            'Cache-Control' => 'private, no-store',
        ]));
    }

    /**
     * Make an adjustment
     *
     * Multipart or JSON: `store_id`, `kind` (debt_charge — debt up; debt_credit — commission debt down; debt_payment —
     * money received outside the top-up flow, pays the oldest invoices first; wallet_credit; wallet_debit — never
     * below zero, 422 `insufficient_wallet`), `amount` (so'm, more than 0, up to 2 decimals), `reason`, optional
     * `document` (pdf, jpg, png, webp; up to 10 MB). Under 1 000 000 so'm it is applied at once (`status: applied`);
     * from it, `pending` until another member approves it.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . AdjustmentPresenter::SHAPE . '}')]
    #[ApiDocResponse(422, 'The wallet cannot cover a wallet_debit (insufficient_wallet)', type: 'array{success: false, message: string, code: string, data: array{reason: string, wallet_balance?: string}}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => 'required|integer|min:1|max:999999999999999999',
            'kind' => ['required', Rule::in(AdjustmentKind::values())],
            'amount' => ['required', 'numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'reason' => AdminRules::reason(),
            'document' => ['nullable', 'file', 'max:' . (int) config('finance.adjustments.max_kb', 10240), 'mimes:' . implode(',', (array) config('finance.adjustments.mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp']))],
        ], UzValidation::messages([
            'amount.regex' => "Summa so'mda, ko'pi bilan 2 xonali kasr bilan yozilishi kerak.",
            'document.mimes' => 'Hujjat PDF yoki rasm (JPG, PNG, WEBP) bo\'lishi kerak.',
            'document.max' => "Hujjat 10 MB dan katta bo'lmasligi kerak.",
        ]));

        $store = Store::withTrashed()->find((int) $data['store_id']);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $member = $this->member($request);
        try {
            $adjustment = $this->adjustments->create($store, $member, AdjustmentKind::from($data['kind']), Money::of((string) $data['amount']), $data['reason'], $request->file('document'));
        } catch (AdjustmentException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($adjustment)->store((int) $store->id)
            ->changes([], ['status' => $adjustment->status, 'kind' => $adjustment->kind, 'amount' => Money::of($adjustment->amount)->toString()])
            ->meta(['needs_second_approval' => $adjustment->needs_second_approval, 'has_document' => $adjustment->document_path !== null]);

        $message = $adjustment->status === FinanceAdjustment::APPLIED
            ? "Tuzatish qo'llandi."
            : 'Tuzatish yaratildi. ' . PayoutService::uzs(PayoutService::threshold()) . " va undan katta tuzatishni boshqa xodim tasdiqlashi kerak.";

        return $this->success($this->presenter->present($adjustment, $member, true), $message, 201);
    }

    /**
     * Approve an adjustment
     *
     * A pending adjustment, by a member other than the one who made it (403 `four_eyes`, a superadmin too); its
     * ledger row is written now. `reason` required. Repeating it answers the same result; decided otherwise: 409
     * `adjustment_decided`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . AdjustmentPresenter::SHAPE . '}')]
    #[ApiDocResponse(403, 'The maker cannot approve it (four_eyes) or step-up needed (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'Already decided (adjustment_decided)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, true);
    }

    /**
     * Reject an adjustment
     *
     * A pending adjustment (the one who made it may withdraw it this way); nothing is booked. `reason` required.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . AdjustmentPresenter::SHAPE . '}')]
    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, false);
    }

    private function decide(Request $request, int $id, bool $approve): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $adjustment = FinanceAdjustment::find($id);
        if ($adjustment === null) {
            return $this->refuse('Tuzatish topilmadi.', 'not_found', 404);
        }

        $member = $this->member($request);
        try {
            $result = $approve
                ? $this->adjustments->approve($adjustment, $member, $data['reason'])
                : $this->adjustments->reject($adjustment, $member, $data['reason']);
        } catch (AdjustmentException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $adjustment = $result['adjustment'];
        $audit = AdminAudit::for($request)->subject($adjustment)->store((int) $adjustment->store_id);
        if ($result['replayed']) {
            $audit->skip();
        } else {
            $audit->changes(['status' => FinanceAdjustment::PENDING], ['status' => $adjustment->status])->meta([
                'kind' => $adjustment->kind,
                'amount' => Money::of($adjustment->amount)->toString(),
                'requested_by' => $adjustment->requested_by !== null ? (int) $adjustment->requested_by : null,
            ]);
        }

        return $this->success($this->presenter->present($adjustment, $member, true), $approve ? "Tasdiqlandi, tuzatish qo'llandi." : 'Tuzatish rad etildi.');
    }
}
