<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6-34: the `takeaway` section of GET /pos/bootstrap: the branch's packaging rules (module `packaging`; the till prices
 * them offline, pos_data OfflinePricing.packaging — [] while the module is off), the pickup-time rules (module
 * `scheduled_pickup`; null while off; the till asks GET /pos/pickup-slots for the times) and the business day (24/7:
 * when the next one starts, so the till rolls its queue blocks over at the day start).
 */
final class TakeawayBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = 'array{packaging: list<' . PackagingRules::RULE . '>, pickup: array{min_lead_minutes: int, slot_minutes: int, max_days_ahead: int, prep_minutes: int}|null, business_day: ' . RoundTheClock::BUSINESS_DAY . '}';

    public function __construct(
        private readonly PackagingRules $packaging,
        private readonly ScheduledPickup $pickup,
        private readonly RoundTheClock $roundTheClock,
        private readonly PosModules $modules,
    ) {}

    /**
     * @return array{takeaway: array<string, mixed>}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $pickup = null;
        if ($this->modules->enabled($branch, PosModule::SCHEDULED_PICKUP)) {
            $settings = $this->pickup->settings($branch);
            $pickup = [
                'min_lead_minutes' => $settings['pickup_min_lead_minutes'],
                'slot_minutes' => $settings['pickup_slot_minutes'],
                'max_days_ahead' => $settings['pickup_max_days_ahead'],
                'prep_minutes' => $settings['pickup_prep_minutes'],
            ];
        }

        return ['takeaway' => [
            'packaging' => $this->packaging->offlineRules($branch),
            'pickup' => $pickup,
            'business_day' => $this->roundTheClock->businessDay($branch),
        ]];
    }
}
