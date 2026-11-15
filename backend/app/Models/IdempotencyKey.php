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

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'scope', 'actor_type', 'actor_id', 'key', 'request_hash', 'locked_until', 'response_status', 'response_body',
        'response_content_type', 'expires_at', 'created_at', 'updated_at',
    ];

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
