<?php

namespace App\Models;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetEventType;
use App\Domain\Pos\Banquet\BanquetSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-51: a banquet event (to'y, osh…) of a branch: halls × date × slot (App\Domain\Pos\Banquet\BanquetEventService;
 * docs/API.md "Tadbirlar").
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property \Illuminate\Support\Carbon $event_date
 * @property BanquetSlot $slot
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property BanquetEventType $event_type
 * @property BanquetEventStatus $status
 * @property string|null $title
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property int $guests_guaranteed
 * @property int $guests_minimum
 * @property int|null $guests_actual
 * @property int|null $banquet_package_id
 * @property array<string, mixed>|null $package_snapshot
 * @property numeric-string $package_total
 * @property numeric-string $hall_total
 * @property numeric-string $extras_total
 * @property numeric-string $orders_total
 * @property numeric-string $total
 * @property numeric-string $paid_total
 * @property string|null $quote_currency
 * @property numeric-string|null $quote_rate
 * @property \Illuminate\Support\Carbon|null $hold_expires_at
 * @property string|null $contract_no
 * @property \Illuminate\Support\Carbon|null $frozen_at
 * @property int|null $frozen_by
 * @property int|null $order_id
 * @property string|null $note
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property int $version
 */
class BanquetEvent extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'hold',
        'event_type' => 'toy',
        'guests_guaranteed' => 0,
        'guests_minimum' => 0,
        'package_total' => '0.00',
        'hall_total' => '0.00',
        'extras_total' => '0.00',
        'orders_total' => '0.00',
        'total' => '0.00',
        'paid_total' => '0.00',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'slot' => BanquetSlot::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'event_type' => BanquetEventType::class,
            'status' => BanquetEventStatus::class,
            'guests_guaranteed' => 'integer',
            'guests_minimum' => 'integer',
            'guests_actual' => 'integer',
            'package_snapshot' => 'array',
            'package_total' => 'decimal:2',
            'hall_total' => 'decimal:2',
            'extras_total' => 'decimal:2',
            'orders_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'quote_rate' => 'decimal:4',
            'hold_expires_at' => 'datetime',
            'frozen_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'released_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * The guests the package is billed for before the day: max(guaranteed, minimum) (P6-52).
     */
    public function billedGuests(): int
    {
        return max((int) $this->guests_guaranteed, (int) $this->guests_minimum);
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Every hall the event takes (several when merged).
     *
     * @return BelongsToMany<Hall, $this>
     */
    public function halls(): BelongsToMany
    {
        return $this->belongsToMany(Hall::class, 'banquet_event_halls')->withTimestamps();
    }

    /**
     * @return HasMany<BanquetEventLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BanquetEventLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The payment schedule (P6-53).
     *
     * @return HasMany<BanquetPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BanquetPayment::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<BanquetPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(BanquetPackage::class, 'banquet_package_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
