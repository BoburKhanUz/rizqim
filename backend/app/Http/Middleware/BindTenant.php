<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `tenant` (M4 stage 2, docs/scale/M4_DESIGN.md 3.2): on the tenant route groups only, after
 * authentication, binds TenantContext to the caller's store for the rest of the request: a member of staff (also in a
 * PIN session on a terminal, whose store PermissionService checks is the user's) -> `users.store_id`; a paired
 * terminal's own token (auth:device) -> `devices.store_id`.
 *
 * A caller without a store (a customer, a RIZQIM team token, a terminal of no store) is not bound and goes on exactly
 * as before: the route's own checks (role/permission middleware, the controller's store checks) answer it as they did.
 */
class BindTenant
{
    public function __construct(private readonly TenantContext $tenant)
    {
    }

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $storeId = self::storeOf($request->user());
        if ($storeId === null) {
            return $next($request);
        }

        // runAs puts back what was bound before once the response is built, also when it throws
        return $this->tenant->runAs($storeId, fn () => $next($request));
    }

    public static function storeOf(mixed $actor): ?int
    {
        return match (true) {
            $actor instanceof User => $actor->store_id !== null && in_array($actor->role, UserRole::storeRoles(), true) ? (int) $actor->store_id : null,
            $actor instanceof Device => $actor->store_id !== null ? (int) $actor->store_id : null,
            default => null,
        };
    }
}
