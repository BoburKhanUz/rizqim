<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Billing\ServiceChargeRules;
use App\Domain\Pos\Billing\ServiceOrderType;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Enums\PlaceFeeMode;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\ServiceFeeSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Order\DiningFeeCalculator;
use App\Support\Money;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P6-22 (C1): the service charge variants of a branch for the partner web panel (docs/API.md "Xizmat haqi
 * qoidalari"): the percent by order type, the large-group rule, the restaurant cover per guest and the bill's
 * notice, on the branch's (or one hall's) service fee row (App\Domain\Pos\Billing\ServiceChargeRules). The base
 * percent and place fee stay on PUT /owner/service-fees. settings.manage; never from a terminal PIN session; a
 * branch-bound caller works on their own branch only. Changing them needs the `service_charge_rules` module.
 */
#[Group('Owner: service charge rules', "Xizmat haqi variantlari: buyurtma turi bo'yicha foiz, katta guruh qoidasi, kuver va chekdagi e'lon (P6-22).")]
class ServiceChargeRulesController extends ManagementController
{
    private const ROW = 'array{branch_id: int|null, hall_id: int|null, hall_name: string|null, service_charge_percent: string, service_charge_channels: list<string>|null, place_fee_amount: string, place_fee_mode: string, rules: ' . ServiceChargeRules::SHAPE . ', notice: string|null, notice_preview: string|null}';

    private const VIEW = 'array{branch_id: int, enabled: bool, locked: bool, order_types: list<array{key: string, label: string}>, rows: list<' . self::ROW . '>}';

    /**
     * The branch's service charge rules
     *
     * The rows in force in the branch: the store's default (`branch_id` null), the branch's own and its halls' own,
     * each with its base percent, its `rules` (`by_order_type` {hall|table_service|takeaway: percent},
     * `large_group_min_guests` with `large_group_percent`, `cover_amount` per guest), the owner's `notice` and
     * `notice_preview` (the text the bill prints). `enabled`: the branch has the `service_charge_rules` module;
     * `locked`: a stolovaya (0%, VM 75).
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::VIEW . '}')]
    public function show(Request $request, PosModules $modules): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success($this->view($branch, $modules));
    }

    /**
     * Set the service charge rules
     *
     * For the branch (or, with `hall_id`, one of its halls). Send the whole set: `by_order_type` ({hall,
     * table_service, takeaway}: percent 0-30; a type left out keeps the row's own percent), `large_group_min_guests`
     * (2-500) with `large_group_percent` (0-30) — both or neither, `cover_amount` (per guest, 0 or null: none),
     * `notice` (up to 300 characters; null: worked out). A row the branch or hall did not have yet is made from the
     * one in force there (the base percent and place fee unchanged). A stolovaya takes no positive percent (422
     * service_charge_locked); without the `service_charge_rules` module: 403 module_disabled. Audited
     * (settings.service_charge_rules_changed).
     */
    #[BodyParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[BodyParameter('hall_id', description: "One hall's own row.", type: 'int', required: false)]
    #[BodyParameter('by_order_type', description: 'Percent by order type: {hall, table_service, takeaway}.', type: 'array<string, string>', required: false, example: ['hall' => '15', 'table_service' => '10', 'takeaway' => '0'])]
    #[BodyParameter('large_group_min_guests', description: 'Guests from which the large-group percent applies.', type: 'int', required: false, example: 8)]
    #[BodyParameter('large_group_percent', description: 'The large-group percent.', type: 'string', required: false, example: '18')]
    #[BodyParameter('cover_amount', description: 'The cover per guest (so\'m).', type: 'string', required: false, example: '5000')]
    #[BodyParameter('notice', description: 'The notice on the bill (null: worked out from the rules).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::VIEW . '}')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, code: string, data: array{reason: string, module: string, branch_id: int}}')]
    public function update(Request $request, PosModules $modules, DiningFeeCalculator $fees, AuditLogger $audit, Entitlements $entitlements): JsonResponse
    {
        $percent = 'nullable|numeric|min:0|max:' . ServiceChargeRules::MAX_PERCENT . '|decimal:0,2';
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'hall_id' => 'nullable|integer|min:1|max:999999999999999999',
            'by_order_type' => 'nullable|array',
            'by_order_type.*' => $percent,
            'large_group_min_guests' => 'nullable|integer|min:2|max:' . ServiceChargeRules::MAX_GUESTS . '|required_with:large_group_percent',
            'large_group_percent' => $percent . '|required_with:large_group_min_guests',
            'cover_amount' => 'nullable|numeric|min:0|max:100000000|decimal:0,2',
            'notice' => 'nullable|string|max:300',
        ]);
        $unknown = array_diff(array_map('strval', array_keys((array) ($data['by_order_type'] ?? []))), ServiceOrderType::values());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['by_order_type' => "Noma'lum buyurtma turi: " . implode(', ', $unknown) . '.']);
        }

        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $storeId = (int) $branch->store_id;
        $entitlements->assertSettingsUnlocked($storeId);
        $modules->assertEnabled($branch, PosModule::SERVICE_CHARGE_RULES);

        $hallId = isset($data['hall_id']) ? (int) $data['hall_id'] : null;
        if ($hallId !== null && !Hall::where('store_id', $storeId)->where('branch_id', $branch->id)->whereKey($hallId)->exists()) {
            throw ValidationException::withMessages(['hall_id' => 'Zal topilmadi.']);
        }

        $rules = ServiceChargeRules::fromArray([
            'by_order_type' => array_filter((array) ($data['by_order_type'] ?? []), fn ($value) => $value !== null),
            'large_group_min_guests' => $data['large_group_min_guests'] ?? null,
            'large_group_percent' => $data['large_group_percent'] ?? null,
            'cover_amount' => $data['cover_amount'] ?? null,
        ]);
        if ($rules->chargesAny() && $modules->profile($branch)->locksServiceCharge()) {
            return response()->json([
                'success' => false,
                'message' => "Stolovayada xizmat haqi qonun bo'yicha olinmaydi: foizlar 0 bo'lishi kerak.",
                'code' => 'service_charge_locked',
                'data' => ['reason' => 'service_charge_locked', 'module' => PosModule::SERVICE_CHARGE->value, 'branch_id' => (int) $branch->id],
            ], 422);
        }

        $row = ServiceFeeSetting::where('store_id', $storeId)
            ->when($hallId !== null, fn ($query) => $query->where('hall_id', $hallId), fn ($query) => $query->whereNull('hall_id')->where('branch_id', $branch->id))
            ->first();
        if ($row === null) {
            // A new row for the scope, from the one in force there (raw, not the stolovaya's 0% copy)
            $source = ServiceFeeSetting::where('store_id', $storeId)->whereNull('hall_id')
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                ->orderByRaw('branch_id IS NULL')->first();
            $row = new ServiceFeeSetting([
                'store_id' => $storeId,
                'branch_id' => $branch->id,
                'hall_id' => $hallId,
                'service_charge_percent' => $source !== null ? (string) $source->service_charge_percent : '0.00',
                'service_charge_channels' => $source?->service_charge_channels,
                'place_fee_amount' => $source !== null ? (string) $source->place_fee_amount : '0.00',
                'place_fee_mode' => $source !== null ? $source->place_fee_mode : PlaceFeeMode::PER_PERSON,
                'place_fee_channels' => $source?->place_fee_channels,
            ]);
        }

        $before = $row->exists ? ['rules' => ServiceChargeRules::of($row)->toArray(), 'notice' => $row->service_charge_notice] : [];
        $notice = trim((string) ($data['notice'] ?? ''));
        $row->forceFill([
            'service_charge_rules' => $rules->isEmpty() ? null : $rules->toArray(),
            'service_charge_notice' => $notice !== '' ? $notice : null,
        ])->save();

        $audit->record('settings.service_charge_rules_changed', $row, $before, ['rules' => $rules->toArray(), 'notice' => $row->service_charge_notice], array_filter(['hall_id' => $hallId]), ['store_id' => $storeId, 'branch_id' => (int) $branch->id]);

        return $this->success($this->view($branch, $modules), 'Xizmat haqi qoidalari saqlandi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function view(Branch $branch, PosModules $modules): array
    {
        $locked = $modules->profile($branch)->locksServiceCharge();
        $rows = ServiceFeeSetting::where('store_id', $branch->store_id)
            ->where(fn ($query) => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->where(fn ($query) => $query->whereNull('hall_id')->orWhereIn('hall_id', Hall::where('branch_id', $branch->id)->select('id')))
            ->orderByRaw('COALESCE(branch_id, 0)')->orderByRaw('COALESCE(hall_id, 0)')->get();
        $halls = Hall::whereIn('id', $rows->pluck('hall_id')->filter())->pluck('name', 'id');

        return [
            'branch_id' => (int) $branch->id,
            'enabled' => $modules->enabled($branch, PosModule::SERVICE_CHARGE_RULES),
            'locked' => $locked,
            'order_types' => array_map(fn (ServiceOrderType $type) => ['key' => $type->value, 'label' => $type->label()], ServiceOrderType::cases()),
            'rows' => $rows->map(function (ServiceFeeSetting $row) use ($halls, $locked) {
                $rules = ServiceChargeRules::of($row);

                return [
                    'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                    'hall_id' => $row->hall_id !== null ? (int) $row->hall_id : null,
                    'hall_name' => $row->hall_id !== null ? ($halls[$row->hall_id] ?? null) : null,
                    'service_charge_percent' => Money::of((string) $row->service_charge_percent)->toString(),
                    'service_charge_channels' => $row->service_charge_channels,
                    'place_fee_amount' => Money::of((string) $row->place_fee_amount)->toString(),
                    'place_fee_mode' => $row->place_fee_mode->value,
                    'rules' => $rules->toArray(),
                    'notice' => $row->service_charge_notice,
                    'notice_preview' => $rules->notice($locked ? self::zeroed($row) : $row, $locked),
                ];
            })->values()->all(),
        ];
    }

    private static function zeroed(ServiceFeeSetting $row): ServiceFeeSetting
    {
        $copy = clone $row;
        $copy->setAttribute('service_charge_percent', '0.00');

        return $copy;
    }
}
