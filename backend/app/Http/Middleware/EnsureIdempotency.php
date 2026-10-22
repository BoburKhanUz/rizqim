<?php

namespace App\Http\Middleware;

use App\Services\Idempotency\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the Idempotency-Key header (App\Services\Idempotency\IdempotencyService). Usage, after the auth middleware:
 * ->middleware(EnsureIdempotency::class . ':orders.store'); the parameter is the scope keys are unique in (defaults to
 * the route's method and URI). P3-26: a second parameter `optional` only tells the API document (App\OpenApi\
 * IdempotencyKeyHeader) that a `pos.` route takes the header without requiring it; it changes nothing here.
 */
class EnsureIdempotency
{
    public function __construct(
        protected IdempotencyService $idempotency,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $scope = null, ?string $mode = null): Response
    {
        $scope ??= $request->getMethod() . ' ' . ($request->route()?->uri() ?? $request->path());

        return $this->idempotency->handle($request, mb_substr($scope, 0, 64), $next);
    }
}
