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
 *
 * P4-21 (docs/BILLING.md)
 * @property \App\Enums\BillingPeriod $billing_period
 * @property \App\Enums\BillingPeriod|null $next_billing_period
 * @property \Illuminate\Support\Carbon|null $term_ends_at end of the yearly term paid in advance
 * @property int|null $prorate_from_plan_id the plan an immediate upgrade came from (the first month's credit)
 * @property int|null $price_lock_plan_id a P1-23 plan whose price caps this row's periods until price_locked_until
 * @property \Illuminate\Support\Carbon|null $price_locked_until
 * @property \Illuminate\Support\Carbon|null $locked_at when the dunning routine locked it
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
            // P4-21
            'billing_period' => \App\Enums\BillingPeriod::class,
            'next_billing_period' => \App\Enums\BillingPeriod::class,
            'term_ends_at' => 'datetime',
            'price_locked_until' => 'datetime',
            'locked_at' => 'datetime',
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

    public function isYearly(): bool
    {
        return $this->billing_period === \App\Enums\BillingPeriod::YEARLY;
    }

    /**
     * @return BelongsTo<TariffPlan, $this>
     */
    public function prorateFromPlan(): BelongsTo
    {
        return $this->belongsTo(TariffPlan::class, 'prorate_from_plan_id');
    }

    /**
     * @return BelongsTo<TariffPlan, $this>
     */
    public function priceLockPlan(): BelongsTo
    {
        return $this->belongsTo(TariffPlan::class, 'price_lock_plan_id');
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
