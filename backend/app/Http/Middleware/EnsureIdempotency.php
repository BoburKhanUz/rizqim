<?php

namespace App\Http\Middleware;

use App\Services\Idempotency\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the Idempotency-Key header (App\Services\Idempotency\IdempotencyService). Usage, after the auth middleware:
 * ->middleware(EnsureIdempotency::class . ':orders.store'); the parameter is the scope keys are unique in (defaults to
 * the route's method and URI).
 */
class EnsureIdempotency
{
    public function __construct(
        protected IdempotencyService $idempotency,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $scope ??= $request->getMethod() . ' ' . ($request->route()?->uri() ?? $request->path());

        return $this->idempotency->handle($request, mb_substr($scope, 0, 64), $next);
    }
}
