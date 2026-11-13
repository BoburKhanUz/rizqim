<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\QrPayments\QrPaymentPresenter;
use App\Domain\Pos\QrPayments\QrPaymentService;
use App\Domain\Pos\QrPayments\QrPaymentStatus;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrProviders;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\PosQrPayment;
use App\Models\User;
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
 * P6-41/P6-42: a dynamic QR (Click, Payme, UzQR) for a bill at the till (docs/API.md "QR to'lov kassada"). Business
 * rules: App\Domain\Pos\QrPayments\QrPaymentService. The provider's webhook confirms it; the till hears `qr.paid`
 * (or polls GET /pos/qr/{uuid}) and refetches the bill.
 */
#[Group('POS: QR payments', "Kassa: Click, Payme va UzQR dinamik QR kodi schyot summasiga, avtomatik tasdiq (P6-41/P6-42).")]
#[HeaderParameter('Idempotency-Key', description: "Required on every change: a new UUID per action, the same one when retrying it (428 without).", type: 'string')]
class PosQrPaymentController extends PosBillingController
{
    private const ONE = 'array{success: true, message: string, data: array{qr: ' . QrPaymentPresenter::VIEW . '}}';

    /**
     * A bill's QR requests
     *
     * The bill's QR requests, newest first (`pending` is the one to show; at most one). Needs orders.view.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{qrs: list<' . QrPaymentPresenter::VIEW . '>}}')]
    public function index(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        [$bill] = $this->bill($user, $uuid);
        $service = app(QrPaymentService::class);
        $presenter = app(QrPaymentPresenter::class);

        $qrs = PosQrPayment::where('bill_id', $bill->id)->orderByDesc('id')->limit(20)->get()
            ->map(fn (PosQrPayment $qr) => $presenter->present($service->refresh($qr)))->values()->all();

        return $this->success(['qrs' => $qrs]);
    }

    /**
     * Show a QR for the bill
     *
     * Makes a dynamic QR for what the bill still owes (or `amount` of it, the QR part of a mixed payment): `provider`
     * click, payme or uzqr (default: the branch's default provider). Print `payload` on the bill and send `display` to
     * the customer display (PosDisplayPayment.qr). One pending QR per bill: a new one cancels the old. The provider
     * confirms the payment by itself (no cashier action): the bill takes a `qr` payment (`reference` = the provider's
     * transaction id) and its sale receipt is issued by Rizqim (the provider registers none). Valid for the branch's
     * `expiry_minutes`. Needs the `qr_payments` module (403 module_disabled), pos.take_payment and an open shift (409
     * `shift_not_open`); 422 `qr_provider_disabled` (the branch does not offer it), 409 `provider_not_configured` (the
     * store's own Click/Payme merchant account, or the UzQR gateway, is not set up), 422 `overpayment` (`due`),
     * `mxik_missing`; 409 `bill_paid`, `bill_void`, `bill_awaiting_fiscal`, `order_changed`, `bill_changed`. Offline
     * the till cannot make one: it offers the static QR confirmed by hand.
     */
    #[ApiDocResponse(201, type: self::ONE)]
    public function store(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'provider' => ['nullable', 'string', Rule::enum(QrProvider::class)],
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'shift_uuid' => 'nullable|uuid',
        ]);
        [$bill] = $this->bill($user, $uuid);

        $qr = app(QrPaymentService::class)->create(
            $user,
            $bill,
            $data['provider'] ?? null,
            isset($data['amount']) ? (string) $data['amount'] : null,
            $data['shift_uuid'] ?? null,
            $this->terminal($user),
            $this->applied($request),
        );

        return $this->success(['qr' => app(QrPaymentPresenter::class)->present($qr)], 'QR tayyor: mijoz skanerlab to\'lasin.', 201);
    }

    /**
     * A QR request
     *
     * Its status now (the till's poll when it has no realtime): `pending`, `paid`, `cancelled` (`cancel_reason`:
     * cashier, replaced, bill_changed, shift_closed, payment_refused:<code>, provider_cancelled, payme_cancel:<n>),
     * `expired` or `refunded`. Reading it expires it when its time ran out; a pending UzQR asks its gateway. Needs
     * orders.view.
     */
    #[ApiDocResponse(200, type: self::ONE)]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $qr = app(QrPaymentService::class)->refresh($this->qr($user, $uuid));

        return $this->success(['qr' => app(QrPaymentPresenter::class)->present($qr)]);
    }

    /**
     * Cancel a QR
     *
     * The cashier takes the QR back (the customer pays another way): its later confirmation is refused, so the provider
     * does not take the money. Already cancelled or expired: returned as it is; paid: 409 `qr_already_paid` (refund
     * it instead). Optional `reason` (audited). Needs pos.take_payment.
     */
    #[QueryParameter('reason', description: 'Why it was cancelled (audited; up to 200 characters).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: self::ONE)]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate(['reason' => 'nullable|string|max:200']);
        $qr = $this->qr($user, $uuid);

        $qr = app(QrPaymentService::class)->cancel($user, $qr, $data['reason'] ?? null, $this->terminal($user), $this->applied($request));

        return $this->success(['qr' => app(QrPaymentPresenter::class)->present($qr)], 'QR bekor qilindi.');
    }

    /**
     * Confirm a sandbox QR (demo)
     *
     * Plays the provider's confirmation for a QR of a sandbox driver (`simulated: true`; demo and e2e stands):
     * exactly what a real webhook does. A real provider's QR: 409 `qr_not_simulated`; switched off (production): 404.
     * Needs pos.take_payment.
     */
    #[ApiDocResponse(200, type: self::ONE)]
    public function simulatePaid(Request $request, string $uuid): JsonResponse
    {
        if (!(bool) config('qr_payments.simulate')) {
            throw new HttpResponseException($this->error('Topilmadi.', [], 404));
        }
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $qr = $this->qr($user, $uuid);
        if (!app(QrProviders::class)->of($qr)->simulated()) {
            throw PosActionException::conflict('qr_not_simulated', "Bu QR haqiqiy provayderniki: uni faqat mijoz to'lovi tasdiqlaydi.");
        }

        $service = app(QrPaymentService::class);
        $outcome = $service->confirm($qr, 'sandbox-' . Str::lower(Str::random(12)));
        $qr->refresh();
        if ($outcome === 'refused' && $qr->status !== QrPaymentStatus::PAID) {
            throw PosActionException::conflict('qr_not_payable', "QR endi to'lanmaydi (" . $qr->status->value . ').');
        }

        return $this->success(['qr' => app(QrPaymentPresenter::class)->present($qr)], "To'landi.");
    }

    private function qr(User $user, string $uuid): PosQrPayment
    {
        $qr = Str::isUuid($uuid) ? PosQrPayment::where('uuid', $uuid)->where('store_id', $user->store_id)->first() : null;
        $order = $qr !== null ? Order::find($qr->order_id) : null;
        if (!$qr instanceof PosQrPayment || !$order instanceof Order || !$this->scope->allows($user, Permission::ORDERS_VIEW, $order)) {
            throw new HttpResponseException($this->error('QR topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        return $qr;
    }
}
