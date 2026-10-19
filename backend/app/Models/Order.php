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
 * @property \Illuminate\Support\Carbon|null $settlement_failed_at
 * @property int $settlement_attempts
 * @property \App\Enums\CancelReason|null $cancel_reason
 * @property string|null $cancel_note
 * @property int|null $cancelled_by
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
            // A delivered order whose ledger booking failed and is retried (SettlementRecoveryService)
            'settlement_failed_at' => 'datetime',
            'settlement_attempts' => 'integer',
            // Why the order was cancelled or rejected; null on older cancellations (App\Enums\CancelReason)
            'cancel_reason' => \App\Enums\CancelReason::class,
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
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Whether the customer may still cancel the order themselves (POST /orders/{uuid}/cancel): only while it is new
     * and nothing was paid for it (cash, or an online payment still pending). Keshbek is paid at checkout.
     */
    public function isCancellableByCustomer(): bool
    {
        return $this->status === \App\Enums\OrderStatus::NEW
            && !in_array($this->payment_status, [\App\Enums\PaymentStatus::PAID, \App\Enums\PaymentStatus::REFUNDED], true);
    }

    /**
     * Whether the customer has lost cash payment because of failed cash pickup orders (see config/orders.php).
     * Only pickup orders the store cancelled because the customer never came (cancel_reason no_show) count: store-side
     * reasons and the customer's own cancellation of a still-new order do not. Cancellations from before reasons were
     * recorded have no reason and never count (they were not backfilled).
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
            ->where('status', \App\Enums\OrderStatus::CANCELED->value)
            ->where('cancel_reason', \App\Enums\CancelReason::NO_SHOW->value)
            ->when($windowDays > 0, fn ($query) => $query->where('created_at', '>=', now()->subDays($windowDays)))
            ->count();

        return $failedCount >= $threshold;
    }
}
