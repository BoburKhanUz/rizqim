<?php

namespace App\Http\Middleware;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * P6-11: `pos.module:<key>` (App\Domain\Pos\Setup\PosModule value; several keys: all of them): the branch the request
 * works on has the module (PosModules::assertEnabled), else 403 plan_feature_missing (the plan lacks it) or 403
 * module_disabled (the format switched it off). Only on setup and new-flow routes: never on payments, closing bills,
 * receipts, shifts or refunds.
 *
 * The branch (review P6 S#2: a caller never picks the branch the gate looks at):
 *  - a caller that acts in one branch — a paired terminal's own token, a branch-bound member, a staff PIN session —
 *    is gated on that branch, always; naming another one (the route's `branch`, `branch_id` of the query or body)
 *    is 403 `branch_forbidden`; a PIN session whose terminal is gone acts nowhere (403 `branch_forbidden`);
 *  - a store-wide caller naming a branch is gated on it: a `branch_id` that is not a positive id is 422, a branch of
 *    another store (or none) 404;
 *  - a store-wide caller naming none (an owner's store-wide request, a record addressed by its id): the module is on
 *    when it is on for at least one branch of the store; the controller checks the record's own branch where it
 *    matters.
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

    /**
     * The branch to gate on; null for a store-wide caller naming none.
     *
     * @throws HttpResponseException 403 branch_forbidden, 404
     * @throws ValidationException 422 on a malformed branch_id
     */
    private function branchOf(Request $request, User|Device $caller, int $storeId): ?Branch
    {
        $named = $this->namedBranchId($request);
        $acting = $caller instanceof Device
            ? ($caller->branch_id !== null ? (int) $caller->branch_id : null)
            : $this->permissions->actingBranchId($caller);

        if ($acting !== null) {
            if ($acting === PermissionService::NO_BRANCH || ($named !== null && $named !== $acting)) {
                throw new HttpResponseException(self::refusal(
                    $acting === PermissionService::NO_BRANCH ? "Bu terminal hech qaysi filialga ulanmagan." : "Siz faqat o'z filialingizda ishlaysiz.",
                    403,
                    'branch_forbidden',
                ));
            }
            $named = $acting;
        }
        if ($named === null) {
            return null;
        }

        $branch = Branch::query()->where('store_id', $storeId)->whereKey($named)->first();
        if ($branch === null) {
            throw new HttpResponseException(self::refusal('Filial topilmadi.', 404, 'branch_not_found'));
        }

        return $branch;
    }

    /**
     * The branch the request names: the route's `branch` (a bound model or its id), else `branch_id` of the query or
     * body; null when it names none (absent, null or empty).
     *
     * @throws ValidationException when it is not a positive id
     * @throws HttpResponseException 404 for a bound branch of another store
     */
    private function namedBranchId(Request $request): ?int
    {
        $route = $request->route('branch');
        if ($route instanceof Branch) {
            return (int) $route->id;
        }

        $raw = $route ?? $request->input('branch_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = is_int($raw) || is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT) : false;
        if ($id === false || $id <= 0) {
            throw ValidationException::withMessages(['branch_id' => "Filial noto'g'ri."]);
        }

        return $id;
    }

    private static function refusal(string $message, int $status, string $reason): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => ['reason' => $reason]], $status);
    }
}
