<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Finance\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P4-21: `billing.unlocked`: settings writes are refused (403 subscription_locked) while the store's subscription is
 * locked by the dunning routine (an invoice unpaid more than a week after its due date). Paying unlocks at once.
 * Only on settings that do not keep the till working (prices of the hall, fees, the floor plan, kitchen stations):
 * never on printers, devices within the plan, the menu, payments, receipts, shifts or refunds (docs/BILLING.md).
 */
class EnsureBillingUnlocked
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->store_id !== null && !$request->isMethodSafe()) {
            $this->entitlements->assertSettingsUnlocked((int) $user->store_id);
        }

        return $next($request);
    }
}
