<?php

namespace App\Http\Middleware;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P6-11: `pos.module:<key>` (App\Domain\Pos\Setup\PosModule value; several keys: all of them): the branch the request
 * works on has the module (PosModules::assertEnabled), else 403 plan_feature_missing (the plan lacks it) or 403
 * module_disabled (the format switched it off). Only on setup and new-flow routes: never on payments, closing bills,
 * receipts, shifts or refunds.
 *
 * The branch: the route's `branch` parameter, else `branch_id` of the request (query or body) when it is a branch of
 * the caller's store, else the terminal's (a paired terminal's own token) or the caller's acting branch (a branch-bound
 * member, a staff PIN session). With none (an owner's store-wide request, a record addressed by its id): the module is
 * on when it is on for at least one branch of the store; the controller checks the record's own branch where it matters.
 */
class EnsurePosModule
{
    public function __construct(
        private readonly PosModules $modules,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        $caller = $request->user();
        $storeId = match (true) {
            $caller instanceof User, $caller instanceof Device => $caller->store_id !== null ? (int) $caller->store_id : null,
            default => null,
        };
        if ($storeId === null) {
            return $next($request); // no store: the route's own auth answers
        }

        $branch = $this->branchOf($request, $caller, $storeId);
        foreach ($keys as $key) {
            $module = PosModule::from($key);
            if ($branch !== null) {
                $this->modules->assertEnabled($branch, $module);
            } else {
                $this->modules->assertEnabledInStore($storeId, $module);
            }
        }

        return $next($request);
    }

    private function branchOf(Request $request, User|Device $caller, int $storeId): ?Branch
    {
        $id = $request->route('branch') ?? $request->input('branch_id');
        if ($id instanceof Branch) {
            return (int) $id->store_id === $storeId ? $id : null;
        }

        if ($id === null || $id === '' || filter_var($id, FILTER_VALIDATE_INT) === false) {
            $id = $caller instanceof Device ? $caller->branch_id : $this->permissions->actingBranchId($caller);
        }
        if ($id === null || (int) $id <= 0) {
            return null;
        }

        return Branch::query()->where('store_id', $storeId)->whereKey((int) $id)->first();
    }
}
