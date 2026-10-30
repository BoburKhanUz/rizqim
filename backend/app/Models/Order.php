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
 * @property string|null $commission_channel P4-21: App\Enums\CommissionChannel value at checkout (null before Billing v2)
 * @property numeric-string|null $commission_rate P4-21: the percent charged at checkout
 * @property \Illuminate\Support\Carbon|null $settlement_failed_at
 * @property int $settlement_attempts
 * @property \App\Enums\CancelReason|null $cancel_reason
 * @property string|null $cancel_note
 * @property int|null $cancelled_by
 * @property \App\Enums\OrderChannel $channel
 * @property numeric-string $service_charge_amount
 * @property numeric-string $place_fee_amount
 * @property int|null $guests_count
 * @property int|null $delivery_zone_id
 * @property numeric-string|null $delivery_zone_price
 * @property numeric-string|null $delivery_min_order_amount
 *
 * P3-12: POS hall orders (hall, table, waiter, terminal, optimistic lock, room fee and minimum spend lines)
 * @property int|null $hall_id
 * @property int|null $table_id
 * @property int|null $waiter_id
 * @property int|null $device_id
 * @property int $version
 * @property numeric-string $room_fee_amount
 * @property numeric-string $min_spend_topup_amount
 *
 * P3-18: shift, business day, queue number, bill lock and close (App\Domain\Pos\Billing, App\Domain\Pos\Shifts)
 * @property int|null $shift_id
 * @property \Illuminate\Support\Carbon|null $business_date
 * @property int|null $queue_number
 * @property \Illuminate\Support\Carbon|null $bill_locked_at
 * @property \Illuminate\Support\Carbon|null $bill_requested_at a waiter asked the till for the bill (P3-26)
 * @property int|null $bill_requested_by
 * @property \Illuminate\Support\Carbon|null $closed_at
 *
 * P3-17: the booking the order was opened from ("Keldi") and when a pre-order is due
 * @property int|null $booking_id
 * @property \Illuminate\Support\Carbon|null $due_at
 *
 * P3 integration fix: when the owner was told the hall order is open too long (once per order, R20)
 * @property \Illuminate\Support\Carbon|null $long_open_alerted_at
 *
 * P3-31: an order a till sold offline and synced later (App\Domain\Pos\Sync)
 * @property string|null $local_number
 * @property \Illuminate\Support\Carbon|null $offline_created_at
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property array<int, array<string, mixed>>|null $sync_flags
 *
 * P3-37: a discount given at the till; `discount` above is the amount it comes to (App\Domain\Pos\Orders)
 * @property string|null $discount_kind percent|amount
 * @property numeric-string|null $discount_value
 * @property string|null $discount_reason
 * @property int|null $discount_by
 *
 * P6-13: the pickup queue of a counter order with a queue number (App\Domain\Pos\Orders\PickupQueue)
 * @property string|null $pickup_status preparing|ready|handed
 * @property \Illuminate\Support\Carbon|null $pickup_ready_at
 * @property \Illuminate\Support\Carbon|null $pickup_called_at
 * @property int $pickup_call_count
 * @property \Illuminate\Support\Carbon|null $pickup_handed_at
 * @property string|null $table_label P6-25: the table plate of a counter order carried to the table (kafe)
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
    }
    
    protected $appends = ['address', 'type'];

    /** The column default, so an order just created reads its channel like one loaded back (P1-21). */
    protected $attributes = [
        'channel' => 'telegram',
    ];

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
            'commission_rate' => 'decimal:2', // P4-21
            // A delivered order whose ledger booking failed and is retried (SettlementRecoveryService)
            'settlement_failed_at' => 'datetime',
            'settlement_attempts' => 'integer',
            // Why the order was cancelled or rejected; null on older cancellations (App\Enums\CancelReason)
            'cancel_reason' => \App\Enums\CancelReason::class,
            // P1-21: where the order came from; service charge and place fee lines (POS hall), guests
            'channel' => \App\Enums\OrderChannel::class,
            'service_charge_amount' => 'decimal:2',
            'place_fee_amount' => 'decimal:2',
            'guests_count' => 'integer',
            // P1-32: the delivery zone as it was at checkout (null without zones); the fee charged is delivery_price
            'delivery_zone_id' => 'integer',
            'delivery_zone_price' => 'decimal:2',
            'delivery_min_order_amount' => 'decimal:2',
            // P3-12: POS hall orders (App\Domain\Pos\Orders)
            'hall_id' => 'integer',
            'table_id' => 'integer',
            'waiter_id' => 'integer',
            'device_id' => 'integer',
            'version' => 'integer',
            'room_fee_amount' => 'decimal:2',
            'min_spend_topup_amount' => 'decimal:2',
            // P3-18: shift, business day, queue number, bill lock and close
            'shift_id' => 'integer',
            'business_date' => 'date',
            'queue_number' => 'integer',
            'bill_locked_at' => 'datetime',
            'bill_requested_at' => 'datetime', // P3-26
            'bill_requested_by' => 'integer',
            'closed_at' => 'datetime',
            // P3-17: booking (its deposits are the order's prepayment) and the due time of a pre-order
            'booking_id' => 'integer',
            'due_at' => 'datetime',
            // P3 integration fix: the long-open alert (App\Domain\Pos\Orders\PosOrderAlerts)
            'long_open_alerted_at' => 'datetime',
            // P3-31: offline sales (App\Domain\Pos\Sync): the device's local number, when it opened and reached us, flags
            'offline_created_at' => 'datetime',
            'synced_at' => 'datetime',
            'sync_flags' => 'array',
            // P3-37: the till's discount (kind, the value typed, who gave it); `discount` is its amount
            'discount_value' => 'decimal:2',
            'discount_by' => 'integer',
            // P6-13: the pickup queue of a counter order with a number (App\Domain\Pos\Orders\PickupQueue)
            'pickup_ready_at' => 'datetime',
            'pickup_called_at' => 'datetime',
            'pickup_call_count' => 'integer',
            'pickup_handed_at' => 'datetime',
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
     * The zone the order was priced from (P1-32); null once the zone is deleted, the snapshot columns stay.
     *
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
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

    // --- P3-12: POS hall orders -------------------------------------------------------------------------------

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }

    /**
     * The table, room or tapchan the order sits at (null: takeaway, or in the hall without a table).
     *
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * @return HasMany<OrderChargeWaiver, $this>
     */
    public function chargeWaivers(): HasMany
    {
        return $this->hasMany(OrderChargeWaiver::class);
    }

    // --- end P3-12 ------------------------------------------------------------------------------------------------

    // --- P3-18: bills, payments and shifts ---------------------------------------------------------------------------

    /**
     * @return HasMany<OrderBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(OrderBill::class);
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    // --- end P3-18 ------------------------------------------------------------------------------------------------

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

    // --- P3-17: bookings --------------------------------------------------------------------------------------

    /**
     * The booking the order was opened from ("Keldi"); its deposits are the order's prepayment (AdvanceLedger).
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
