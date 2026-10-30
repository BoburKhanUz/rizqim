<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Orders\PosCourses;
use App\Domain\Pos\Orders\PosOrderService;
use App\Domain\Pos\Orders\PosWeights;
use App\Enums\Permission;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-21 / P6-25 (wave 2 "Restoran va kafe", docs/API.md "Kurslar, og'irlik, stolga olib kelish"): send a held course
 * to the kitchen, enter the real weight of a "weight later" line, set the table plate of a counter order. Like every
 * POS change: Idempotency-Key, the order's `version`, the waiter rule, audited, announced (order.items_changed).
 * Business rules: PosOrderService, PosCourses, PosWeights.
 */
#[Group('POS: orders', "Kassa va ofitsiant: zal buyurtmasi, bosqichlar, oshxonaga yuborish, o'chirish, ko'chirish, birlashtirish, xona puli va xizmat haqi (P3-12).")]
class PosCourseController extends PosHallController
{
    /**
     * Send a course to the kitchen
     *
     * P6-21: "N-kursni yuborish" — the held lines of course `course` (1–9) go to their stations (`sent_at`); lines
     * added to that course later go with the next ordinary send. 422 `nothing_to_send` when the course has nothing
     * left to send. Needs orders.manage; the waiter rule. Not gated by the `courses` module: an order that has courses
     * always sends them.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function send(Request $request, string $uuid, string $course, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $courseNo = ctype_digit($course) ? (int) $course : 0;
        if ($courseNo < 1 || $courseNo > PosCourses::MAX_COURSE) {
            return $this->error('Kurs raqami 1 dan ' . PosCourses::MAX_COURSE . ' gacha.', ['reason' => 'course_invalid'], 422);
        }
        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);

        return $this->change($request, fn () => $orders->sendCourse($order, (int) $data['version'], $user, $courseNo, $data['approval_token'] ?? null), "{$courseNo}-kurs oshxonaga yuborildi.");
    }

    /**
     * Enter the weight of a line
     *
     * P6-25: the real weight (`quantity`, kg or l, up to 3 decimals) of a line added with `awaiting_weight`: the line's
     * price follows it at its own unit price, `weighed_by` / `weighed_at` are set, the charges follow, and the order can
     * be billed again (no bill while a line awaits its weight: 409 `weight_pending`). A line weighed already: weighing
     * it again needs orders.void_item or a manager's `approval_token` (403 `approval_required`). A line that does not
     * wait for a weight: 422 `not_weighed_line`. Needs orders.manage; the waiter rule. Not gated by the module.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function weight(Request $request, string $uuid, string $item, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'quantity' => 'required|numeric|gt:0|max:' . PosWeights::MAX_QUANTITY,
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $itemId = RowId::parse($item);
        if ($itemId === null) {
            return $this->error('Taom topilmadi.', ['reason' => 'item_not_found'], 404);
        }
        $weight = PosWeights::parseWeight($data['quantity']);

        return $this->change($request, fn () => $orders->setWeight($order, (int) $data['version'], $user, $itemId, $weight, $data['approval_token'] ?? null), "Og'irlik kiritildi.");
    }

    /**
     * Set the table plate
     *
     * P6-25 (kafe "stolga olib kelish", module `table_service`): the number on the plate the guest took (`table_label`,
     * up to 20 characters; null or empty clears it), shown on the kitchen ticket and the order. Audited. Needs
     * orders.manage; the waiter rule.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function tableLabel(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'table_label' => 'present|nullable|string|max:20',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $label = isset($data['table_label']) && trim((string) $data['table_label']) !== '' ? trim((string) $data['table_label']) : null;

        return $this->change($request, fn () => $orders->setTableLabel($order, (int) $data['version'], $user, $label, $data['approval_token'] ?? null), $label !== null ? 'Stol raqami yozildi.' : "Stol raqami olib tashlandi.");
    }
}
