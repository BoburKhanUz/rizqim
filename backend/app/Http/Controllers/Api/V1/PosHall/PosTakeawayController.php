<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Takeaway\PackagingRules;
use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Enums\Permission;
use App\Support\RowId;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-34 on the till: a packaging line taken off (the guest brought their own bag) or put back, a takeaway's pickup
 * time (module scheduled_pickup) and the times the till may offer. orders.manage; the order's `version`; an
 * Idempotency-Key on every change (428 without).
 */
#[Group('POS: orders')]
class PosTakeawayController extends PosHallController
{
    /**
     * Take a packaging line off the order
     *
     * `reason` is required ("mijoz o'z paketini olib keldi"). The line stays on the order with `removed: true` and 0
     * so'm (charges.packaging_lines), the total goes down. 422 `packaging_not_found` (the order has no such line) or
     * `already_removed`; 409 `paid_bill_affected` once a bill of the order is being paid. Audited
     * (`pos.packaging_removed`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function removePackaging(Request $request, string $uuid, string $rule): JsonResponse
    {
        // Resolved here, not injected: Scramble reads an injected class as the request body
        $packaging = app(PackagingRules::class);
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'reason' => 'required|string|min:2|max:255',
        ], [], ['reason' => 'Sabab']);
        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $ruleId = RowId::parse($rule) ?? 0;

        return $this->change($request, fn () => $packaging->remove($order, (int) $data['version'], $ruleId, (string) $data['reason'], $user), 'Qadoq olib tashlandi.');
    }

    /**
     * Put a packaging line back
     *
     * 422 `not_removed`. Audited (`pos.packaging_restored`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function restorePackaging(Request $request, string $uuid, string $rule): JsonResponse
    {
        // Resolved here, not injected: Scramble reads an injected class as the request body
        $packaging = app(PackagingRules::class);
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate(['version' => 'required|integer|min:0']);
        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $ruleId = RowId::parse($rule) ?? 0;

        return $this->change($request, fn () => $packaging->restore($order, (int) $data['version'], $ruleId, $user), 'Qadoq qaytarildi.');
    }

    /**
     * Set the pickup time of a takeaway
     *
     * `pickup_at` (ISO time): at least the branch's lead time from now (422 `pickup_too_soon`, `data.earliest_at`),
     * inside the window (422 `pickup_too_far`) and the opening hours (422 `pickup_closed`; the 24/7 module: any hour).
     * The lines not sent yet wait and go to the kitchen at `pickup.kitchen_fire_at` (pickup_at − the prep time) by
     * themselves. `pickup_at: null` takes the time off: the kitchen gets the order now. 409 `already_in_kitchen` once
     * it was sent. Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosOrderController::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function setPickupTime(Request $request, string $uuid, ScheduledPickup $pickup): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'pickup_at' => 'present|nullable|date',
        ]);
        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        if ($order->channel !== \App\Enums\OrderChannel::POS_TAKEAWAY) {
            return $this->error("Olib ketish vaqti faqat olib ketish buyurtmasiga beriladi.", ['reason' => 'not_takeaway'], 422);
        }
        $at = $data['pickup_at'] !== null ? CarbonImmutable::parse((string) $data['pickup_at'])->utc() : null;

        return $this->change($request, fn () => $pickup->change($order, (int) $data['version'], $at, $user), $at !== null ? 'Olib ketish vaqti belgilandi.' : 'Buyurtma oshxonaga yuborildi.');
    }

    /**
     * Pickup times the till may offer
     *
     * Every `slot_minutes` from the earliest allowed time to the end of the window, inside the opening hours (24/7: all
     * day). `at` UTC, `time` the store's "HH:MM", `business_date` the day it belongs to.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{slots: list<' . ScheduledPickup::SLOT . '>}}')]
    public function slots(Request $request, ScheduledPickup $pickup): JsonResponse
    {
        $user = $this->staff($request);
        $branch = $this->branchFor($request, $user, Permission::ORDERS_MANAGE);

        return $this->success(['slots' => $pickup->slots($branch)]);
    }
}
