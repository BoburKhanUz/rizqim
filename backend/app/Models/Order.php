<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    
    protected $guarded = ['id'];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->short_code)) {
                // Generate a random 4-5 digit hex code like A1024
                $order->short_code = 'A' . strtoupper(substr(uniqid(), -4));
            }
        });

        static::saved(function ($order) {
            $storeId = $order->store_id;
            if ($storeId) {
                // Invalidate all cached operational metrics for this store
                \Illuminate\Support\Facades\Cache::forget("ops:delayed_orders_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:active_prep_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:active_transit_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:pending_accept_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:kitchen_state_object:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:courier_pressure_object:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:operator_state_map:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:top_selling_product_today:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:average_order_value:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:payment_method_dominance:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:failed_transactions_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:rush_hour_profitability:store_{$storeId}");
            }
        });

        static::deleted(function ($order) {
            $storeId = $order->store_id;
            if ($storeId) {
                \Illuminate\Support\Facades\Cache::forget("ops:delayed_orders_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:active_prep_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:active_transit_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:pending_accept_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:kitchen_state_object:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:courier_pressure_object:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:operator_state_map:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:top_selling_product_today:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:average_order_value:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:payment_method_dominance:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:failed_transactions_count:store_{$storeId}");
                \Illuminate\Support\Facades\Cache::forget("ops:rush_hour_profitability:store_{$storeId}");
            }
        });
    }
    
    protected $appends = ['address', 'type'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\OrderStatus::class,
            'payment_status' => \App\Enums\PaymentStatus::class,
            'order_type' => \App\Enums\OrderType::class,
            'shipping_address' => 'array',
            'commission_amount' => 'float',
        ];
    }

    public function getTypeAttribute(): ?string
    {
        return is_object($this->order_type) ? $this->order_type->value : $this->order_type;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->shipping_address['address'] ?? null;
    }

    public function getDeliveryAddressAttribute(): ?string
    {
        return $this->shipping_address['address'] ?? null;
    }
    
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
