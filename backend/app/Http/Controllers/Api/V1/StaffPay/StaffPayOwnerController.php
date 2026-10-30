<?php

namespace App\Http\Controllers\Api\V1\StaffPay;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\Branch;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * P6-23: shared scoping of the staff pay owner endpoints (/owner/staff-pay/*): the caller's store, a branch-bound
 * caller their own branch; a record of another store is a 404, of a branch the caller may not act on a 403; the
 * record's branch must have the staff_pay module (403 module_disabled). Never from a terminal PIN session.
 */
abstract class StaffPayOwnerController extends ManagementController
{
    /**
     * The read permission: staff_pay.view, or staff_pay.manage for whoever only has that.
     */
    protected function readPermission(Request $request): Permission
    {
        return $this->permissions->allows($request->user(), Permission::STAFF_PAY_VIEW) ? Permission::STAFF_PAY_VIEW : Permission::STAFF_PAY_MANAGE;
    }

    /**
     * A record's branch, the caller's permission on it and the module.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return array{0: T, 1: Branch}
     */
    protected function record(Request $request, string $model, string $uuid, Permission $permission, string $notFound): array
    {
        $record = Str::isUuid($uuid) ? $model::query()->where('store_id', $this->storeId($request))->where('uuid', $uuid)->first() : null;
        $branch = $record !== null ? Branch::where('store_id', $this->storeId($request))->find($record->getAttribute('branch_id')) : null;
        if ($record === null || $branch === null) {
            throw new HttpResponseException($this->error($notFound, [], 404));
        }
        $this->authorizeFor($request, $permission, $branch);
        app(PosModules::class)->assertEnabled($branch, PosModule::STAFF_PAY);

        return [$record, $branch];
    }

    /**
     * The branch of the request (`branch_id`, or the caller's own), with the permission and the module.
     */
    protected function moduleBranch(Request $request, Permission $permission): Branch
    {
        $branch = $this->branch($request, $permission);
        app(PosModules::class)->assertEnabled($branch, PosModule::STAFF_PAY);

        return $branch;
    }

    protected function trimmed(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
