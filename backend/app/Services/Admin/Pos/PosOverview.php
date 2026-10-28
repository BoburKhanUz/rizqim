<?php

namespace App\Services\Admin\Pos;

use App\Enums\AdminPermission;
use App\Models\StoreSubscription;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;

/**
 * P4-24: the numbers on the tabs of POS va obunalar. A group is null when the member may not see that part (the
 * subscription and invoice tabs need subscriptions.manage or finance.view; devices, shifts, fiscal receipts and sync
 * errors need devices.view).
 */
class PosOverview
{
    public const TRIAL_ENDING_DAYS = 3;

    public function __construct(
        private readonly AdminPermissionService $permissions,
        private readonly PosInvoices $invoices,
        private readonly PosDevices $devices,
        private readonly PosShifts $shifts,
        private readonly PosFiscal $fiscal,
        private readonly PosSyncErrors $sync,
    ) {}

    /**
     * @return array{subscriptions: array<string, mixed>|null, invoices: array<string, mixed>|null, devices: array<string, mixed>|null, shifts: array<string, mixed>|null, fiscal: array<string, mixed>|null, sync_errors: array<string, mixed>|null}
     */
    public function for(User $member): array
    {
        $billing = $this->permissions->has($member, AdminPermission::SUBSCRIPTIONS_MANAGE) || $this->permissions->has($member, AdminPermission::FINANCE_VIEW);
        $pos = $this->permissions->has($member, AdminPermission::DEVICES_VIEW);

        return [
            'subscriptions' => $billing ? $this->subscriptions() : null,
            'invoices' => $billing ? $this->invoices->counts() : null,
            'devices' => $pos ? $this->devices->counts() : null,
            'shifts' => $pos ? $this->shifts->counts(['from' => now((string) config('billing.timezone'))->subDays(30)->toDateString()]) : null,
            'fiscal' => $pos ? $this->fiscal->summary() : null,
            'sync_errors' => $pos ? $this->sync->counts() : null,
        ];
    }

    /**
     * @return array{counts: array<string, int>, trial_ending: int, trial_ending_days: int}
     */
    private function subscriptions(): array
    {
        return [
            'counts' => StoreSubscription::query()->current()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
            'trial_ending' => StoreSubscription::query()->current()->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())->where('trial_ends_at', '<=', now()->addDays(self::TRIAL_ENDING_DAYS))->count(),
            'trial_ending_days' => self::TRIAL_ENDING_DAYS,
        ];
    }
}
