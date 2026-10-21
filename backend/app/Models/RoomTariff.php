<?php

namespace App\Models;

use App\Enums\DayType;
use App\Enums\DiningTableKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A room fee price (P3-12, R3): for one place (dining_table_id) or a kind of place (table_kind, optionally only in one
 * hall), for a day type, within optional dates (business dates, inclusive). App\Domain\Pos\Orders\PosChargesCalculator
 * picks the most specific one.
 *
 * @property DiningTableKind|null $table_kind
 * @property DayType $day_type
 * @property numeric-string $price
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 */
class RoomTariff extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'table_kind' => DiningTableKind::class,
        'day_type' => DayType::class,
        'price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }
}
