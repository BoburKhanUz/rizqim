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
    private const ORDER = 'array{uuid: string, short_code: string|null, status: string, channel: string, version: int, opened_at: string|null, open_minutes: int, overdue: bool, bill_printed: bool, guests_count: int|null, waiter: array{id: int, name: string|null}|null, total_price: string, items_count: int, unsent_count: int}';

    private const TABLE = 'array{id: int, name: string, kind: string, seats: int|null, layout: array{x: string|null, y: string|null, w: string|null, h: string|null}, is_active: bool, min_spend: string|null, min_spend_mode: string, status: string, reservation: array{booking_uuid: string, starts_at: string, guests: int|null}|null, orders: list<' . self::ORDER . '>}';

    /**
     * Floor map of a branch
     *
     * Halls in order with their tables, rooms and tapchans, each with a `status`: `free`, `reserved_soon` (a booking
     * starts within the hour), `occupied`, `overdue` (open longer than the branch's alert, 6 hours by default) or
     * `bill_printed`, and the open orders on it (opened_at, guests, waiter, total, unsent lines). Closed seasonal halls
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

        return $this->success($floor->forBranch($this->branchFor($request, $user, Permission::ORDERS_VIEW)));
    }
}
