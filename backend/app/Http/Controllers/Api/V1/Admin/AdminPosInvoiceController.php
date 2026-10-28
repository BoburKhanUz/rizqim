<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SubscriptionInvoiceStatus;
use App\Http\Requests\Admin\AdminRules;
use App\Models\SubscriptionInvoice;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Pos\PosInvoices;
use App\Services\Admin\Pos\PosPage;
use App\Services\Finance\InvoiceVoidException;
use App\Services\Finance\Payouts\PayoutService;
use App\Services\Finance\SubscriptionService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-24 Hisob-fakturalar: the subscription invoices of every store. Reading: admin.subscriptions.manage or
 * admin.finance.view. Marking paid and voiding: admin.finance.manage, a step-up and a `reason`, through
 * SubscriptionService (the same code as the Filament panel), audited with the invoice's status before and after.
 * Review W2: a paid or partly paid invoice is never voided, and a void of 1 000 000 so'm or more takes two members.
 */
#[Group('Admin')]
class AdminPosInvoiceController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PosInvoices $invoices,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Invoices (all stores)
     *
     * Newest first, pages (`page`, `per_page` 1-100, default 30). Filters: `status` (issued, paid, void, or `overdue`:
     * unpaid after the due date, Asia/Tashkent), `store_id`, `period` (YYYY-MM), `dunning` (the latest reminder step:
     * reminder_1, reminder_3, reminder_7, locked, unlocked), `q` (part of the number). Rows carry the days overdue and
     * the latest dunning step. `counts`: issued / overdue / paid / void invoices and the unpaid sum (not filtered).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PosInvoices::ROW . '>, ' . PosPage::SHAPE . ', counts: array{issued: int, overdue: int, paid: int, void: int, outstanding: string}}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([...array_map(fn (SubscriptionInvoiceStatus $status) => $status->value, SubscriptionInvoiceStatus::cases()), 'overdue'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'dunning' => ['nullable', Rule::in(PosInvoices::dunningStages())],
            'q' => 'nullable|string|min:2|max:60',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->invoices->query($filters)->orderByDesc('id')->paginate(PosPage::perPage($filters['per_page'] ?? null));

        return $this->success(PosPage::of($page, $this->invoices->rows($page->items()), ['counts' => $this->invoices->counts()]));
    }

    /**
     * An invoice
     *
     * The invoice with its lines, paid and outstanding parts and every dunning step, and the store's debt split
     * (subscription part and commission part).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{invoice: array<string, mixed>, store: array{id: int, name: string, status: string, debt_balance: string, subscription_debt: string, commission_debt: string}|null}}')]
    #[ApiDocResponse(404, 'No such invoice (not_found)', type: self::ERROR)]
    public function show(string $uuid): JsonResponse
    {
        $invoice = SubscriptionInvoice::where('uuid', $uuid)->first();

        return $invoice === null ? $this->notFound() : $this->success($this->invoices->detail($invoice));
    }

    /**
     * Mark an invoice paid
     *
     * `reason` (where the money came from). Only an unpaid (issued) invoice; marking one paid again changes nothing.
     * The balance does not change: the payment itself is booked as a top-up, which pays the oldest invoices first;
     * what the ledger has not paid of the invoice stays in the store's subscription debt. Ends the dunning for it.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{invoice: array<string, mixed>, store: array<string, mixed>|null}}')]
    #[ApiDocResponse(422, 'A voided invoice (invoice_void)', type: self::ERROR)]
    public function markPaid(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $invoice = SubscriptionInvoice::where('uuid', $uuid)->first();
        if ($invoice === null) {
            return $this->notFound();
        }
        if ($invoice->status === SubscriptionInvoiceStatus::VOID) {
            return $this->refuse("Bekor qilingan hisob-fakturani to'langan deb belgilab bo'lmaydi.", 'invoice_void', 422);
        }

        $before = $invoice->status->value;
        $invoice = $this->subscriptions->markPaid($invoice, $this->member($request));

        return $this->changed($request, $invoice, $before, "Hisob-faktura to'langan deb belgilandi.");
    }

    /**
     * Void an invoice
     *
     * `reason`. Only an unpaid invoice: a paid or partly paid one is refused (422 `invoice_paid`; money goes back by a
     * manual adjustment). The invoice no longer counts as income and its amount comes off the store's debt (a reversal
     * row in the ledger). From 1 000 000 so'm (`config('finance.four_eyes_threshold')`) two members: the first call is a
     * request (the invoice stays `issued`, `invoice.void_request` shows who asked, audited as
     * `admin.pos.invoice_void_requested`), the same member again changes nothing, a different member's call voids it.
     * Voiding one again changes nothing.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{invoice: array<string, mixed>, store: array<string, mixed>|null}}')]
    #[ApiDocResponse(422, 'A paid or partly paid invoice (invoice_paid)', type: self::ERROR)]
    public function void(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $invoice = SubscriptionInvoice::where('uuid', $uuid)->first();
        if ($invoice === null) {
            return $this->notFound();
        }

        $before = $invoice->status->value;
        $requestedBefore = $invoice->void_requested_by;
        try {
            $invoice = $this->subscriptions->voidInvoice($invoice, $this->member($request), (string) $data['reason']);
        } catch (InvoiceVoidException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status);
        }

        if ($invoice->status !== SubscriptionInvoiceStatus::VOID && $invoice->void_requested_by !== null) {
            $audit = AdminAudit::for($request)->action('admin.pos.invoice_void_requested')->subject($invoice)->store((int) $invoice->store_id)
                ->changes(['void_requested_by' => $requestedBefore], ['void_requested_by' => $invoice->void_requested_by])
                ->meta(['number' => $invoice->number, 'amount' => $invoice->total()->toString()]);
            if ($requestedBefore !== null) {
                $audit->skip();
            }

            return $this->success($this->invoices->detail($invoice->refresh()),
                "Bekor qilish so'raldi: " . PayoutService::uzs(PayoutService::threshold()) . " va undan katta hisob-fakturani ikkinchi xodim tasdiqlashi kerak.");
        }

        return $this->changed($request, $invoice, $before, 'Hisob-faktura bekor qilindi.');
    }

    private function changed(Request $request, SubscriptionInvoice $invoice, string $before, string $message): JsonResponse
    {
        $invoice->refresh();
        $audit = AdminAudit::for($request)->subject($invoice)->store((int) $invoice->store_id)
            ->changes(['status' => $before], ['status' => $invoice->status->value])->meta(['number' => $invoice->number]);
        if ($before === $invoice->status->value) {
            $audit->skip();
        }

        return $this->success($this->invoices->detail($invoice), $message);
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Hisob-faktura topilmadi.', 'not_found', 404);
    }
}
