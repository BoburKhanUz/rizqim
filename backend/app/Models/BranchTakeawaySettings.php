<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-34: a branch's pickup-time rules (App\Domain\Pos\Takeaway\ScheduledPickup reads and changes them). One row per
 * branch; none: the defaults.
 *
 * @property int $store_id
 * @property int $branch_id
 * @property int $pickup_min_lead_minutes
 * @property int $pickup_slot_minutes
 * @property int $pickup_max_days_ahead
 * @property int $pickup_prep_minutes
 */
class BranchTakeawaySettings extends Model
{
    protected $table = 'branch_takeaway_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'branch_id' => 'integer',
            'pickup_min_lead_minutes' => 'integer',
            'pickup_slot_minutes' => 'integer',
            'pickup_max_days_ahead' => 'integer',
            'pickup_prep_minutes' => 'integer',
        ];
    }
}
