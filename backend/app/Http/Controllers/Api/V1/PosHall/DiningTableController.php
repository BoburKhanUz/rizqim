<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Hall\HallLayout;
use App\Enums\DiningTableKind;
use App\Enums\MinSpendMode;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\DiningTable;
use App\Models\Hall;
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
 * P3-12: tables, rooms and tapchans of a branch for the web panel (R1, R4): seats, the spot on the floor map (one by
 * one, or the whole map at once), the minimum spend. tables.manage; never from a terminal PIN session.
 */
#[Group('Owner: halls and tables')]
class DiningTableController extends ManagementController
{
    public const TABLE = 'array{id: int, branch_id: int, hall_id: int, name: string, kind: string, seats: int|null, layout: array{x: string|null, y: string|null, w: string|null, h: string|null}, sort_order: int, is_active: bool, min_spend: string|null, min_spend_mode: string}';

    private const LAYOUT_RULES = [
        'layout' => 'nullable|array',
        'layout.x' => 'nullable|numeric|min:-100000|max:100000|decimal:0,2',
        'layout.y' => 'nullable|numeric|min:-100000|max:100000|decimal:0,2',
        'layout.w' => 'nullable|numeric|min:0|max:100000|decimal:0,2',
        'layout.h' => 'nullable|numeric|min:0|max:100000|decimal:0,2',
    ];

    /**
     * Tables, rooms and tapchans
     *
     * Of a branch (`branch_id`; without it every branch the caller works in), optionally of one hall, in floor order.
     */
    #[QueryParameter('branch_id', description: 'One branch; omit for every branch the caller works in.', type: 'int')]
    #[QueryParameter('hall_id', description: 'One hall.', type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::TABLE . '>}')]
    public function index(Request $request, HallLayout $layout): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1', 'hall_id' => 'nullable|integer|min:1']);
        $branch = $this->branchFilter($request, Permission::TABLES_MANAGE);

        $tables = DiningTable::where('store_id', $this->storeId($request))
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch?->id))
            ->when($request->query('hall_id'), fn ($query, $hallId) => $query->where('hall_id', (int) $hallId))
            ->orderBy('branch_id')->orderBy('hall_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        return $this->success($tables->map(fn (DiningTable $table) => $layout->table($table))->values()->all());
    }

    /**
     * Add a table, room or tapchan
     *
     * In a hall (its branch is the hall's). `kind`: table, room or tapchan. `min_spend` (optional): the least the party
     * spends on food, per room (`per_table`) or per guest (`per_person`); the difference is a separate bill line.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::TABLE . '}')]
    public function store(Request $request, AuditLogger $audit, HallLayout $layout): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $hall = $this->hall($request, (int) $data['hall_id']);
        $this->assertUniqueName((int) $hall->branch_id, trim($data['name']), null);

        $table = new DiningTable(['store_id' => $hall->store_id, 'branch_id' => $hall->branch_id, 'hall_id' => $hall->id]);
        $this->fill($table, $data);
        $table->save();
        $audit->record('pos.table_created', $table, [], [], ['name' => $table->name, 'kind' => $table->kind->value]);

        return $this->success($layout->table($table->refresh()), "Stol qo'shildi.", 201);
    }

    /**
     * Edit a table, room or tapchan
     *
     * Any field; `hall_id` moves it to another hall of the same branch.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::TABLE . '}')]
    public function update(Request $request, string $id, AuditLogger $audit, HallLayout $layout): JsonResponse
    {
        $table = $this->table($request, $id);
        $data = $request->validate($this->rules(false));

        if (isset($data['hall_id']) && (int) $data['hall_id'] !== (int) $table->hall_id) {
            $hall = $this->hall($request, (int) $data['hall_id']);
            if ((int) $hall->branch_id !== (int) $table->branch_id) {
                throw ValidationException::withMessages(['hall_id' => 'Zal boshqa filialda.']);
            }
        }
        if (isset($data['name'])) {
            $this->assertUniqueName((int) $table->branch_id, trim($data['name']), (int) $table->id);
        }

        $before = $layout->table($table);
        $this->fill($table, $data);
        $table->save();
        $audit->record('pos.table_updated', $table, $before, $layout->table($table));

        return $this->success($layout->table($table->refresh()), 'Stol saqlandi.');
    }

    /**
     * Delete a table, room or tapchan
     *
     * 422 `table_in_use` while an order is open at it. Past orders keep its name.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    public function destroy(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $table = $this->table($request, $id);
        if ($table->openOrders()->exists()) {
            return $this->error("Stolda ochiq buyurtma bor: avval uni yoping yoki ko'chiring.", ['reason' => 'table_in_use'], 422);
        }

        $table->delete();
        $audit->record('pos.table_deleted', $table, [], [], ['name' => $table->name]);

        return $this->success(['id' => (int) $table->id], "Stol o'chirildi.");
    }

    /**
     * Save the floor map
     *
     * The places of one branch at once (the map editor's "Save"): each `id` with its `layout` {x, y, w, h}, and
     * optionally `hall_id` (a hall of the same branch) and `sort_order`. All or nothing: an id of another branch is a
     * 422 and nothing is saved.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::TABLE . '>}')]
    public function layout(Request $request, AuditLogger $audit, HallLayout $layout): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer|min:1',
            'tables' => 'required|array|min:1|max:500',
            'tables.*.id' => 'required|integer|min:1|distinct',
            'tables.*.hall_id' => 'nullable|integer|min:1',
            'tables.*.sort_order' => 'nullable|integer|min:0|max:100000',
            'tables.*.layout' => 'required|array',
            'tables.*.layout.x' => 'nullable|numeric|min:-100000|max:100000|decimal:0,2',
            'tables.*.layout.y' => 'nullable|numeric|min:-100000|max:100000|decimal:0,2',
            'tables.*.layout.w' => 'nullable|numeric|min:0|max:100000|decimal:0,2',
            'tables.*.layout.h' => 'nullable|numeric|min:0|max:100000|decimal:0,2',
        ]);
        $branch = $this->branch($request, Permission::TABLES_MANAGE, $data['branch_id']);

        $tables = DiningTable::where('branch_id', $branch->id)->whereIn('id', array_column($data['tables'], 'id'))->get()->keyBy('id');
        $halls = Hall::where('branch_id', $branch->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($data['tables'] as $index => $row) {
            if (!$tables->has((int) $row['id'])) {
                throw ValidationException::withMessages(["tables.{$index}.id" => 'Stol bu filialda topilmadi.']);
            }
            if (isset($row['hall_id']) && !in_array((int) $row['hall_id'], $halls, true)) {
                throw ValidationException::withMessages(["tables.{$index}.hall_id" => 'Zal bu filialda topilmadi.']);
            }
        }

        DB::transaction(function () use ($data, $tables, $branch, $audit) {
            foreach ($data['tables'] as $row) {
                /** @var DiningTable $table */
                $table = $tables->get((int) $row['id']);
                $table->fill(array_filter([
                    'hall_id' => isset($row['hall_id']) ? (int) $row['hall_id'] : null,
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
                ], fn ($value) => $value !== null) + $this->layoutColumns($row['layout']))->save();
            }

            $audit->record('pos.layout_saved', $branch, [], [], ['tables' => count($data['tables'])], ['branch_id' => (int) $branch->id]);
        });

        $saved = DiningTable::where('branch_id', $branch->id)->orderBy('hall_id')->orderBy('sort_order')->orderBy('id')->get();

        return $this->success($saved->map(fn (DiningTable $table) => $layout->table($table))->values()->all(), 'Zal xaritasi saqlandi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';

        return [
            'hall_id' => "{$required}|integer|min:1",
            'name' => "{$required}|string|min:1|max:50",
            'kind' => [$creating ? 'nullable' : 'sometimes', 'string', Rule::in(DiningTableKind::values())],
            'seats' => 'nullable|integer|min:1|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
            'min_spend' => 'nullable|numeric|min:0|max:100000000|decimal:0,2',
            'min_spend_mode' => ['nullable', 'string', Rule::in(MinSpendMode::values())],
        ] + self::LAYOUT_RULES;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(DiningTable $table, array $data): void
    {
        foreach (['hall_id', 'seats', 'sort_order'] as $key) {
            if (array_key_exists($key, $data)) {
                $table->{$key} = $data[$key] !== null ? (int) $data[$key] : ($key === 'sort_order' ? 0 : null);
            }
        }
        if (isset($data['name'])) {
            $table->name = trim($data['name']);
        }
        if (isset($data['kind'])) {
            $table->kind = DiningTableKind::from($data['kind']);
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $table->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('min_spend', $data)) {
            $table->min_spend = $data['min_spend'] !== null ? Money::of($data['min_spend'])->toString() : null;
        }
        if (isset($data['min_spend_mode'])) {
            $table->min_spend_mode = MinSpendMode::from($data['min_spend_mode']);
        }
        if (array_key_exists('layout', $data) && is_array($data['layout'])) {
            $table->fill($this->layoutColumns($data['layout']));
        }
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, string|null>
     */
    private function layoutColumns(array $layout): array
    {
        $columns = [];
        foreach (['x', 'y', 'w', 'h'] as $key) {
            if (array_key_exists($key, $layout)) {
                $columns["layout_{$key}"] = $layout[$key] !== null ? Money::of($layout[$key])->toString() : null;
            }
        }

        return $columns;
    }

    private function hall(Request $request, int $id): Hall
    {
        $hall = Hall::where('store_id', $this->storeId($request))->find($id);
        if (!$hall instanceof Hall) {
            throw ValidationException::withMessages(['hall_id' => 'Zal topilmadi.']);
        }
        $this->authorizeFor($request, Permission::TABLES_MANAGE, $hall);

        return $hall;
    }

    private function table(Request $request, string $id): DiningTable
    {
        $tableId = RowId::parse($id);
        $table = $tableId !== null ? DiningTable::where('store_id', $this->storeId($request))->find($tableId) : null;
        if (!$table instanceof DiningTable) {
            throw new HttpResponseException($this->error('Stol topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::TABLES_MANAGE, $table);

        return $table;
    }

    private function assertUniqueName(int $branchId, string $name, ?int $exceptId): void
    {
        $taken = DiningTable::where('branch_id', $branchId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Filialda shu nomli stol yoki xona bor.']);
        }
    }
}
