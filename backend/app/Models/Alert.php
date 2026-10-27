<?php

namespace App\Models;

use App\Services\Admin\Monitoring\AlertState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-15: one episode of a problem found by an alert rule (Monitoring → Ogohlantirishlar). `details` holds numbers and
 * names only (counts, queue names, device and store names, ages): never payloads, exception text or personal data.
 *
 * @property int $id
 * @property int $alert_rule_id
 * @property string $kind
 * @property string $subject_key
 * @property string $severity critical | warning | info
 * @property string $state open | acknowledged | resolved
 * @property string $title
 * @property array<string, mixed>|null $details
 * @property int|null $store_id
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property int $count
 * @property int|null $acknowledged_by
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property string|null $acknowledge_note
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $resolve_note
 * @property string|null $resolution manual | auto | rule_disabled
 * @property \Illuminate\Support\Carbon|null $telegram_opened_at
 * @property \Illuminate\Support\Carbon|null $telegram_resolved_at
 * @property-read User|null $acknowledgedBy
 * @property-read User|null $resolvedBy
 * @property-read Store|null $store
 */
class Alert extends Model
{
    use MassPrunable;

    /** Resolved alerts are kept this long (the history tab), then removed by `model:prune` (daily). */
    public const KEEP_RESOLVED_DAYS = 90;

    protected $guarded = ['id'];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('state', AlertState::RESOLVED->value)->where('resolved_at', '<', now()->subDays(self::KEEP_RESOLVED_DAYS));
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'telegram_opened_at' => 'datetime',
            'telegram_resolved_at' => 'datetime',
            'count' => 'integer',
        ];
    }

    /**
     * Open or acknowledged: the problem is still there as far as the team knows.
     *
     * @param  Builder<Alert>  $query
     * @return Builder<Alert>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('state', '<>', AlertState::RESOLVED->value);
    }

    public function isResolved(): bool
    {
        return $this->state === AlertState::RESOLVED->value;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
