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

    protected $guarded = ['id'];

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
