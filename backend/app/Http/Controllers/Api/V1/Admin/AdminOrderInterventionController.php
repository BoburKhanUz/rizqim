<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Http\Requests\Admin\AdminRules;
use App\Models\Order;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Orders\AdminOrderException;
use App\Services\Admin\Orders\AdminOrderPresenter;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderInterventionService;
use App\Services\Admin\Orders\OrderSlaSettings;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-14: interventions on a marketplace order (admin.orders.intervene, a step-up and a `reason` each; every one is
 * audited with the order's status before and after). Status changes go through the order lifecycle
 * (OrderStatusService): the same rules, side effects and notifications as a store's own move.
 */
#[Group('Admin')]
class AdminOrderInterventionController extends AdminController
{
    public function __construct(
        private readonly OrderInterventionService $interventions,
        private readonly AdminOrderQuery $orders,
        private readonly AdminOrderPresenter $presenter,
        private readonly OrderSlaSettings $sla,
    ) {}

    /**
     * Cancel an order
     *
     * `cancel_reason`: one of the store-side reasons (customer_request, no_show — a pickup order already accepted only,
     * out_of_stock, store_closed, courier_unavailable, other); `note` (optional, the customer sees it); `reason` (the
     * team's own, in the audit log and the status history). Paid money follows the cancel rules: keshbek goes back to
     * the balance, Click/Payme (or collected cash) is flagged for a manual refund — ask for it with a refund request.
     * The store hears about it in its order chats. Answers the order card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Cannot be cancelled now (illegal, stale) or the reason does not apply (reason)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', Rule::in(CancelReason::staffValues())],
            'note' => 'nullable|string|max:500',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $order = $this->find($uuid);
        if ($order === null) {
            return $this->notFound();
        }

        $before = $this->state($order);
        try {
            $result = $this->interventions->cancel($order, $this->member($request), CancelReason::from($data['cancel_reason']), $data['note'] ?? null, $data['reason']);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $this->audited($request, $result->order, $before, ['manual_refund_required' => $result->manualRefundRequired]);

        return $this->success($this->card($request, $result->order), $result->manualRefundRequired
            ? "Buyurtma bekor qilindi. To'lov mijozga qaytarilishi kerak: qaytarish so'rovini yarating."
            : 'Buyurtma bekor qilindi.');
    }

    /**
     * Fix an order's status
     *
     * `status`: a status the lifecycle allows from the current one (the card's `actions.transitions`), never canceled
     * or rejected (use cancel) nor refunded (use a refund request). There is no forced move: anything else is 422
     * `illegal`. `courier_id` (optional) for a move to a courier: one of the store's couriers; without it the least
     * busy one is picked, as for the store. `reason` is required. Answers the order card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Not allowed from the current status (illegal, stale, use_cancel, use_refund_request) or no courier (courier)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function status(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'courier_id' => 'nullable|integer|min:1|max:999999999999999999',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $order = $this->find($uuid);
        if ($order === null) {
            return $this->notFound();
        }

        $before = $this->state($order);
        try {
            $result = $this->interventions->fixStatus($order, $this->member($request), OrderStatus::from($data['status']), $data['reason'], isset($data['courier_id']) ? (int) $data['courier_id'] : null);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $this->audited($request, $result->order, $before, $result->changed ? [] : ['unchanged' => true]);

        return $this->success($this->card($request, $result->order), $result->changed ? 'Holat tuzatildi.' : "Buyurtma allaqachon shu holatda.");
    }

    /**
     * Message the store or the customer
     *
     * `to`: `store` (the store's order chats: its admin chat and the managers who get order cards) or `customer` (the
     * RIZQIM bot); `text` (1–1000 characters, sent as plain text); `reason`. Queued like every Telegram message. 422
     * `no_recipient` when there is nobody to send it to; 503 `telegram_not_configured` without the bot.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{recipients: int}}')]
    #[ApiDocResponse(422, 'Nobody to send it to (no_recipient)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function message(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', Rule::in(['store', 'customer'])],
            'text' => 'required|string|min:1|max:1000',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $order = $this->find($uuid);
        if ($order === null) {
            return $this->notFound();
        }

        try {
            $sent = $this->interventions->message($order, $this->member($request), $data['to'], $data['text']);
        } catch (AdminOrderException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)->meta([
            'to' => $data['to'],
            'recipients' => $sent['recipients'],
            'text' => mb_substr(trim($data['text']), 0, 500),
            'short_code' => $order->short_code,
        ]);

        return $this->success($sent, $data['to'] === 'store' ? 'Xabar ovqatxonaga yuborildi.' : 'Xabar mijozga yuborildi.');
    }

    /**
     * @return array{status: string, courier_id: int|null}
     */
    private function state(Order $order): array
    {
        return ['status' => $order->status->value, 'courier_id' => $order->courier_id !== null ? (int) $order->courier_id : null];
    }

    /**
     * @param  array{status: string, courier_id: int|null}  $before
     * @param  array<string, mixed>  $meta
     */
    private function audited(Request $request, Order $order, array $before, array $meta = []): void
    {
        $after = ['status' => $order->status->value, 'courier_id' => $order->courier_id !== null ? (int) $order->courier_id : null];
        if ($order->status->isCancellation()) {
            $after['cancel_reason'] = $order->cancel_reason?->value;
        }

        AdminAudit::for($request)->subject($order)->store((int) $order->store_id)->changes($before, $after)
            ->meta(['short_code' => $order->short_code, ...$meta]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Request $request, Order $order): array
    {
        return $this->presenter->card($order->fresh() ?? $order, $this->member($request), $this->sla->limits());
    }

    private function find(string $uuid): ?Order
    {
        return $this->orders->base()->where('orders.uuid', $uuid)->first();
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Buyurtma topilmadi.', 'not_found', 404);
    }
}
