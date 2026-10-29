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
     * "chop etuvchi" kassa or null), `screen_lock_minutes` (0: never), `queue_numbers`, `cashier_discount_max_percent`
     * (P3-37: the discount percent a cashier gives without a manager; null or "0.00": always a manager), `alerts`
     * (`open_room_hours`, `void_amount`, `discount_percent`, `unaccepted_online_minutes`, `cash_out_amount`; null: off),
     * `holidays` (dates), `booking_slot_minutes` (default length of a booking, null: the server's),
     * `booking_confirmation` (P3-38: `auto_send`, `sms`, `cancel_policy`)); `device` (the
     * terminal: uuid, type, name, `is_printing_device`; null outside a terminal session); `user` (id, name, role,
     * permissions); `stations` (active kitchen stations); `station_routes` (dish to station: by product and by
     * category; a product route beats its category's); `printers` (active ones this terminal can use: LAN and
     * Bluetooth printers, and the USB or built-in printers attached to this very terminal); `staff` (id, name, role,
     * photo_url: to pick a waiter); `halls` (open halls and zones), `tables` (their active places; `layout` in grid
     * cells of the floor plan, 40 x 24), `shift` (the shift open on this terminal; null outside a terminal session or
     * with none open); P3-31: `offline`, what the till keeps for the limited mode (fees per hall and channel, today's room
     * fee and minimum spend per place, the choy-non items, its local number prefix and next number, the limits);
     * P3-26: `quick_items`, the one-tap buttons (products marked `quick_add` in the menu, then the
     * branch's per-guest "choy-non" items: `source` quick_add | auto_guest, `variant_uuid` to add as a line; prices and
     * availability from the POS catalog); P4-21: `entitlements`, the store's plan, status, features and devices (the
     * same as GET /owner/billing/entitlements) for the "Asosiy tarifda bor" locks and the unpaid-invoice notice;
     * P6-13: `queue`, order numbers (`numbers`, `calling`: the queue_numbers and queue_calling modules) and this till's
     * number blocks of today (`blocks` start/end/next, `next_number`; a till running low gets its next block here).
     * P6-16: `terminal_flow`, how the branch takes cards (`card_mode`), whether this till is a smart kassa, whether to
     * ask the bank terminal's day total at the Z (`ask_bank_total`, `difference_threshold`); null without the
     * terminal_card_flow module.
     * P6-11: `format`, the branch's format profile: `profile`, `profile_label`, `mode`, `first_screen` one of hall_map,
     * quick_counter, booking_calendar, online_orders; `modules`, the effective modules: the profile's with the owner's
     * switches, minus the law's locks and what the plan lacks; the apps show screens and buttons from this list only;
     * `locks.service_charge_zero` for a stolovaya.
     */
    #[QueryParameter('branch_id', description: 'The branch, for a caller who is not tied to one (the owner, a store-wide terminal).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{server_time: string, menu_version: string, store: array{id: int, name: string, timezone: string}, branch: array{id: int, name: string, address: string|null, phone: string|null, timezone: string, business_day_starts_at: string, business_date: string, pos_settings: array{mode: string, printing_device_id: string|null, screen_lock_minutes: int, queue_numbers: bool, cashier_discount_max_percent: string|null, alerts: array{open_room_hours: int|null, void_amount: string|null, discount_percent: string|null, unaccepted_online_minutes: int|null, cash_out_amount: string|null}, holidays: list<string>, booking_slot_minutes: int|null, booking_confirmation: array{auto_send: bool, sms: bool, cancel_policy: string|null}}}, device: array{uuid: string, type: string, name: string|null, is_printing_device: bool}|null, user: array{id: int, name: string, role: string, permissions: list<string>}, stations: list<array{id: int, branch_id: int, name: string, kind: string, kind_label: string, sort_order: int, is_active: bool, show_on_kds: bool}>, station_routes: array{categories: list<array{category_id: int, station_id: int}>, products: list<array{product_id: int, station_id: int}>}, printers: list<array{id: int, branch_id: int, name: string, purpose: string, connection: string, address: string|null, port: int|null, paper_width: int, station_id: int|null, device_id: string|null, is_active: bool, encoding: string|null, print_as_image: bool}>, staff: list<array{id: int, name: string, role: string, photo_url: string|null}>, halls: list<array{id: int, name: string, sort_order: int}>, tables: list<array{id: int, hall_id: int, name: string, kind: string, seats: int|null, layout: array{x: string|null, y: string|null, w: string|null, h: string|null}, sort_order: int, min_spend: string|null, min_spend_mode: string}>, shift: array{uuid: string, status: string, business_date: string, opened_at: string, opened_by: int|null, opening_cash: string, device_id: string}|null, quick_items: list<array{product_id: int, variant_id: int, variant_uuid: string, name: string, variant_name: string|null, source: \'quick_add\'|\'auto_guest\', hall_id: int|null, quantity_per_guest: string|null, sort_order: int}>, offline: array{device_prefix: string|null, next_local_number: int, business_date: string, day_type: string, fees: list<array{hall_id: int|null, channel: string, service_charge_percent: string, place_fee_amount: string, place_fee_mode: string}>, places: list<array{table_id: int, hall_id: int, room_fee: string, min_spend: string, min_spend_mode: string}>, guest_items: list<array{hall_id: int|null, variant_uuid: string, quantity_per_guest: string, sort_order: int}>, limits: array{batch_size: int, warn_after_minutes: int, alert_after_minutes: int, max_queued_orders: int, menu_max_age_hours: int, contact_max_age_hours: int, fiscal_warn_hours: list<int>, fiscal_limit_hours: int, clock_skew_warn_seconds: int}}, terminal_flow: ' . \App\Domain\Pos\TerminalFlow\TerminalFlowBootstrapSection::SECTION . '|null, format: ' . \App\Domain\Pos\Setup\PosFormatBootstrapSection::SECTION . ', entitlements: array{plan: array{code: string, name: string, tier: string|null, version: int}|null, legacy: bool, period: string|null, status: string, trial_ends_at: string|null, current_period: array{start: string, end: string}|null, term_ends_at: string|null, locked_since: string|null, lock_at: string|null, settings_locked: bool, features: array<string, bool>, required_plans: array<string, string|null>, devices: array<string, array<string, mixed>>, notice: array<string, mixed>|null}, queue: ' . PosQueueController::STATE . '}}')]
    public function show(Request $request, PosBootstrap $bootstrap): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);

        $branch = $this->branch($request, Permission::ORDERS_VIEW);
        $device = $this->permissions->terminalOf($request->user());

        return $this->success($bootstrap->build($request->user(), $branch, $device));
    }
}
