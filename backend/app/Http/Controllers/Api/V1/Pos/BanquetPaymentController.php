<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\Payments\BanquetCancellationService;
use App\Domain\Pos\Banquet\Payments\BanquetPaymentPresenter;
use App\Domain\Pos\Banquet\Payments\BanquetPaymentService;
use App\Domain\Pos\Banquet\Payments\BanquetSchedule;
use App\Domain\Pos\Billing\PosFiscalReceipt;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Services\Idempotency\IdempotencyService;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * P6-53: a banquet event's payment schedule for the till and the web panel (docs/API.md "Tadbirlar: to'lov jadvali").
 * Reading with banquets.manage or pos.take_payment; taking a row's payment at the till with pos.take_payment (an open
 * shift on the terminal); editing the schedule, recording a bank transfer and cancelling with banquets.manage. Money
 * of an existing event is never gated by the `banquet` module (editing the schedule is). Another store's or branch's
 * event or row: 404.
 */
#[Group('POS: banquet payments', "Tadbir to'lov jadvali: zaklad, oraliq va yakuniy to'lovlar, bo'nak cheklari, bank o'tkazmasi, bekor qilish va qaytarish (P6-53).")]
class BanquetPaymentController extends MenuStaffController
{
    /**
     * An event's payment schedule
     *
     * The rows (deposit, interims, final: due date, amount, status, tender, ADVANCE receipt; `overdue` once its date has
     * passed unpaid), every money entry (payments, bank transfers, refunds, retained parts), the figures for the event
     * card (`summary`: paid, outstanding, overdue, next due) and, for a cancelled event, what the cancellation decided.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPaymentPresenter::SCHEDULE . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $event = $this->event($request, $uuid, false);

        return $this->success(app(BanquetPaymentPresenter::class)->schedule($event));
    }

    /**
     * Edit the unpaid rows of the schedule
     *
     * `rows`: the unpaid rows as they should be (`uuid` of an existing unpaid row keeps it; `kind` deposit, interim or
     * final; `due_date`; `amount`); they must add up to what is still to pay (422 `schedule_sum_mismatch` with
     * `expected`). Paid rows never change. `version`: the event's (409 `version_conflict`). Needs banquets.manage and
     * the `banquet` module; audited.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: the same key when retrying the request returns the first answer.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPaymentPresenter::SCHEDULE . '}')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'rows' => 'present|array|max:24',
            'rows.*.uuid' => 'nullable|uuid',
            'rows.*.kind' => ['required', 'string', Rule::in(BanquetPaymentKind::values())],
            'rows.*.due_date' => 'required|date_format:Y-m-d',
            'rows.*.amount' => 'required|numeric|min:0.01|max:9999999999|decimal:0,2',
            'version' => 'nullable|integer|min:1',
        ], UzValidation::messages());
        $event = $this->event($request, $uuid, true);
        $rows = array_map(fn (array $row) => [
            'uuid' => isset($row['uuid']) ? (string) $row['uuid'] : null,
            'kind' => (string) $row['kind'],
            'due_date' => (string) $row['due_date'],
            'amount' => (string) $row['amount'],
        ], array_values($data['rows']));

        $event = app(BanquetSchedule::class)->replace($event, $rows, isset($data['version']) ? (int) $data['version'] : null, $request->user());

        return $this->success(app(BanquetPaymentPresenter::class)->schedule($event), "To'lov jadvali saqlandi.");
    }

    /**
     * Take a scheduled payment at the till
     *
     * The row's amount (or a part: `amount`; the rest stays an unpaid row with the same date) by `tender` cash, card
     * (`card_type` and the terminal's `transaction_id` required) or qr (a static QR; optional `reference`). The money
     * lands in the open shift of the terminal (or `shift_uuid`; 409 `shift_not_open`) — the X/Z shows it under
     * `banquets.taken` — and an ADVANCE receipt is issued at once (`fiscal`). The event becomes `confirmed` once its
     * deposit row is paid in full. 409 `payment_not_due`, `event_not_payable`; 422 `amount_exceeds_row`,
     * `tender_invalid`, `card_type_required`, `transaction_id_required`, `mxik_missing`. Needs pos.take_payment. The
     * same Idempotency-Key returns the same payment.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per payment, the same one when retrying it (400 without).', type: 'string')]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{schedule: ' . BanquetPaymentPresenter::SCHEDULE . ', entry: ' . BanquetPaymentPresenter::ENTRY . ', fiscal: ' . PosFiscalReceipt::FISCAL . '}}')]
    public function pay(Request $request, string $uuid, string $row): JsonResponse
    {
        $key = $this->idempotencyKey($request);
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:9999999999|decimal:0,2',
            'tender' => 'required|string|in:cash,card,qr',
            'card_type' => 'nullable|string|max:32',
            'transaction_id' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:100',
            'shift_uuid' => 'nullable|uuid',
        ], UzValidation::messages());
        $user = $request->user();
        $event = $this->event($request, $uuid, false);
        $this->authorizeFor($request, Permission::POS_TAKE_PAYMENT, $event);
        $payment = $this->row($event, $row);

        $entry = app(BanquetPaymentService::class)->pay(
            $event,
            $payment,
            array_map(fn ($value) => is_scalar($value) ? (string) $value : null, $data) + ['tender' => (string) $data['tender']],
            $data['shift_uuid'] ?? null,
            $user,
            $this->permissions->terminalOf($user),
            $key,
            fn () => app(IdempotencyService::class)->markApplied($request),
        );

        return $this->success([
            'schedule' => app(BanquetPaymentPresenter::class)->schedule($event->refresh()),
            'entry' => app(BanquetPaymentPresenter::class)->entry($entry),
            // The ADVANCE receipt as registered, for the till to print
            'fiscal' => PosFiscalReceipt::fiscal($entry->fiscal_status, $entry->fiscal_receipt_uuid, null, (int) $event->store_id),
        ], "To'lov qabul qilindi.", 201);
    }

    /**
     * Record a bank transfer against a row
     *
     * The manager records money the customer sent by bank transfer: `reference` (the payment order number), `paid_on`
     * (default today, never in the future), optional `amount` (a part of the row). No shift and no receipt (the
     * invoice/ESF is issued outside the system); it counts in the event's paid total and is offset in the final sale
     * receipt as prepayment like the advances. Confirms the event like a deposit paid at the till. Needs
     * banquets.manage. The same Idempotency-Key returns the same entry.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per transfer, the same one when retrying it (400 without).', type: 'string')]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{schedule: ' . BanquetPaymentPresenter::SCHEDULE . ', entry: ' . BanquetPaymentPresenter::ENTRY . '}}')]
    public function transfer(Request $request, string $uuid, string $row): JsonResponse
    {
        $key = $this->idempotencyKey($request);
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:9999999999|decimal:0,2',
            'reference' => 'required|string|min:2|max:120',
            'paid_on' => 'nullable|date_format:Y-m-d',
        ], UzValidation::messages());
        $event = $this->event($request, $uuid, true);
        $payment = $this->row($event, $row);

        $entry = app(BanquetPaymentService::class)->transfer($event, $payment, [
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'reference' => (string) $data['reference'],
            'paid_on' => $data['paid_on'] ?? null,
        ], $request->user(), $key, fn () => app(IdempotencyService::class)->markApplied($request));

        return $this->success([
            'schedule' => app(BanquetPaymentPresenter::class)->schedule($event->refresh()),
            'entry' => app(BanquetPaymentPresenter::class)->entry($entry),
        ], "Bank o'tkazmasi yozildi.", 201);
    }

    /**
     * What cancelling the event would do
     *
     * The days left before the event, the rule band's retained %, what was paid, the retained and refunded parts and,
     * per paid row, how its refund goes back (`cash_drawer`, `card`, `qr`, `bank_transfer`). `retained`: preview a
     * manager's own retained amount (0 … paid). Needs banquets.manage.
     */
    #[QueryParameter('retained', description: "A manager's own retained amount to preview (0 … paid).", type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetCancellationService::QUOTE . '}')]
    public function quote(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['retained' => 'nullable|numeric|min:0|max:9999999999|decimal:0,2'], UzValidation::messages());
        $event = $this->event($request, $uuid, true);

        return $this->success(app(BanquetCancellationService::class)->quote($event, isset($data['retained']) ? Money::of((string) $data['retained']) : null));
    }

    /**
     * Cancel the event with its refund and retained parts
     *
     * `reason` required. The rule table decides the retained part of what was paid (GET …/cancellation shows it); a
     * manager may send `retained` (0 … paid) with `override_reason` (422 `override_reason_required`; audited, the
     * owner hears it). Refunds: cash from the drawer of the open shift on this terminal (409 `refund_needs_shift`
     * elsewhere; pos.refund or a manager's `approval_token`), card and QR the way they were paid (REFUND receipts
     * against the advance receipts), a bank transfer marked "to be returned by transfer"; the retained part is a SALE
     * receipt of the cancellation service paid by the advance (prepayment). The unpaid rows go and the halls are free.
     * Nothing paid: the event is just released. 409 `event_not_cancellable`. Needs banquets.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per cancellation, the same one when retrying it (400 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetPaymentPresenter::SCHEDULE . '}')]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $this->idempotencyKey($request);
        $data = $request->validate([
            'reason' => 'required|string|min:3|max:500',
            'retained' => 'nullable|numeric|min:0|max:9999999999|decimal:0,2',
            'override_reason' => 'nullable|string|max:500',
            'approval_token' => 'nullable|string|max:255',
        ], UzValidation::messages());
        $user = $request->user();
        $event = $this->event($request, $uuid, true);

        app(BanquetCancellationService::class)->cancel(
            $event,
            trim((string) $data['reason']),
            isset($data['retained']) ? Money::of((string) $data['retained']) : null,
            filled($data['override_reason'] ?? null) ? trim((string) $data['override_reason']) : null,
            $data['approval_token'] ?? null,
            $user,
            $this->permissions->terminalOf($user)?->id,
            fn () => app(IdempotencyService::class)->markApplied($request),
        );

        return $this->success(app(BanquetPaymentPresenter::class)->schedule($event->refresh()), 'Tadbir bekor qilindi.');
    }

    private function event(Request $request, string $uuid, bool $write): BanquetEvent
    {
        $acting = $this->permissions->actingBranchId($request->user());
        $event = Str::isUuid($uuid) ? BanquetEvent::where('store_id', $this->storeId($request))
            ->where('uuid', $uuid)
            ->when($acting !== null, fn ($query) => $query->where('branch_id', $acting))
            ->first() : null;
        if ($event === null) {
            throw new HttpResponseException($this->error('Tadbir topilmadi.', ['reason' => 'event_not_found'], 404));
        }
        if ($write) {
            $this->authorizeFor($request, Permission::BANQUETS_MANAGE, $event);
        } elseif (!$this->permissions->allows($request->user(), Permission::BANQUETS_MANAGE, $event)
            && !$this->permissions->allows($request->user(), Permission::POS_TAKE_PAYMENT, $event)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $event;
    }

    private function row(BanquetEvent $event, string $uuid): BanquetPayment
    {
        $row = Str::isUuid($uuid) ? BanquetPayment::where('banquet_event_id', $event->id)->where('uuid', $uuid)->first() : null;
        if ($row === null) {
            throw new HttpResponseException($this->error("To'lov qatori topilmadi.", ['reason' => 'payment_not_found'], 404));
        }

        return $row;
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->headers->get('Idempotency-Key');
        if ($key === null || trim($key) === '') {
            throw new HttpResponseException($this->error("Idempotency-Key sarlavhasi majburiy: pul bilan bog'liq so'rov qayta yuborilganda ikki marta yozilmasligi uchun.", ['reason' => 'idempotency_key_required'], 400));
        }

        return $key;
    }
}
