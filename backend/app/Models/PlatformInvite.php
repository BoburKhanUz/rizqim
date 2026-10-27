<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-11: a one-time invite link of a new team member (App\Services\Admin\PlatformTeamService). Only the SHA-256 of the
 * token is kept; the link is shown once, to whoever created it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property int|null $created_by
 */
class PlatformInvite extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'created_by',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
