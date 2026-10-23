<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Hall\BusinessCalendar;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\DayType;
use App\Enums\DiningTableKind;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\RoomTariff;
use App\Models\RoomTariffCalendar;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P3-12: room fee tariffs ("xona puli", R3) and the branch's weekend days and holidays they are read with.
 * settings.manage; never from a terminal PIN session.
 */
#[Group('Owner: room tariffs', "Xona puli: xona yoki xona turi × kun turi (hafta ichi, dam olish, bayram) narxi; dam olish kunlari va bayramlar (P3-12).")]
class RoomTariffController extends ManagementController
{
    public const TARIFF = 'array{id: int, branch_id: int, dining_table_id: int|null, dining_table_name: string|null, hall_id: int|null, table_kind: string|null, day_type: string, price: string, valid_from: string|null, valid_to: string|null}';

    public const CALENDAR = 'array{branch_id: int, weekend_days: list<int>, holidays: list<string>}';

    /**
     * Room tariffs of a branch
     *
     * The tariffs and the branch's calendar (weekend days, ISO 1 = Monday, default Saturday and Sunday; holidays,
     * Y-m-d). The most specific tariff wins: the room's own, then its kind in its hall, then its kind in the branch;
     * a holiday without its own price is priced as a weekend, a weekend as a weekday. Without a tariff: no room fee.
     */
    #[QueryParameter('branch_id', description: "The branch; defaults to the caller's.", type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{calendar: ' . self::CALENDAR . ', tariffs: list<' . self::TARIFF . '>}}')]
    public function index(Request $request, BusinessCalendar $calendar): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $request->query('branch_id'));

        $tariffs = RoomTariff::with('diningTable:id,name')->where('branch_id', $branch->id)
            ->orderByRaw('dining_table_id IS NULL')->orderBy('dining_table_id')->orderBy('table_kind')->orderBy('day_type')->orderBy('id')
            ->get();

        return $this->success([
            'calendar' => $this->calendar($branch, $calendar),
            'tariffs' => $tariffs->map(fn (RoomTariff $tariff) => $this->present($tariff))->values()->all(),
        ]);
    }

    /**
     * Add a room tariff
     *
     * For one place (`dining_table_id`) or a kind of place (`table_kind`: room, tapchan, table; optionally only in
     * `hall_id`), a `day_type` (weekday, weekend, holiday) and a `price` (0 is a free room); `valid_from`/`valid_to`
     * (business dates, inclusive) limit it in time.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::TARIFF . '}')]
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $data['branch_id']);

        $tariff = new RoomTariff(['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
        $this->fill($tariff, $data, $branch);
        $tariff->save();
        $audit->record('pos.room_tariff_created', $tariff, [], [], $this->present($tariff), ['branch_id' => (int) $branch->id]);

        return $this->success($this->present($tariff->refresh()), "Tarif qo'shildi.", 201);
    }

    /**
     * Edit a room tariff
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::TARIFF . '}')]
    public function update(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $tariff = $this->tariff($request, $id);
        $data = $request->validate($this->rules(false));
        /** @var Branch $branch */
        $branch = $tariff->branch;

        $before = $this->present($tariff);
        $this->fill($tariff, $data, $branch);
        $tariff->save();
        $audit->record('pos.room_tariff_updated', $tariff, $before, $this->present($tariff), [], ['branch_id' => (int) $branch->id]);

        return $this->success($this->present($tariff->refresh()), 'Tarif saqlandi.');
    }

    /**
     * Delete a room tariff
     *
     * Open orders get the new price at their next change.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    public function destroy(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $tariff = $this->tariff($request, $id);
        $tariff->delete();
        $audit->record('pos.room_tariff_deleted', $tariff, [], [], $this->present($tariff), ['branch_id' => (int) $tariff->branch_id]);

        return $this->success(['id' => (int) $tariff->id], "Tarif o'chirildi.");
    }

    /**
     * Set the branch's weekend days and holidays
     *
     * `weekend_days`: ISO weekdays (1 = Monday … 7 = Sunday); `holidays`: dates (Y-m-d), at most 400. P3-26: the
     * holidays are the branch's one list, the same as `pos_settings.holidays` (PUT /owner/branches/{id}/pos-settings):
     * saving here replaces that list.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::CALENDAR . '}')]
    public function calendarUpdate(Request $request, AuditLogger $audit, BusinessCalendar $calendar): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer|min:1',
            'weekend_days' => 'present|array|max:7',
            'weekend_days.*' => 'integer|min:1|max:7|distinct',
            'holidays' => 'present|array|max:' . PosSettings::MAX_HOLIDAYS,
            'holidays.*' => 'date_format:Y-m-d|distinct',
        ], [
            'holidays.*.date_format' => "Bayram sanasi YYYY-MM-DD ko'rinishida bo'lishi kerak.",
        ]);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $data['branch_id']);

        $before = $this->calendar($branch, $calendar);
        DB::transaction(function () use ($branch, $data) {
            $weekend = array_values(array_unique(array_map('intval', $data['weekend_days'])));
            sort($weekend);
            RoomTariffCalendar::updateOrCreate(['branch_id' => $branch->id], ['store_id' => $branch->store_id, 'weekend_days' => $weekend, 'holidays' => null]);

            // The holidays live in the branch's POS settings (one list for the tariffs, the bootstrap and the panel)
            $locked = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $stored = $locked->getAttribute('pos_settings');
            $stored = is_string($stored) ? json_decode($stored, true) : $stored;
            $locked->forceFill(['pos_settings' => PosSettings::merge(is_array($stored) ? $stored : null, ['holidays' => $data['holidays']])])->save();
        });

        $after = $this->calendar($branch->refresh(), $calendar);
        $audit->record('pos.room_calendar_changed', $branch, $before, $after, [], ['branch_id' => (int) $branch->id]);

        return $this->success($after, 'Kalendar saqlandi.');
    }

    /**
     * @return array{branch_id: int, weekend_days: list<int>, holidays: list<string>}
     */
    private function calendar(Branch $branch, BusinessCalendar $calendar): array
    {
        $row = RoomTariffCalendar::where('branch_id', $branch->id)->first();

        return [
            'branch_id' => (int) $branch->id,
            'weekend_days' => $calendar->weekendDays($row),
            // P3-26: the branch's one holiday list (pos_settings.holidays)
            'holidays' => $calendar->holidays($branch, $row),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';

        return [
            'branch_id' => $creating ? 'required|integer|min:1' : 'prohibited',
            'dining_table_id' => 'nullable|integer|min:1',
            'hall_id' => 'nullable|integer|min:1',
            'table_kind' => ['nullable', 'string', Rule::in(DiningTableKind::values())],
            'day_type' => [$required, 'string', Rule::in(DayType::values())],
            'price' => "{$required}|numeric|min:0|max:100000000|decimal:0,2",
            'valid_from' => 'nullable|date_format:Y-m-d',
            'valid_to' => 'nullable|date_format:Y-m-d|after_or_equal:valid_from',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(RoomTariff $tariff, array $data, Branch $branch): void
    {
        foreach (['dining_table_id', 'hall_id', 'table_kind', 'valid_from', 'valid_to'] as $key) {
            if (array_key_exists($key, $data)) {
                $tariff->{$key} = $data[$key];
            }
        }
        if (isset($data['day_type'])) {
            $tariff->day_type = DayType::from($data['day_type']);
        }
        if (isset($data['price'])) {
            $tariff->price = Money::of($data['price'])->toString();
        }

        if ($tariff->dining_table_id !== null) {
            $table = DiningTable::where('branch_id', $branch->id)->find((int) $tariff->dining_table_id);
            if ($table === null) {
                throw ValidationException::withMessages(['dining_table_id' => 'Xona yoki stol bu filialda topilmadi.']);
            }
            // A place's own tariff: its kind and hall are the place's
            $tariff->table_kind = null;
            $tariff->hall_id = null;
        } elseif ($tariff->table_kind === null) {
            throw ValidationException::withMessages(['table_kind' => 'Xonani (dining_table_id) yoki xona turini (table_kind) tanlang.']);
        }

        if ($tariff->hall_id !== null && !Hall::where('branch_id', $branch->id)->whereKey((int) $tariff->hall_id)->exists()) {
            throw ValidationException::withMessages(['hall_id' => 'Zal bu filialda topilmadi.']);
        }
        if ($tariff->valid_from !== null && $tariff->valid_to !== null && $tariff->valid_from->greaterThan($tariff->valid_to)) {
            throw ValidationException::withMessages(['valid_to' => "Tugash sanasi boshlanishdan oldin bo'lishi mumkin emas."]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(RoomTariff $tariff): array
    {
        return [
            'id' => (int) $tariff->id,
            'branch_id' => (int) $tariff->branch_id,
            'dining_table_id' => $tariff->dining_table_id !== null ? (int) $tariff->dining_table_id : null,
            'dining_table_name' => $tariff->dining_table_id !== null ? $tariff->diningTable?->name : null,
            'hall_id' => $tariff->hall_id !== null ? (int) $tariff->hall_id : null,
            'table_kind' => $tariff->table_kind?->value,
            'day_type' => $tariff->day_type->value,
            'price' => (string) $tariff->price,
            'valid_from' => $tariff->valid_from?->format('Y-m-d'),
            'valid_to' => $tariff->valid_to?->format('Y-m-d'),
        ];
    }

    private function tariff(Request $request, string $id): RoomTariff
    {
        $tariffId = RowId::parse($id);
        $tariff = $tariffId !== null ? RoomTariff::where('store_id', $this->storeId($request))->find($tariffId) : null;
        if (!$tariff instanceof RoomTariff) {
            throw new HttpResponseException($this->error('Tarif topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $tariff);

        return $tariff;
    }
}
