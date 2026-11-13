<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Vouchers\PosPromoService;
use App\Domain\Pos\Vouchers\VoucherCode;
use App\Domain\Pos\Vouchers\VoucherException;
use App\Domain\Pos\Vouchers\VoucherPresenter;
use App\Domain\Pos\Vouchers\VoucherService;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\PosHall\PosHallController;
use App\Http\Controllers\Api\V1\PosHall\PosOrderController;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-43: gift certificates and promo codes at the till (docs/API.md "Sertifikat va vaucher"). Selling a certificate
 * and applying a promo code are new flows (the `vouchers` module, Asosiy+); spending a certificate is a bill tender
 * (POST /pos/bills/{uuid}/payments with `voucher`) and never asks the module. Online only: the till without the
 * server shows a plain message (the balance lives here). Another store's code: 404.
 */
#[Group('POS: vouchers', "Kassa: sovg'a sertifikatini sotish (bo'nak cheki), kod bo'yicha qidirish va promo-kod (P6-43).")]
class PosVoucherController extends PosHallController
{
    /**
     * Sell a gift certificate
     *
     * A non-menu sale: `face_value` (whole so'm, 1 000 – 100 000 000) paid by `tender` cash, card (`card_type` and the
     * terminal's `transaction_id` required) or qr (optional `reference`); optional `buyer_name`, `buyer_phone`
     * (+998…), `valid_until` (the last business day it pays, default a year), `note`. The money lands in the open shift
     * of the terminal (or `shift_uuid`; 409 `shift_not_open`) — the X/Z shows it under `vouchers.sold`, apart from the
     * sales — and an ADVANCE receipt is issued for it (`fiscal`). The answer carries the code and its `print` block
     * (store, code, QR text, value, validity) for the certificate slip. 422 `face_value_invalid`, `tender_invalid`,
     * `card_type_required`, `transaction_id_required`, `buyer_phone_invalid`, `valid_until_invalid`, `mxik_missing`.
     * The same Idempotency-Key returns the same certificate. Needs pos.take_payment and the `vouchers` module.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per sale, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . VoucherPresenter::VOUCHER . '}')]
    public function sell(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'face_value' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'tender' => 'required|string|in:cash,card,qr',
            'card_type' => 'nullable|string|max:16',
            'transaction_id' => 'nullable|string|max:64',
            'reference' => 'nullable|string|max:100',
            'buyer_name' => 'nullable|string|max:120',
            'buyer_phone' => 'nullable|string|max:32',
            'valid_until' => 'nullable|date_format:Y-m-d',
            'note' => 'nullable|string|max:500',
            'shift_uuid' => 'nullable|uuid',
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
        ]);
        $branch = $this->branchFor($request, $user, Permission::POS_TAKE_PAYMENT);

        $voucher = app(VoucherService::class)->sell(
            $user,
            $branch,
            array_map(fn ($value) => is_scalar($value) ? (string) $value : null, $data),
            $data['shift_uuid'] ?? null,
            $this->permissions->terminalOf($user),
            (string) $request->headers->get('Idempotency-Key'),
            fn () => $this->idempotency->markApplied($request),
        );

        return $this->success(app(VoucherPresenter::class)->voucher($voucher, false, $branch), "Sertifikat sotildi.", 201);
    }

    /**
     * Look up a gift certificate
     *
     * By the code as printed or scanned (spaces, dashes and lower case are fine): its balance, validity and status
     * (`expired` once its last business day has passed), the buyer's phone masked, and its last 10 entries. Another
     * store's or an unknown code: 404 `voucher_not_found`. Needs pos.take_payment.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{voucher: ' . VoucherPresenter::VOUCHER . ', entries: list<' . VoucherPresenter::ENTRY . '>}}')]
    public function show(Request $request, string $code): JsonResponse
    {
        $user = $this->staff($request);
        $normalized = VoucherCode::normalize($code);
        $voucher = $normalized !== null ? Voucher::where('store_id', $user->store_id)->where('code', $normalized)->first() : null;
        if ($voucher === null) {
            throw VoucherException::notFound();
        }
        $presenter = app(VoucherPresenter::class);
        $actingBranch = $this->permissions->actingBranchId($user);
        $branch = $actingBranch !== null ? \App\Models\Branch::find($actingBranch) : null;

        return $this->success([
            'voucher' => $presenter->voucher($voucher, false, $branch),
            'entries' => VoucherEntry::where('voucher_id', $voucher->id)->with('user:id,name')->orderByDesc('id')->limit(10)->get()
                ->map(fn (VoucherEntry $entry) => $presenter->entry($entry))->values()->all(),
        ]);
    }

    /**
     * Apply a promo code to a POS order
     *
     * A discount voucher is a store promo code: `code` becomes the order's discount on the dishes with the reason
     * `promo:<CODE>` (a percentage without a cap stays a percent; a fixed amount, or a capped percentage, is worked
     * out now). The cashier's discount limit does not apply; the waiter rule does. One code per order: 409
     * `promo_already_applied` (take it off first); a discount typed by hand later replaces it. Usage counts as for
     * online orders (a cancelled order gives it back). 404 `promo_not_found` (unknown, another store's, or a
     * platform-wide code), 422 `promo_inactive`, `promo_min_order`, `promo_limit_reached`, `promo_no_discount`; 409
     * `paid_bill_affected`, `version_conflict`. Needs orders.manage and the `vouchers` module; online only.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    public function applyPromo(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0|max:2147483647',
            'code' => 'required|string|max:50',
        ]);
        $order = $this->order($user, $uuid, Permission::ORDERS_VIEW);

        return $this->change($request, fn () => app(PosPromoService::class)->apply($order, (int) $data['version'], $user, (string) $data['code']), "Promo-kod qo'llandi.");
    }

    /**
     * Take the promo code off a POS order
     *
     * The order's discount goes with it (422 `promo_not_applied` when it has none). Needs orders.manage.
     */
    #[QueryParameter('version', description: "The order's version (409 version_conflict when stale).", type: 'int', required: true)]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    public function removePromo(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $request->validate(['version' => 'required|integer|min:0|max:2147483647']);
        $order = $this->order($user, $uuid, Permission::ORDERS_VIEW);

        return $this->change($request, fn () => app(PosPromoService::class)->remove($order, (int) $request->query('version', $request->input('version')), $user), 'Promo-kod olib tashlandi.');
    }
}
