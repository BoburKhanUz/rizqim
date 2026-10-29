<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-13: a range of queue numbers of one branch and business day, held by a till (`device_id`) or by the branch
 * itself (null: Telegram pickup orders, orders opened without a till). See App\Domain\Pos\Shifts\QueueNumbers.
 *
 * @property int $id
 * @property int $store_id
 * @property int $branch_id
 * @property int|null $device_id
 * @property \Illuminate\Support\Carbon $business_date
 * @property int $start_number
 * @property int $end_number
 * @property int $next_number
 */
class PosQueueBlock extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'start_number' => 'integer',
            'end_number' => 'integer',
            'next_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function contains(int $number): bool
    {
        return $number >= $this->start_number && $number <= $this->end_number;
    }

    /**
     * Numbers of the block the server has not seen used yet.
     */
    public function remaining(): int
    {
        return max(0, $this->end_number - max($this->next_number, $this->start_number) + 1);
    }
}
