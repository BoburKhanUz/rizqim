<?php

namespace App\Services\Finance\Billing;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P4-21: GET /pos/bootstrap's `entitlements`: the store's plan, status, features and devices (the same payload as GET
 * /owner/billing/entitlements), so the till, the waiter phones and the KDS show "Asosiy tarifda bor" and the dunning
 * notice without another call. Payments, receipts, shifts and refunds never depend on it.
 */
final class EntitlementsBootstrapSection implements PosBootstrapContributor
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        // A terminal never sees the overdue invoice's number and amounts (Entitlements::showsInvoiceTo)
        return ['entitlements' => $this->entitlements->toArray((int) $branch->store_id, null, $this->entitlements->showsInvoiceTo($user, $device))];
    }
}
