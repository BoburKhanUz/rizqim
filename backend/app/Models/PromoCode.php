<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'value' => 'float',
        'min_order_amount' => 'float',
        'max_discount_amount' => 'float',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
