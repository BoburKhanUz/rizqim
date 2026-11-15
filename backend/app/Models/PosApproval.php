<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * P3-18: a manager's one-time approval on a terminal (POST /pos/approvals): one permission, one device, 60 seconds.
 * Only the token's sha256 is stored.
 *
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
class PosApproval extends Model
{
    use MassPrunable;

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'token_hash', 'store_id', 'branch_id', 'device_id', 'permission', 'manager_id', 'requested_by', 'expires_at',
        'used_at', 'used_by', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'manager_id' => 'integer',
        'device_id' => 'integer',
    ];

    /**
     * Approvals are only worth keeping while they can be used; the audit log keeps who approved what.
     *
     * @return Builder<PosApproval>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now()->subDays(7));
    }
}
