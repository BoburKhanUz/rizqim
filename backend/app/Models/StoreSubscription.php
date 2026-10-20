<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P1-23: a store's tariff plan for a stretch of time (docs/BILLING.md). Rows change only through
 * App\Services\Finance\SubscriptionService.
 *
 * @property int $id
 * @property int $store_id
 * @property int $tariff_plan_id
 * @property \App\Enums\SubscriptionStatus $status
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon $current_period_start
 * @property \Illuminate\Support\Carbon $current_period_end
 * @property int|null $next_tariff_plan_id
 * @property bool $cancel_at_period_end
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|null $created_by
 */
class StoreSubscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Rows that are the store's current subscription (every status but cancelled).
     *
     * @param  Builder<StoreSubscription>  $query
     * @return Builder<StoreSubscription>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', '!=', SubscriptionStatus::CANCELLED->value);
    }

    public function isCurrent(): bool
    {
        return $this->status !== SubscriptionStatus::CANCELLED;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<TariffPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TariffPlan::class, 'tariff_plan_id');
    }

    /**
     * @return BelongsTo<TariffPlan, $this>
     */
    public function nextPlan(): BelongsTo
    {
        return $this->belongsTo(TariffPlan::class, 'next_tariff_plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }
}
