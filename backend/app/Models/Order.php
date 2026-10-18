<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property \App\Enums\OrderStatus $status
 * @property \App\Enums\PaymentStatus $payment_status
 * @property \App\Enums\OrderType $order_type
 * @property array<string, mixed>|null $shipping_address
 * @property numeric-string $subtotal
 * @property numeric-string $delivery_price
 * @property numeric-string $discount
 * @property numeric-string $total_price
 * @property numeric-string $commission_amount
 */
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

        // Cached operational metrics (store-wide and per branch) go stale with every order change
        static::saved(fn ($order) => \App\Services\Ops\OpsContext::forgetOrderMetrics($order));
        static::deleted(fn ($order) => \App\Services\Ops\OpsContext::forgetOrderMetrics($order));
    }
    
    protected $appends = ['address', 'type'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\OrderStatus::class,
            'payment_status' => \App\Enums\PaymentStatus::class,
            'order_type' => \App\Enums\OrderType::class,
            'shipping_address' => 'array',
            'subtotal' => 'decimal:2',
            'delivery_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function getTypeAttribute(): ?string
    {
        // Null only on an in-memory order created without an order_type (the column default is not read back)
        return $this->order_type->value ?? null;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->shipping_address['address'] ?? null;
    }

    public function getDeliveryAddressAttribute(): ?string
    {
        return $this->shipping_address['address'] ?? null;
    }
    
    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    
    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * Whether the customer has lost cash payment because of failed cash pickup orders (see config/orders.php).
     * Only cancellations by the customer, or after the store accepted the order (not collected), count;
     * `rejected` orders and store refusals of still-new orders do not.
     */
    public static function cashPickupBanApplies(int $userId): bool
    {
        $threshold = (int) config('orders.cash_pickup_ban.threshold', 1);
        if ($threshold < 1) {
            return false;
        }

        $windowDays = (int) config('orders.cash_pickup_ban.window_days', 30);

        $failedCount = static::query()
            ->where('user_id', $userId)
            ->where('order_type', 'pickup')
            ->where('payment_method', 'cash')
            ->where('status', 'canceled')
            ->when($windowDays > 0, fn ($query) => $query->where('created_at', '>=', now()->subDays($windowDays)))
            ->where(function ($query) {
                $query->whereHas('statusHistories', function ($history) {
                    $history->where('order_status_histories.status', 'canceled')
                        ->whereColumn('order_status_histories.user_id', 'orders.user_id');
                })->orWhereHas('statusHistories', function ($history) {
                    $history->whereNotIn('order_status_histories.status', ['new', 'canceled', 'rejected', 'refunded']);
                });
            })
            ->count();

        return $failedCount >= $threshold;
    }
}
