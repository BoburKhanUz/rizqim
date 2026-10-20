<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One Idempotency-Key a user or terminal sent to an endpoint (App\Services\Idempotency\IdempotencyService).
 *
 * @property \Illuminate\Support\Carbon|null $locked_until
 * @property \Illuminate\Support\Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    use MassPrunable;

    protected $guarded = ['id'];

    protected $casts = [
        'actor_id' => 'integer',
        'response_status' => 'integer',
        'locked_until' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Expired keys are deleted by `php artisan model:prune` (scheduled daily).
     *
     * @return Builder<IdempotencyKey>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now());
    }
}
