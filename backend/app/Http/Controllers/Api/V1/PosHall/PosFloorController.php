<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Hall\FloorState;
use App\Enums\Permission;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('POS: orders')]
class PosFloorController extends PosHallController
{
    private const ORDER = 'array{uuid: string, short_code: string|null, status: string, channel: string, version: int, opened_at: string|null, open_minutes: int, overdue: bool, bill_printed: bool, bill_requested: bool, bill_requested_at: string|null, guests_count: int|null, waiter: array{id: int, name: string|null}|null, total_price: string, items_count: int, unsent_count: int, ready_count: int}';

    private const TABLE = 'array{id: int, name: string, kind: string, seats: int|null, layout: array{x: string|null, y: string|null, w: string|null, h: string|null}, is_active: bool, min_spend: string|null, min_spend_mode: string, status: string, bill_requested: bool, service_state: string|null, reservation: array{booking_uuid: string, starts_at: string, guests: int|null, contact_name: string|null, contact_phone: string|null}|null, orders: list<' . self::ORDER . '>}';

    /**
     * Floor map of a branch
     *
     * Halls in order with their tables, rooms and tapchans, each with a `status`: `free`, `reserved_soon` (a booking
     * starts within the hour), `occupied`, `overdue` (open longer than the branch's alert, 6 hours by default) or
     * `bill_printed`, and the open orders on it (opened_at, guests, waiter, total, `unsent_count`: lines not sent to the
     * kitchen yet, `ready_count`: lines the kitchen marked ready and nobody served yet, `bill_requested`: a waiter asked
     * for the bill, "Schyot so'raldi"; the place's own `bill_requested` while no bill is printed yet). A place's
     * `service_state` (P6-26): the hostess' mark, `cleaning` while a free place waits to be cleaned (a separate key,
     * `status` is unchanged). `reservation` names the guest (`contact_name`, `contact_phone`) for staff with orders.manage or bookings.manage
     * (null for others, e.g. a kitchen screen). `layout`: grid cells of the floor plan (40 columns x 24 rows, x/y the
     * top-left cell, w/h the size in cells; null: not placed yet). Closed seasonal halls
     * and inactive places are left out unless an order is still open there. `untabled_orders`: open POS orders without
     * a place. Refetch on `order.*` events of `pos-branch.{id}`. Needs orders.view; `branch_id` defaults to the
     * caller's (a terminal's) branch.
     */
    #[QueryParameter('branch_id', description: "The branch; defaults to the caller's or the terminal's branch.", type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch_id: int, generated_at: string, overdue_after_hours: int, halls: list<array{id: int, name: string, sort_order: int, is_active: bool, tables: list<' . self::TABLE . '>}>, untabled_orders: list<' . self::ORDER . '>}}')]
    public function show(Request $request, FloorState $floor): JsonResponse
    {
        $user = $this->staff($request);
        $request->validate(['branch_id' => 'nullable|integer|min:1']);

        $branch = $this->branchFor($request, $user, Permission::ORDERS_VIEW);
        // P3-26: the guest's name and phone of a reservation for staff who seat or serve them
        $withContact = $this->permissions->allows($user, Permission::ORDERS_MANAGE, $branch) || $this->permissions->allows($user, Permission::BOOKINGS_MANAGE, $branch);

        return $this->success($floor->forBranch($branch, null, $withContact));
    }
}
