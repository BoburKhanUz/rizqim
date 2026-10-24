<?php

namespace App\Models;

use App\Domain\Pos\Booking\BookingEventType;
use App\Domain\Pos\Booking\BookingSource;
use App\Domain\Pos\Booking\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P3-17: a booking of a room or table (App\Domain\Pos\Booking\BookingService; docs/API.md "Bron").
 *
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property int $guests_planned
 * @property BookingEventType $event_type
 * @property BookingSource $source
 * @property BookingStatus $status
 * @property numeric-string|null $min_spend
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $arrived_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $canceled_at
 * @property \Illuminate\Support\Carbon|null $confirmation_sent_at P3-38: when the guest was last sent the confirmation
 * @property string|null $confirmation_channel P3-38: telegram or sms
 */
class Booking extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'requested',
        'event_type' => 'other',
        'source' => 'phone',
        'guests_planned' => 1,
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'guests_planned' => 'integer',
            'event_type' => BookingEventType::class,
            'source' => BookingSource::class,
            'status' => BookingStatus::class,
            'min_spend' => 'decimal:2',
            'version' => 'integer',
            'confirmed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
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
     * Every room or table the booking occupies, the main one (dining_table_id) included.
     *
     * @return HasMany<BookingTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(BookingTable::class);
    }

    /**
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * @return HasMany<BookingPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
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
