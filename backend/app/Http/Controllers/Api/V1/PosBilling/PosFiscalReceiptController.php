<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\PosFiscalReceipt;
use App\Enums\Permission;
use App\Models\FiscalReceipt;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * P3-26: a receipt as registered, read at the till to print it (again): the cashier holds pos.take_payment, not the
 * web panel's finance.view / reports.view of GET /owner/fiscal/receipts/{uuid}.
 */
#[Group('POS: billing')]
class PosFiscalReceiptController extends PosBillingController
{
    /**
     * A receipt for the till
     *
     * The receipt as registered (fiscal sign, receipt number, QR link, fiscal module id `terminal_id`, `non_fiscal`,
     * `issued_at`, type, payment form, lines and payments as sent), to print it or a copy. The same object comes back
     * in `fiscal.receipt` of a bill payment, a refund, a deposit and a nasiya repayment. Needs pos.take_payment in the
     * receipt's branch (a terminal PIN session: its terminal's branch); another store's or branch's receipt: 404.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosFiscalReceipt::RECEIPT . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $receipt = Str::isUuid($uuid) ? FiscalReceipt::where('store_id', $user->store_id)->where('uuid', $uuid)->first() : null;

        if (!$receipt instanceof FiscalReceipt || !$this->permissions->allows($user, Permission::POS_TAKE_PAYMENT, $receipt)) {
            return $this->error('Chek topilmadi yoki sizning filialingizga tegishli emas.', [], 404);
        }

        return $this->success(PosFiscalReceipt::present($receipt));
    }
}
