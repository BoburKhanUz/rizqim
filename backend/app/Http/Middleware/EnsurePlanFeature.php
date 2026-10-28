<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Models\User;
use App\Services\Finance\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P4-21: `plan.feature:<feature>` (App\Enums\PlanFeature value): the caller's store has the feature on its plan, else
 * 403 plan_feature_missing with the cheapest plan that has it. Only on gated writes (creating a booking, a deposit, a
 * nasiya account...): never on payments, closing bills, receipts, shifts or refunds (docs/BILLING.md).
 */
class EnsurePlanFeature
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->store_id !== null) {
            $this->entitlements->assertFeature((int) $user->store_id, PlanFeature::from($feature));
        }

        return $next($request);
    }
}
