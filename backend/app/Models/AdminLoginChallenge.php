<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-11: a correct password waiting for its second step (App\Services\Admin\AdminAuthService). Only the SHA-256 of
 * the token is kept; pending_secret is the TOTP secret of a set-up not confirmed yet (encrypted, hidden).
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $purpose
 * @property string|null $pending_secret
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
class AdminLoginChallenge extends Model
{
    public const PURPOSE_OTP = 'otp';
    public const PURPOSE_SETUP = 'setup';

    protected $fillable = [
        'user_id',
        'token_hash',
        'purpose',
        'ip',
        'user_agent',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
        'pending_secret',
    ];

    protected function casts(): array
    {
        return [
            'pending_secret' => 'encrypted',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
