<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-23: a staff member's clock-in/out in a branch (App\Domain\Pos\StaffPay\WorkTime).
 *
 * @property \Illuminate\Support\Carbon $business_date
 * @property \Illuminate\Support\Carbon $clock_in_at
 * @property \Illuminate\Support\Carbon|null $clock_out_at
 * @property \Illuminate\Support\Carbon|null $abandoned_at
 * @property string $source App\Domain\Pos\StaffPay\WorkSessionSource value
 */
class StaffWorkSession extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->clock_out_at === null && $this->abandoned_at === null;
    }

    /**
     * Whole minutes worked (0 while open or set aside).
     */
    public function minutes(): int
    {
        if ($this->clock_out_at === null) {
            return 0;
        }

        return max(0, intdiv($this->clock_out_at->getTimestamp() - $this->clock_in_at->getTimestamp(), 60));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
