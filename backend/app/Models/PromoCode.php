<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    /**
     * Orders in these statuses no longer count towards usage limits.
     */
    public const RELEASED_ORDER_STATUSES = ['canceled', 'rejected', 'refunded'];

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'value' => 'float',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'store_id' => 'integer',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function redemptions(): HasMany
    {
        return $this->orders()->whereNotIn('status', self::RELEASED_ORDER_STATUSES);
    }

    public function isValidForStore(?int $storeId): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->store_id !== null && $this->store_id !== $storeId) {
            return false;
        }

        return true;
    }
}
