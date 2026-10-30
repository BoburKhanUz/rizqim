<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Orders\OrderHandoverService;
use App\Domain\Pos\Setup\PosModule;
use App\Enums\Permission;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-24: handing an open table to another waiter and numbering a line's guest (docs/API.md "Ofitsiantni almashtirish
 * va mehmon bo'yicha bo'lish"). Same scoping, idempotency and version rules as the other order changes.
 */
#[Group('POS: orders', "Kassa va ofitsiant: zal buyurtmasi, bosqichlar, oshxonaga yuborish, o'chirish, ko'chirish, birlashtirish, xona puli va xizmat haqi (P3-12).")]
class PosOrderHandoverController extends PosHostessController
{
    /**
     * Hand the table to another waiter
     *
     * The open order goes to `waiter_id` (an active waiter of the order's branch; 404 `waiter_not_found`, 422
     * `same_waiter`). Needs orders.transfer (a manager or the owner by default; it is enough on its own, without
     * orders.manage), or orders.manage with a manager's one-time `approval_token` for orders.transfer (POST
     * /pos/approvals; 403 `approval_required`). The waiter rule is not
     * asked: the manager decides. `reason` (optional, up to 255 characters) is kept in the audit log. The old and the
     * new waiter are told (broadcast `order.transferred` on their own channels), the floor refetches. Nothing already
     * paid changes; what the order earns from now on (a personal tip, sales for the waiter's pay) follows the new
     * waiter. The waiter_transfer module (403 `module_disabled`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function transfer(Request $request, string $uuid, OrderHandoverService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'waiter_id' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'approval_token' => 'nullable|string|max:255',
        ]);

        // Review S9: the route lets orders.transfer or orders.manage in, so the order is found (scoped to the user's
        // branch) with either; orders.manage alone then needs the manager's approval in the service
        try {
            $order = $this->order($user, $uuid, Permission::ORDERS_TRANSFER);
        } catch (HttpResponseException) {
            $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        }
        $this->requireModule($order->branch_id, PosModule::WAITER_TRANSFER);

        return $this->change($request, fn () => $orders->transfer($order, (int) $data['version'], $user, (int) $data['waiter_id'], $data['reason'] ?? null, $data['approval_token'] ?? null), 'Stol boshqa ofitsiantga berildi.');
    }

    /**
     * Set a line's guest number
     *
     * `seat_no` 1-500 puts the line on that guest for the split by guest (POST /pos/orders/{uuid}/bills, mode
     * `by_guest`); null takes it off every guest (it is shared). 422 `already_void`; 404 `item_not_found`. Needs
     * orders.manage; the waiter rule applies (`approval_token`). The guest_split module (403 `module_disabled`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function seat(Request $request, string $uuid, string $item, OrderHandoverService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'seat_no' => 'present|nullable|integer|min:1|max:500',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $this->requireModule($order->branch_id, PosModule::GUEST_SPLIT);
        $itemId = RowId::parse($item);
        if ($itemId === null) {
            return $this->error('Taom topilmadi.', ['reason' => 'item_not_found'], 404);
        }

        return $this->change($request, fn () => $orders->setSeat($order, (int) $data['version'], $user, $itemId, $data['seat_no'] !== null ? (int) $data['seat_no'] : null, $data['approval_token'] ?? null), 'Mehmon raqami saqlandi.');
    }
}
