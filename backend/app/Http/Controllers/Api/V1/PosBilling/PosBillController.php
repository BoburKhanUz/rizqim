<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\BillFiscalizer;
use App\Domain\Pos\Billing\BillPresenter;
use App\Domain\Pos\Billing\BillService;
use App\Domain\Pos\Billing\BillSplitter;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\OrderCloser;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\PosTender;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\PosRefund;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * P3-18: an order's bills (schyot), splitting, printing, payments and closing (POS_SPEC 2.9; docs/API.md "Schyot,
 * to'lov va smena"). Business rules: BillService, PaymentService, BillFiscalizer, OrderCloser.
 */
#[Group('POS: billing', "Kassa: schyot, bo'lish, to'lov, qaytarish, smena, X/Z va menejer tasdig'i (P3-18).")]
#[HeaderParameter('Idempotency-Key', description: "Required on every change: a new UUID per action, the same one when retrying it (428 without).", type: 'string')]
class PosBillController extends PosBillingController
{
    private const STATE = 'array{order: ' . BillPresenter::ORDER . ', bills: list<' . BillPresenter::BILL . '>, refunds: list<' . BillPresenter::REFUND . '>}';

    /**
     * An order's bills
     *
     * The order's billing state (`billed_amount`, `paid_amount`, `unbilled_amount`: what no bill carries yet, e.g. a
     * round added after the bill was printed; `fiscal_pending`: a bill is paid but its receipt is not issued yet,
     * "fiskal kutmoqda"; `bills_out_of_date`: the order changed after its open bills were made: bill again), its bills
     * (void ones only with `all=1`) with their lines, charges, per-guest amount and payments, and its refunds. Needs
     * orders.view in the order's branch.
     */
    #[QueryParameter('all', description: 'Include voided bills.', type: 'boolean', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function index(Request $request, BillPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $order = $this->order($user, $uuid);

        return $this->success($this->state($order, $presenter, $request->boolean('all')));
    }

    /**
     * Make or split the bill
     *
     * Bills what is left to bill of the order (bills already being paid stay; other open or printed bills are voided
     * and made again). `mode`: `single` (one bill), `equal` (`parts` bills with the same total; the remainder tiyin on
     * the first), `items` (`bills`: each with its `items` — `order_item_id` and `quantity`, fractions too, e.g. 0.5 of
     * 1.5 kg; every line left must be given out in full; optional `guests` for the per-guest amount). The discount and
     * the charges (room fee, minimum spend top-up, service charge, place fee) are shared in proportion; all shares are
     * exact to the tiyin. `version`: the order's (409 `version_conflict` when stale). One bill: orders.manage or
     * pos.take_payment; splitting: pos.take_payment (403 `permission_denied`). A waiter bills only their own orders
     * (another waiter's: a manager's `approval_token` for orders.manage, else 403 `approval_required`). 409
     * `nothing_to_bill`, `order_closed`,
     * `not_pos_order`; 422 `items_not_covered`, `bill_empty`, `item_not_billable`, `parts_invalid`.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function store(Request $request, BillService $bills, BillPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0|max:2147483647',
            'mode' => ['required', 'string', Rule::in(['single', 'equal', 'items'])],
            'parts' => 'required_if:mode,equal|nullable|integer|min:1|max:' . BillSplitter::MAX_PARTS,
            'bills' => 'required_if:mode,items|nullable|array|min:1|max:' . BillSplitter::MAX_PARTS,
            'bills.*.items' => 'required|array|min:1|max:200',
            'bills.*.items.*.order_item_id' => 'required|integer|min:1|max:999999999999',
            'bills.*.items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999', 'regex:/^\d+(\.\d{1,3})?$/'],
            'bills.*.guests' => 'nullable|integer|min:1|max:500',
            'approval_token' => 'nullable|string|max:100',
        ]);
        $order = $this->order($user, $uuid);

        $bills->create(
            $user,
            $order,
            $data['mode'],
            (int) ($data['parts'] ?? 1),
            array_map(fn (array $bill) => [
                'items' => array_map(fn (array $item) => ['order_item_id' => (int) $item['order_item_id'], 'quantity' => (string) $item['quantity']], $bill['items']),
                'guests' => isset($bill['guests']) ? (int) $bill['guests'] : null,
            ], $data['mode'] === 'items' ? (array) $data['bills'] : []),
            (int) $data['version'],
            $this->terminal($user),
            $this->applied($request),
            $data['approval_token'] ?? null,
        );

        return $this->success($this->state($order->refresh(), $presenter, false), 'Schyot tayyor.', 201);
    }

    /**
     * Print the bill
     *
     * Returns the bill to print ("Bu fiskal chek emas" is the template's). The first print hands it to the guest and
     * locks the order (`bill_locked_at`: voids and discounts need a manager from then on); printing again is a copy:
     * `copy: true`, print "NUSXA"; it needs pos.reprint_bill or a manager's `approval_token` (403 `approval_required`).
     * An open bill made before the order changed: 409 `order_changed` (bill again). Optional `version` (409 when
     * stale). Needs orders.manage or pos.take_payment.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{bill: ' . BillPresenter::BILL . ', copy: bool, order: ' . BillPresenter::ORDER . '}}')]
    public function print(Request $request, BillService $bills, BillPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'nullable|integer|min:0|max:2147483647',
            'approval_token' => 'nullable|string|max:100',
        ]);
        [$bill] = $this->bill($user, $uuid);

        $result = $bills->print($user, $bill, $data['approval_token'] ?? null, $this->terminal($user), isset($data['version']) ? (int) $data['version'] : null, $this->applied($request));

        return $this->success([
            'bill' => $presenter->bill($result['bill']),
            'copy' => $result['copy'],
            'order' => $presenter->order(Order::findOrFail($result['bill']->order_id)),
        ], $result['copy'] ? 'Schyot nusxasi.' : 'Schyot chiqarildi.');
    }

    /**
     * Take payment for a bill
     *
     * One or more `tenders` (a mixed payment), each with the `amount` it pays of the bill: `cash` (optional
     * `tendered_amount`: what the guest gave, the change is worked out), `card` (a separate bank terminal:
     * `card_type` — uzcard, humo, visa, mastercard, unionpay, mir, other — and the terminal's `transaction_id` are
     * required), `qr` (optional `reference`), `prepayment` (the booking's deposit, at most what is left of it, once
     * per bill), `account` (nasiya: `account_uuid`; needs customer_accounts.manage or a manager's `approval_token`;
     * once per bill). Payments may come in several requests; together they never exceed the bill (422 `overpayment`).
     * The money lands in the open shift of the terminal (or `shift_uuid`; 409 `shift_not_open`). When the bill is
     * covered, its sale receipt is asked for: the bill is `paid` once the receipt is issued (`fiscal.status`), and the
     * order is closed (delivered, paid, table free) once all its bills are. A receipt not issued yet leaves the bill
     * "fiskal kutmoqda" (`fiscal.status` pending or failed): retry with `.../fiscal-retry`. A line without MXIK: 422
     * `mxik_missing` before any money is taken. Optional `version`. Needs pos.take_payment.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{bill: ' . BillPresenter::BILL . ', order: ' . BillPresenter::ORDER . '}}')]
    public function pay(Request $request, PaymentService $payments, BillPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'tenders' => 'required|array|min:1|max:10',
            'tenders.*.tender' => ['required', 'string', Rule::in(array_map(fn (PosTender $tender) => $tender->value, PosTender::cases()))],
            'tenders.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'tenders.*.tendered_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'tenders.*.card_type' => 'nullable|string|max:16',
            'tenders.*.transaction_id' => 'nullable|string|max:64',
            'tenders.*.reference' => 'nullable|string|max:100',
            'tenders.*.account_uuid' => 'nullable|uuid',
            'approval_token' => 'nullable|string|max:100',
            'shift_uuid' => 'nullable|uuid',
            'version' => 'nullable|integer|min:0|max:2147483647',
        ]);
        [$bill] = $this->bill($user, $uuid);

        $bill = $payments->pay(
            $user,
            $bill,
            array_map(fn (array $tender) => array_map(fn ($value) => is_scalar($value) ? (string) $value : null, $tender), $data['tenders']),
            $data['approval_token'] ?? null,
            $data['shift_uuid'] ?? null,
            isset($data['version']) ? (int) $data['version'] : null,
            $this->terminal($user),
            $this->applied($request),
        );

        return $this->success([
            'bill' => $presenter->bill($bill),
            'order' => $presenter->order(Order::findOrFail($bill->order_id)),
        ], $bill->status === BillStatus::PAID ? "To'landi." : "To'lov qabul qilindi.");
    }

    /**
     * Ask again for a bill's receipt
     *
     * For a bill whose payments cover it but whose sale receipt is not issued ("fiskal kutmoqda": pending or failed):
     * asks the fiscal provider again (same receipt, never a second one; P3-35: a receipt the provider refused is sent
     * again too); issued: the bill is paid and the order closed when every bill is. Needs pos.take_payment.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{bill: ' . BillPresenter::BILL . ', order: ' . BillPresenter::ORDER . '}}')]
    public function fiscalRetry(Request $request, BillFiscalizer $fiscalizer, BillPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        [$bill] = $this->bill($user, $uuid);

        $bill = $fiscalizer->issue($bill, $user);

        return $this->success([
            'bill' => $presenter->bill($bill),
            'order' => $presenter->order(Order::findOrFail($bill->order_id)),
        ]);
    }

    /**
     * Close a paid order
     *
     * Closes the order when everything on it is on paid bills (receipts issued): delivered, paid, table free. Payments
     * close it by themselves; this is for a close that was interrupted, and asks again for receipts still pending
     * first. Calling it on a closed order returns it. Not settled yet: 409 `order_not_settled` (with what is left).
     * Needs pos.take_payment.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function close(Request $request, BillFiscalizer $fiscalizer, OrderCloser $closer, BillPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $order = $this->order($user, $uuid);

        if ($order->closed_at === null) {
            OrderBill::where('order_id', $order->id)->whereNotNull('shift_id')
                ->whereNotIn('status', [BillStatus::PAID->value, BillStatus::VOID->value])
                ->get()
                ->each(fn (OrderBill $bill) => $fiscalizer->issue($bill, $user));

            $closed = DB::transaction(function () use ($order, $closer, $user, $request) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($order->closed_at !== null) {
                    return true;
                }
                $closed = $closer->closeIfSettled($order, $user);
                if ($closed) {
                    $this->idempotency->markApplied($request);
                }

                return $closed;
            });

            if (!$closed) {
                $state = $presenter->order($order->refresh());
                throw PosActionException::conflict('order_not_settled', "Buyurtma hali to'liq to'lanmagan yoki fiskal chek kutilmoqda.", [
                    'unbilled_amount' => $state['unbilled_amount'],
                    'fiscal_pending' => $state['fiscal_pending'],
                ]);
            }
        }

        return $this->success($this->state($order->refresh(), $presenter, false), 'Buyurtma yopildi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Order $order, BillPresenter $presenter, bool $all): array
    {
        $bills = OrderBill::where('order_id', $order->id)
            ->when(!$all, fn ($query) => $query->where('status', '!=', BillStatus::VOID->value))
            ->orderBy('number')
            ->get();

        return [
            'order' => $presenter->order($order),
            'bills' => $bills->map(fn (OrderBill $bill) => $presenter->bill($bill, $order))->values()->all(),
            'refunds' => PosRefund::where('order_id', $order->id)->orderBy('id')->get()
                ->map(fn (PosRefund $refund) => $presenter->refund($refund))->values()->all(),
        ];
    }
}
