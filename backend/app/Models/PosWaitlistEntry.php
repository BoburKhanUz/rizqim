<?php

namespace App\Models;

use App\Domain\Pos\Waitlist\WaitlistStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-24: a guest on the hostess' waitlist (App\Domain\Pos\Waitlist\WaitlistService).
 *
 * @property WaitlistStatus $status
 * @property int $party_size
 * @property int|null $quoted_wait_minutes
 * @property \Illuminate\Support\Carbon $business_date
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property \Illuminate\Support\Carbon|null $seated_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int $version
 */
class PosWaitlistEntry extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'waiting',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'party_size' => 'integer',
            'quoted_wait_minutes' => 'integer',
            'business_date' => 'date:Y-m-d',
            'notified_at' => 'datetime',
            'seated_at' => 'datetime',
            'closed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id')->withTrashed();
    }
}
