<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Setup\SetupPresenter;
use App\Enums\KitchenStationKind;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\KitchenStation;
use App\Services\Audit\AuditLogger;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * P3-13: the kitchen stations of a branch (osh/qozon, mangal, salat va bar, choy...), for the partner web panel
 * (settings.manage; never from a terminal PIN session). A branch-bound caller works on their own branch only.
 */
#[Group('Owner: kitchen stations', "Filial sexlari: osh, mangal, salat va bar, choy (P3-13); grill, fritur, ichimliklar, barista va yig'ish (P6-33). Marka va KDS shu sexlarga bo'linadi.")]
class KitchenStationController extends ManagementController
{
    /** P6-33: the shortest and the longest time target of a station (seconds). */
    public const MIN_TARGET = 10;

    public const MAX_TARGET = 7200;

    private const STATION = 'array{id: int, branch_id: int, name: string, kind: string, kind_label: string, sort_order: int, is_active: bool, show_on_kds: bool, target_seconds: int|null}';

    /**
     * The branch's kitchen stations
     *
     * By `branch_id` (a branch-bound caller: their own); without it, every branch of the store. In the order the
     * screens show them (`sort_order`, then oldest first). Inactive stations are listed too.
     */
    #[QueryParameter('branch_id', description: 'Only this branch.', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{kinds: list<array{value: string, label: string}>, stations: list<' . self::STATION . '>}}')]
    public function index(Request $request): JsonResponse
    {
        $branch = $this->branchFilter($request, Permission::SETTINGS_MANAGE);

        $stations = KitchenStation::query()
            ->where('store_id', $this->storeId($request))
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->orderBy('branch_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        return $this->success([
            'kinds' => array_map(fn (KitchenStationKind $kind) => ['value' => $kind->value, 'label' => $kind->label()], KitchenStationKind::cases()),
            'stations' => $stations->map(fn (KitchenStation $station) => SetupPresenter::station($station))->values(),
        ]);
    }

    /**
     * Add a kitchen station
     *
     * `kind`: osh, mangal, salad_bar, tea, kitchen (default) or other; P6-33: grill, fryer, drinks, barista, or expo
     * (the assembly screen: it gets no dishes). `name` is unique within the branch. `show_on_kds`: the station has a
     * KDS screen. The station gets dishes through the routes (PUT /owner/kitchen-station-routes). P6-33:
     * `target_seconds` (10–7200, null: none) is the time target of its lines (an expo station: the assembly time);
     * setting one needs the module `kds_expo` on the branch (403 module_disabled), clearing never does.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::STATION . '}')]
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        // P3-26: the whole body first (so the API document lists it), then the name's uniqueness in the branch
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['sometimes', 'string', Rule::enum(KitchenStationKind::class)],
            'target_seconds' => ['sometimes', 'nullable', 'integer', 'min:' . self::MIN_TARGET, 'max:' . self::MAX_TARGET],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_kds' => ['sometimes', 'boolean'],
        ], UzValidation::messages($this->messages()), $this->attributes());
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $request->validate([
            'name' => [Rule::unique('kitchen_stations', 'name')->where('branch_id', $branch->id)],
        ], UzValidation::messages($this->messages()), $this->attributes());

        $this->assertTargetAllowed($branch, $data);

        $station = KitchenStation::create(['store_id' => $branch->store_id, 'branch_id' => $branch->id] + collect($data)->except('branch_id')->all());
        $station->refresh();
        $audit->record('pos.station_created', $station, [], SetupPresenter::station($station));

        return $this->success(SetupPresenter::station($station), 'Sex qo\'shildi.', 201);
    }

    /**
     * Edit a kitchen station
     *
     * Any subset of `name`, `kind`, `sort_order`, `is_active`, `show_on_kds`, `target_seconds` (P6-33). A deactivated
     * station is not offered to terminals and its routes are ignored: its dishes go to the default ticket printer. A
     * station with dishes routed to it cannot become `expo`: 422 `station_has_routes`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATION . '}')]
    public function update(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $station = $this->stationOf($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('kitchen_stations', 'name')->where('branch_id', $station->branch_id)->ignore($station->id)],
            'kind' => ['sometimes', 'string', Rule::enum(KitchenStationKind::class)],
            'target_seconds' => ['sometimes', 'nullable', 'integer', 'min:' . self::MIN_TARGET, 'max:' . self::MAX_TARGET],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_kds' => ['sometimes', 'boolean'],
        ], UzValidation::messages($this->messages()), $this->attributes());

        $this->assertTargetAllowed($station->branch()->firstOrFail(), $data);
        if (($data['kind'] ?? null) === KitchenStationKind::EXPO->value && $station->kind !== KitchenStationKind::EXPO && $station->routes()->exists()) {
            return $this->error("Bu sexga taomlar biriktirilgan: yig'ish sexiga aylantirishdan oldin ularni boshqa sexga o'tkazing.", ['reason' => 'station_has_routes'], 422);
        }

        $before = SetupPresenter::station($station);
        $station->update($data);
        $station->refresh();
        $audit->record('pos.station_updated', $station, $before, SetupPresenter::station($station));

        return $this->success(SetupPresenter::station($station), 'Sex saqlandi.');
    }

    /**
     * Delete a kitchen station
     *
     * Its routes are removed and its printers keep working without a station. A station that order lines were
     * already sent to is kept for the history: 422 `station_in_use`, deactivate it instead.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, data: array{reason: string}}', description: "Sexga yuborilgan qatorlar bor (`station_in_use`)")]
    public function destroy(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $station = $this->stationOf($request, $id);

        if (Schema::hasColumn('order_items', 'station_id') && \Illuminate\Support\Facades\DB::table('order_items')->where('station_id', $station->id)->exists()) {
            return $this->error("Bu sexga buyurtma qatorlari yuborilgan: o'chirmang, faolsizlantiring.", ['reason' => 'station_in_use'], 422);
        }

        $before = SetupPresenter::station($station);
        $station->delete();
        $audit->record('pos.station_deleted', $station, $before, []);

        return $this->success(null, "Sex o'chirildi.");
    }

    /**
     * P6-33: a time target is the KDS v2 setup of the module `kds_expo` (403 module_disabled without it); clearing one
     * is always allowed.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertTargetAllowed(Branch $branch, array $data): void
    {
        if (($data['target_seconds'] ?? null) !== null) {
            app(PosModules::class)->assertEnabled($branch, PosModule::KDS_EXPO);
        }
    }

    private function stationOf(Request $request, int $id): KitchenStation
    {
        $station = KitchenStation::where('store_id', $this->storeId($request))->find($id);
        if (!$station instanceof KitchenStation) {
            throw new HttpResponseException($this->error('Sex topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $station);

        return $station;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.unique' => 'Bu filialda bunday nomli sex bor.',
            'kind.enum' => "Sex turi noto'g'ri.",
            'target_seconds.min' => 'Vaqt maqsadi kamida ' . self::MIN_TARGET . ' soniya bo\'lishi kerak.',
            'target_seconds.max' => 'Vaqt maqsadi ko\'pi bilan ' . self::MAX_TARGET . ' soniya bo\'lishi mumkin.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return ['name' => 'Nomi', 'kind' => 'Turi', 'sort_order' => 'Tartib', 'branch_id' => 'Filial', 'target_seconds' => 'Vaqt maqsadi'];
    }
}
