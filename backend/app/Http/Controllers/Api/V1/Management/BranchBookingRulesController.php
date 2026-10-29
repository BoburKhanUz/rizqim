<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Booking\BookingDepositMode;
use App\Domain\Pos\Booking\BookingRules;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-26: a branch's table booking rules for the partner web ("Stol broni sozlamalari"): slot by place kind, how soon
 * and how far ahead a booking may be made, an optional deposit. settings.manage; never from a terminal PIN session; a
 * branch-bound caller works on their own branch; the table_booking module (403 `module_disabled`, or
 * `plan_feature_missing`).
 */
#[Group('Owner: table booking', "Stol broni sozlamalari: stol turi bo'yicha slot, bron muddati, ixtiyoriy bo'nak va kutish ro'yxati ko'rinishi (P6-24, P6-26).")]
class BranchBookingRulesController extends ManagementController
{
    /**
     * The branch's table booking rules
     *
     * Every key with its default when nothing was saved (`configured: false`): `table_slot_minutes` (30-480, 120) and
     * `room_slot_minutes` / `tapchan_slot_minutes` (null: the branch's `booking_slot_minutes`, 5 hours); the least time
     * ahead a booking may be made per place kind (`*_min_advance_minutes` 0-1440, 0: no limit; the choyxona's "kamida
     * 3 soat" is 180 for rooms) and `max_advance_days` (null: no limit); the deposit: `deposit_mode` (a BookingDepositMode
     * value), `deposit_amount`, `deposit_from_guests` (a party of this many or more) and `deposit_required_to_confirm`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingRules::VIEW . '}')]
    public function show(Request $request, int $id, BookingRules $rules, PosModules $modules): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $id);
        $modules->assertEnabled($branch, PosModule::TABLE_BOOKING);

        return $this->success($rules->view($branch));
    }

    /**
     * Change the branch's table booking rules
     *
     * Send only what changes. The deposit applies to table bookings only (rooms and tapchans keep taking deposits by
     * hand) and is taken as the existing booking deposit with its advance receipt. With `deposit_required_to_confirm` a
     * booking stays a request (409 `deposit_required` on confirm) until the deposits taken add up to the amount.
     * Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BookingRules::VIEW . '}')]
    public function update(Request $request, int $id, BookingRules $rules, PosModules $modules): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $id);
        $modules->assertEnabled($branch, PosModule::TABLE_BOOKING);

        $data = $request->validate([
            'table_slot_minutes' => ['sometimes', 'integer', 'min:' . BookingRules::TABLE_SLOT_MIN, 'max:' . BookingRules::TABLE_SLOT_MAX],
            'room_slot_minutes' => ['sometimes', 'nullable', 'integer', 'min:' . BookingRules::TABLE_SLOT_MIN, 'max:1440'],
            'tapchan_slot_minutes' => ['sometimes', 'nullable', 'integer', 'min:' . BookingRules::TABLE_SLOT_MIN, 'max:1440'],
            'table_min_advance_minutes' => ['sometimes', 'integer', 'min:0', 'max:' . BookingRules::MAX_ADVANCE_MINUTES],
            'room_min_advance_minutes' => ['sometimes', 'integer', 'min:0', 'max:' . BookingRules::MAX_ADVANCE_MINUTES],
            'tapchan_min_advance_minutes' => ['sometimes', 'integer', 'min:0', 'max:' . BookingRules::MAX_ADVANCE_MINUTES],
            'max_advance_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:' . BookingRules::MAX_ADVANCE_DAYS],
            'deposit_mode' => ['sometimes', 'string', Rule::in(BookingDepositMode::values())],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0', 'max:' . BookingRules::MAX_DEPOSIT, 'decimal:0,2'],
            'deposit_from_guests' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'deposit_required_to_confirm' => ['sometimes', 'boolean'],
        ], UzValidation::messages(), [
            'table_slot_minutes' => 'Stol broni muddati (daqiqa)',
            'room_slot_minutes' => 'Xona broni muddati (daqiqa)',
            'tapchan_slot_minutes' => 'Tapchan broni muddati (daqiqa)',
            'table_min_advance_minutes' => 'Stol uchun eng kam oldindan vaqt (daqiqa)',
            'room_min_advance_minutes' => 'Xona uchun eng kam oldindan vaqt (daqiqa)',
            'tapchan_min_advance_minutes' => 'Tapchan uchun eng kam oldindan vaqt (daqiqa)',
            'max_advance_days' => 'Eng ko\'p oldindan kun',
            'deposit_mode' => "Bo'nak turi",
            'deposit_amount' => "Bo'nak summasi",
            'deposit_from_guests' => "Bo'nak boshlanadigan mehmonlar soni",
            'deposit_required_to_confirm' => "Bo'nak olinmaguncha tasdiqlamaslik",
        ]);

        return $this->success($rules->update($branch, $data, $request->user()), "Stol broni sozlamalari saqlandi.");
    }
}
