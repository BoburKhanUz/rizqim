<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\PosSettings;
use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use App\Support\StoreTimezone;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P3-13: the branch's POS settings for the partner web panel: the business day start and `pos_settings` (mode,
 * printing till, screen lock, queue numbers, alert thresholds, holidays). settings.manage; never from a terminal PIN
 * session; a branch-bound caller works on their own branch only.
 */
#[Group('Owner: POS settings', "Filialning POS sozlamalari: rejim, biznes kun boshlanishi, chop etuvchi kassa, ekran qulfi, ogohlantirish chegaralari (P3-13).")]
class BranchPosSettingsController extends ManagementController
{
    private const SETTINGS = 'array{branch_id: int, timezone: string, business_day_starts_at: string, pos_settings: array{mode: string, printing_device_id: string|null, screen_lock_minutes: int, queue_numbers: bool, cashier_discount_max_percent: string|null, alerts: array{open_room_hours: int|null, void_amount: string|null, discount_percent: string|null, unaccepted_online_minutes: int|null, cash_out_amount: string|null}, holidays: list<string>, booking_slot_minutes: int|null}}';

    /**
     * The branch's POS settings
     *
     * `business_day_starts_at`: "HH:MM", the local time (`timezone` of the store) the business day starts, 05:00 unless
     * changed. `pos_settings`: every key, stored values over the defaults: `mode` counter|table|hybrid,
     * `printing_device_id` (uuid of the "chop etuvchi" kassa), `screen_lock_minutes` (0: never), `queue_numbers`,
     * `cashier_discount_max_percent` (P3-37: the discount percent a cashier gives alone, "10.00" by default; null or
     * "0.00": always a manager's PIN), `alerts` (`open_room_hours`, `void_amount`, `discount_percent`,
     * `unaccepted_online_minutes`, `cash_out_amount` — one cash out of the drawer of at least this, "500000.00" by
     * default; null: off), `holidays` (YYYY-MM-DD), `booking_slot_minutes` (null: the server default).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SETTINGS . '}')]
    public function show(Request $request, int $id): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $id);

        return $this->success($this->present($branch));
    }

    /**
     * Change the branch's POS settings
     *
     * Send only what changes. `business_day_starts_at`: "HH:MM" from 00:00 to 11:59 (an early opener may set 04:00).
     * `pos_settings`: `mode`; `printing_device_id` (uuid of a POS terminal of this branch, or null for none);
     * `screen_lock_minutes` 0-240; `queue_numbers`; `cashier_discount_max_percent` 0-100 (2 decimals) or null;
     * `alerts` merges key by key (`open_room_hours` 1-72, `void_amount` 0-100000000, `discount_percent` 0-100,
     * `unaccepted_online_minutes` 1-120, `cash_out_amount` 0-100000000; null switches that alert off); `holidays` replaces the whole list (up to 400 dates); `booking_slot_minutes` 30-1440 (how long a booking without an end
     * time holds its room; null: the server default). An unknown key is 422.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SETTINGS . '}')]
    public function update(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $id);

        $data = $request->validate([
            'business_day_starts_at' => ['sometimes', 'required', 'string', 'regex:/^(0\d|1[01]):[0-5]\d$/'],
            'pos_settings' => ['sometimes', 'array'],
            'pos_settings.mode' => ['sometimes', 'string', 'in:counter,table,hybrid'],
            'pos_settings.printing_device_id' => ['sometimes', 'nullable', 'uuid'],
            'pos_settings.screen_lock_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'pos_settings.queue_numbers' => ['sometimes', 'boolean'],
            'pos_settings.cashier_discount_max_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'pos_settings.alerts' => ['sometimes', 'array'],
            'pos_settings.alerts.open_room_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:72'],
            'pos_settings.alerts.void_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'pos_settings.alerts.discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'pos_settings.alerts.unaccepted_online_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'pos_settings.alerts.cash_out_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'pos_settings.booking_slot_minutes' => ['sometimes', 'nullable', 'integer', 'min:30', 'max:1440'],
            'pos_settings.holidays' => ['sometimes', 'array', 'max:' . PosSettings::MAX_HOLIDAYS],
            'pos_settings.holidays.*' => ['date_format:Y-m-d'],
        ], UzValidation::messages([
            'business_day_starts_at.regex' => "Biznes kun boshlanishi SS:DD ko'rinishida, 00:00 dan 11:59 gacha bo'lishi kerak (masalan 05:00).",
            'pos_settings.mode.in' => "Rejim counter, table yoki hybrid bo'lishi kerak.",
            'pos_settings.holidays.*.date_format' => "Bayram sanasi YYYY-MM-DD ko'rinishida bo'lishi kerak.",
        ]), [
            'business_day_starts_at' => 'Biznes kun boshlanishi',
            'pos_settings.mode' => 'Rejim',
            'pos_settings.printing_device_id' => 'Chop etuvchi kassa',
            'pos_settings.screen_lock_minutes' => 'Ekran qulfi (daqiqa)',
            'pos_settings.queue_numbers' => 'Navbat raqami',
            'pos_settings.cashier_discount_max_percent' => 'Kassir chegirmasi chegarasi (%)',
            'pos_settings.alerts.open_room_hours' => 'Ochiq xona (soat)',
            'pos_settings.alerts.void_amount' => "O'chirilgan summa",
            'pos_settings.alerts.discount_percent' => 'Chegirma (%)',
            'pos_settings.alerts.unaccepted_online_minutes' => 'Qabul qilinmagan onlayn buyurtma (daqiqa)',
            'pos_settings.alerts.cash_out_amount' => 'Kassadan chiqim summasi',
            'pos_settings.booking_slot_minutes' => 'Bron muddati (daqiqa)',
            'pos_settings.holidays' => 'Bayram kunlari',
        ]);

        $changes = $data['pos_settings'] ?? [];
        $this->rejectUnknownKeys($request->input('pos_settings'), $changes);

        if (($changes['printing_device_id'] ?? null) !== null) {
            $this->assertPrintingDevice($branch, (string) $changes['printing_device_id']);
        }

        $before = $this->present($branch);
        $attributes = [];
        if (isset($data['business_day_starts_at'])) {
            $attributes['business_day_starts_at'] = $data['business_day_starts_at'] . ':00';
        }
        if ($changes !== []) {
            $attributes['pos_settings'] = PosSettings::merge($branch->pos_settings, $changes);
        }
        if ($attributes !== []) {
            $branch->update($attributes);
            $branch->refresh();
        }

        $after = $this->present($branch);
        $audit->record('settings.pos_settings_changed', $branch, $before, $after, [], ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $this->success($after, 'POS sozlamalari saqlandi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Branch $branch): array
    {
        $store = $this->currentStoreOf($branch);

        return [
            'branch_id' => (int) $branch->id,
            'timezone' => StoreTimezone::of($store),
            'business_day_starts_at' => PosSettings::timeOf($branch->business_day_starts_at),
            'pos_settings' => PosSettings::resolve($branch->pos_settings),
        ];
    }

    private function currentStoreOf(Branch $branch): ?\App\Models\Store
    {
        return \App\Models\Store::find($branch->store_id);
    }

    /**
     * Keys the validator does not know are rejected instead of stored.
     *
     * @param  array<string, mixed>  $changes
     */
    private function rejectUnknownKeys(mixed $input, array $changes): void
    {
        if (!is_array($input)) {
            return;
        }

        $errors = [];
        foreach (array_diff(array_keys($input), PosSettings::KEYS) as $key) {
            $errors['pos_settings.' . $key] = "Noma'lum sozlama: {$key}.";
        }
        if (is_array($input['alerts'] ?? null)) {
            foreach (array_diff(array_keys($input['alerts']), PosSettings::ALERT_KEYS) as $key) {
                $errors['pos_settings.alerts.' . $key] = "Noma'lum ogohlantirish: {$key}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The "chop etuvchi" kassa is a POS terminal of the store that works this branch (its own, or paired to the store).
     */
    private function assertPrintingDevice(Branch $branch, string $uuid): void
    {
        $exists = Device::query()
            ->where('uuid', $uuid)
            ->where('store_id', $branch->store_id)
            ->where('type', DeviceType::POS->value)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages(['pos_settings.printing_device_id' => "Kassa terminali topilmadi (u shu filialniki bo'lishi kerak)."]);
        }
    }
}
