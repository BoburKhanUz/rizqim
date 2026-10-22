<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\SetupPresenter;
use App\Enums\KitchenStationKind;
use App\Enums\Permission;
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
#[Group('Owner: kitchen stations', "Filial sexlari: osh, mangal, salat va bar, choy (P3-13). Marka va KDS shu sexlarga bo'linadi.")]
class KitchenStationController extends ManagementController
{
    private const STATION = 'array{id: int, branch_id: int, name: string, kind: string, kind_label: string, sort_order: int, is_active: bool, show_on_kds: bool}';

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
     * `kind`: osh, mangal, salad_bar, tea, kitchen (default) or other. `name` is unique within the branch.
     * `show_on_kds`: the station has a KDS screen. The station gets dishes through the routes
     * (PUT /owner/kitchen-station-routes).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::STATION . '}')]
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'name' => ['required', 'string', 'max:100', Rule::unique('kitchen_stations', 'name')->where('branch_id', $branch->id)],
            'kind' => ['sometimes', 'string', 'in:osh,mangal,salad_bar,tea,kitchen,other'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_kds' => ['sometimes', 'boolean'],
        ], UzValidation::messages($this->messages()), $this->attributes());

        $station = KitchenStation::create(['store_id' => $branch->store_id, 'branch_id' => $branch->id] + collect($data)->except('branch_id')->all());
        $station->refresh();
        $audit->record('pos.station_created', $station, [], SetupPresenter::station($station));

        return $this->success(SetupPresenter::station($station), 'Sex qo\'shildi.', 201);
    }

    /**
     * Edit a kitchen station
     *
     * Any subset of `name`, `kind`, `sort_order`, `is_active`, `show_on_kds`. A deactivated station is not offered
     * to terminals and its routes are ignored: its dishes go to the default ticket printer.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATION . '}')]
    public function update(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $station = $this->stationOf($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('kitchen_stations', 'name')->where('branch_id', $station->branch_id)->ignore($station->id)],
            'kind' => ['sometimes', 'string', 'in:osh,mangal,salad_bar,tea,kitchen,other'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_kds' => ['sometimes', 'boolean'],
        ], UzValidation::messages($this->messages()), $this->attributes());

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
            'kind.in' => "Sex turi noto'g'ri.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return ['name' => 'Nomi', 'kind' => 'Turi', 'sort_order' => 'Tartib', 'branch_id' => 'Filial'];
    }
}
