<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('POS: bootstrap', "Terminal ishga tushganda bir so'rovda kerak bo'lgan hamma narsa: filial, sozlamalar, sexlar, printerlar, xodimlar (P3-13).")]
class PosBootstrapController extends MenuStaffController
{
    /**
     * POS bootstrap
     *
     * A terminal's staff PIN session works its terminal's branch; a normal staff sign-in works the staff member's own
     * branch. An owner (or a terminal paired to the whole store) names the branch with `branch_id`; another store's
     * branch is 404, another branch than the caller's own 403 (orders.view).
     *
     * `data`: `server_time`; `menu_version` (refetch GET /catalog?channel=pos_* when it changes); `store` (id, name,
     * timezone); `branch` (id, name, address, phone, timezone, `business_day_starts_at` "HH:MM", `business_date`
     * (the business day it is now), `pos_settings`: `mode` counter|table|hybrid, `printing_device_id` (uuid of the
     * "chop etuvchi" kassa or null), `screen_lock_minutes` (0: never), `queue_numbers`, `alerts` (`open_room_hours`,
     * `void_amount`, `discount_percent`, `unaccepted_online_minutes`; null: off), `holidays` (dates), `booking_slot_minutes` (default length of a booking, null: the server's)); `device` (the
     * terminal: uuid, type, name, `is_printing_device`; null outside a terminal session); `user` (id, name, role,
     * permissions); `stations` (active kitchen stations); `station_routes` (dish to station: by product and by
     * category; a product route beats its category's); `printers` (active ones this terminal can use: LAN and
     * Bluetooth printers, and the USB or built-in printers attached to this very terminal); `staff` (id, name, role,
     * photo_url: to pick a waiter); `halls` (open halls and zones), `tables` (their active places; `layout` in grid
     * cells of the floor plan, 40 x 24), `shift` (the shift open on this terminal; null outside a terminal session or
     * with none open); P3-26: `quick_items`, the one-tap buttons (products marked `quick_add` in the menu, then the
     * branch's per-guest "choy-non" items: `source` quick_add | auto_guest, `variant_uuid` to add as a line; prices and
     * availability from the POS catalog).
     */
    #[QueryParameter('branch_id', description: 'The branch, for a caller who is not tied to one (the owner, a store-wide terminal).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{server_time: string, menu_version: string, store: array{id: int, name: string, timezone: string}, branch: array{id: int, name: string, address: string|null, phone: string|null, timezone: string, business_day_starts_at: string, business_date: string, pos_settings: array{mode: string, printing_device_id: string|null, screen_lock_minutes: int, queue_numbers: bool, alerts: array{open_room_hours: int|null, void_amount: string|null, discount_percent: string|null, unaccepted_online_minutes: int|null}, holidays: list<string>, booking_slot_minutes: int|null}}, device: array{uuid: string, type: string, name: string|null, is_printing_device: bool}|null, user: array{id: int, name: string, role: string, permissions: list<string>}, stations: list<array{id: int, branch_id: int, name: string, kind: string, kind_label: string, sort_order: int, is_active: bool, show_on_kds: bool}>, station_routes: array{categories: list<array{category_id: int, station_id: int}>, products: list<array{product_id: int, station_id: int}>}, printers: list<array{id: int, branch_id: int, name: string, purpose: string, connection: string, address: string|null, port: int|null, paper_width: int, station_id: int|null, device_id: string|null, is_active: bool, encoding: string|null, print_as_image: bool}>, staff: list<array{id: int, name: string, role: string, photo_url: string|null}>, halls: list<array{id: int, name: string, sort_order: int}>, tables: list<array{id: int, hall_id: int, name: string, kind: string, seats: int|null, layout: array{x: string|null, y: string|null, w: string|null, h: string|null}, sort_order: int, min_spend: string|null, min_spend_mode: string}>, shift: array{uuid: string, status: string, business_date: string, opened_at: string, opened_by: int|null, opening_cash: string, device_id: string}|null, quick_items: list<array{product_id: int, variant_id: int, variant_uuid: string, name: string, variant_name: string|null, source: \'quick_add\'|\'auto_guest\', hall_id: int|null, quantity_per_guest: string|null, sort_order: int}>}}')]
    public function show(Request $request, PosBootstrap $bootstrap): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);

        $branch = $this->branch($request, Permission::ORDERS_VIEW);
        $device = $this->permissions->terminalOf($request->user());

        return $this->success($bootstrap->build($request->user(), $branch, $device));
    }
}
