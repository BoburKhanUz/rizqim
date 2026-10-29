<?php

namespace App\Models;

use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code for pairing a terminal (App\Services\Auth\DevicePairingService). Only an HMAC of the code is kept.
 *
 * @property DeviceType $type
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int|null $display_for_device_id P6-14: the till a customer display paired with this code follows
 */
class DevicePairingCode extends Model
{
    protected $fillable = [
        'store_id',
        'branch_id',
        'type',
        'name',
        'code_hash',
        'created_by',
        'expires_at',
        'display_for_device_id',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Unused and not yet expired.
     *
     * @param  Builder<DevicePairingCode>  $query
     * @return Builder<DevicePairingCode>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
