<?php

namespace App\Services\Store;

use App\Domain\Delivery\ZoneResolver;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Device;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * P2-12: a store's branches as the partner web panel edits them (the fields of the panel's branch form, plus the
 * open/closed status ZoneResolver reads). One daily window of working hours per branch, in the store's timezone,
 * read by ZoneResolver::isBranchOpen (docs/ZONES.md, "Filial"): no hours or equal hours = round the clock, closing
 * before opening = overnight. Changes are audited (branch.*).
 */
class BranchService
{
    public const HOURS_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    /** The audited and returned editable fields. */
    public const FIELDS = ['name', 'phone', 'address', 'region_id', 'district_id', 'latitude', 'longitude', 'radius_km', 'open_time', 'close_time', 'is_active', 'status'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ZoneResolver $zones,
    ) {}

    /**
     * The region must be active and the district active and in that region (the branch's stored region when the
     * request leaves it out). Runs after the request's own rules (BranchController).
     *
     * @param  array<string, mixed>  $data  validated
     * @param  array<string, mixed>  $messages
     */
    public function validateLocation(array $data, ?Branch $branch, array $messages): void
    {
        $regionId = array_key_exists('region_id', $data) ? (int) $data['region_id'] : ($branch?->region_id !== null ? (int) $branch->region_id : null);

        Validator::make($data, [
            'region_id' => ['sometimes', Rule::exists('regions', 'id')->where('is_active', true)],
            'district_id' => [
                'sometimes',
                Rule::exists('districts', 'id')->where('is_active', true)->when($regionId !== null, fn ($rule) => $rule->where('region_id', $regionId)),
            ],
        ], $messages, self::attributeNames())->validate();
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'name' => 'Filial nomi',
            'phone' => 'Telefon',
            'address' => 'Manzil',
            'region_id' => 'Viloyat',
            'district_id' => 'Tuman',
            'latitude' => 'Kenglik',
            'longitude' => 'Uzunlik',
            'radius_km' => 'Yetkazib berish radiusi',
            'open_time' => 'Ochilish vaqti',
            'close_time' => 'Yopilish vaqti',
            'is_active' => 'Faol',
            'status' => 'Holati',
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(Store $store, array $data): Branch
    {
        $branch = new Branch(['store_id' => $store->id, 'status' => BranchStatus::ACTIVE, 'is_active' => true, 'radius_km' => 5]);
        // P6-11: a new branch starts in the store's format (its first branch's profile and that profile's till mode)
        $first = Branch::query()->where('store_id', $store->id)->orderBy('id')->first(['id', 'pos_settings']);
        if ($first !== null) {
            $profile = PosSettings::format($first->pos_settings)['profile'];
            $branch->pos_settings = ['profile' => $profile->value, 'modules' => [], 'mode' => $profile->defaultMode()];
        }
        $branch->fill($this->attributes($data))->save();

        $this->audit->record('branch.created', $branch, [], $this->snapshot($branch->refresh()), [], ['branch_id' => (int) $branch->id]);

        return $branch;
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function update(Branch $branch, array $data): Branch
    {
        $before = $this->snapshot($branch);
        $branch->fill($this->attributes($data))->save();
        $after = $this->snapshot($branch->refresh());

        if ($before != $after) {
            $this->audit->record('branch.updated', $branch, $before, $after, [], ['branch_id' => (int) $branch->id]);
        }

        return $branch;
    }

    /**
     * Why the branch may not be deleted from the API, or null. Its orders keep the books and reports right (deleting
     * would cut them loose), and a store always keeps one branch: such a branch is made inactive instead. P2-33: and
     * nothing may still be tied to it (referencesRefusal).
     */
    public function deletionRefusal(Branch $branch): ?string
    {
        if (Order::withTrashed()->where('branch_id', $branch->id)->exists()) {
            return "Filialda buyurtmalar bor, uni o'chirib bo'lmaydi. O'rniga uni faol emas qiling.";
        }
        if (!Branch::where('store_id', $branch->store_id)->whereKeyNot($branch->id)->exists()) {
            return "Do'konning yagona filialini o'chirib bo'lmaydi.";
        }

        return $this->referencesRefusal($branch);
    }

    /**
     * P2-33: what is still tied to the branch. Deleting it would set their branch_id to NULL (the foreign keys are
     * nullOnDelete), and NULL means "the whole store": the branch's manager would see every branch's orders and staff,
     * its terminals would serve every branch and its zones would deliver from any branch. Staff accounts, terminals
     * and personal devices not revoked, and delivery zones count.
     *
     * @return array{staff: int, devices: int, zones: int}
     */
    public function references(Branch $branch): array
    {
        return [
            'staff' => User::where('branch_id', $branch->id)->count(),
            'devices' => Device::where('branch_id', $branch->id)->whereNull('revoked_at')->count(),
            'zones' => DeliveryZone::where('branch_id', $branch->id)->count(),
        ];
    }

    /**
     * Why the branch may not be deleted while staff, devices or zones are tied to it (with the counts), or null (the
     * branch API's delete).
     */
    public function referencesRefusal(Branch $branch): ?string
    {
        $counts = $this->references($branch);
        $parts = array_filter([
            $counts['staff'] > 0 ? "{$counts['staff']} ta xodim" : null,
            $counts['devices'] > 0 ? "{$counts['devices']} ta faol qurilma (terminal)" : null,
            $counts['zones'] > 0 ? "{$counts['zones']} ta yetkazib berish hududi" : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return "Filialni o'chirib bo'lmaydi: unga " . implode(', ', $parts) . " biriktirilgan. "
            . "Avval xodimlarni boshqa filialga o'tkazing, qurilmalarni bekor qiling va hududlarni boshqa filialga "
            . "biriktiring yoki o'chiring. O'chirilsa ular butun do'konga tegishli bo'lib qolardi.";
    }

    public function delete(Branch $branch): void
    {
        $snapshot = $this->snapshot($branch);
        $branch->delete();

        // The row is gone: the log keeps the store and the branch's last state (branch_id would point nowhere)
        $this->audit->record('branch.deleted', $branch, $snapshot, [], [], ['store_id' => (int) $branch->store_id, 'branch_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Branch $branch, ?Store $store = null, ?CarbonImmutable $now = null): array
    {
        $open = $this->hhmm($branch->open_time);
        $close = $this->hhmm($branch->close_time);
        $allDay = $open === null || $close === null || $open === $close;

        return [
            'id' => (int) $branch->id,
            'name' => (string) $branch->name,
            'phone' => $branch->phone,
            'address' => $branch->address,
            'region_id' => $branch->region_id !== null ? (int) $branch->region_id : null,
            'district_id' => $branch->district_id !== null ? (int) $branch->district_id : null,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'radius_km' => (float) $branch->radius_km,
            'status' => $branch->status->value,
            'is_active' => (bool) ($branch->is_active ?? true),
            'working_hours' => [
                'open' => $allDay ? null : $open,
                'close' => $allDay ? null : $close,
                'all_day' => $allDay,
                'overnight' => !$allDay && $close < $open,
                'timezone' => \App\Support\StoreTimezone::of($store),
            ],
            // What delivery checkout sees right now (ZoneResolver: status, is_active and the hours)
            'is_open_now' => $this->zones->isBranchOpen($branch, $now ?? CarbonImmutable::now(), $store),
            'created_at' => $branch->created_at?->toIso8601String(),
            'updated_at' => $branch->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::FIELDS));

        foreach (['open_time', 'close_time'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] === null ? null : substr((string) $data[$field], 0, 5) . ':00';
            }
        }
        if (array_key_exists('status', $data)) {
            $data['status'] = BranchStatus::from((string) $data['status']);
        }
        foreach (['region_id', 'district_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Branch $branch): array
    {
        $values = $branch->only(self::FIELDS);
        $values['status'] = $branch->status->value;
        $values['open_time'] = $this->hhmm($branch->open_time);
        $values['close_time'] = $this->hhmm($branch->close_time);

        return $values;
    }

    private function hhmm(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 5);
    }
}
