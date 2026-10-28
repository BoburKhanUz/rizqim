<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-22: a Telegram announcement from the team to store owners (App\Services\Admin\Stores\StoreBroadcastService).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $created_by
 * @property string $audience all | selected
 * @property list<int>|null $store_ids
 * @property string $text
 * @property string $reason
 * @property int $stores
 * @property int $recipients
 * @property int $queued
 * @property string $status queued | sending | sent | failed
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read User|null $creator
 */
class StoreBroadcast extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'store_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
