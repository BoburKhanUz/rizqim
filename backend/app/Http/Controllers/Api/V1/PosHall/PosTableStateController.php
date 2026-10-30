<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Hall\TableStates;
use App\Domain\Pos\Setup\PosModule;
use App\Enums\Permission;
use App\Models\DiningTable;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-26: the hostess marks a place of the floor map as waiting to be cleaned ("tozalanmoqda") or ready again.
 */
#[Group('POS: hostess', "Hostes: kutish ro'yxati, stol holati va stol broni (P6-24, P6-26).")]
class PosTableStateController extends PosHostessController
{
    /**
     * Mark a table's state
     *
     * `state`: `cleaning` (the guests left, the table is not ready) or `free` (ready: clears the mark). The mark shows
     * as `service_state` on the floor map while the place has no open order; opening an order there clears it by
     * itself, and it never blocks seating. Needs hostess.manage or tables.manage in the table's branch; the hostess
     * module (403 `module_disabled`). Announced as `table.state_changed` on the branch's channel.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, name: string, service_state: string|null, service_state_at: string|null}}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function update(Request $request, string $id, TableStates $states): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['state' => ['required', 'string', Rule::in([TableStates::CLEANING, 'free'])]]);

        $tableId = RowId::parse($id);
        $table = $tableId !== null ? DiningTable::where('store_id', $user->store_id)->find($tableId) : null;
        if ($table === null
            || !(($branch = $table->branch) && ($this->scope->allowsBranch($user, Permission::HOSTESS_MANAGE, $branch) || $this->scope->allowsBranch($user, Permission::TABLES_MANAGE, $branch)))) {
            throw new HttpResponseException($this->error('Stol topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        $this->requireModule($table->branch, PosModule::HOSTESS);

        return $this->atomically(
            $request,
            fn () => $states->set($table, $data['state'] === TableStates::CLEANING ? TableStates::CLEANING : null, $user),
            fn (DiningTable $table) => $this->success([
                'id' => (int) $table->id,
                'name' => (string) $table->name,
                'service_state' => $table->service_state,
                'service_state_at' => $table->service_state_at?->toJSON(),
            ], $table->service_state === null ? 'Stol tayyor.' : 'Stol tozalanmoqda.'),
        );
    }
}
