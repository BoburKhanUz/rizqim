<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-17: one room or table a booking occupies (a banquet may merge several).
 */
class BookingTable extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'dining_table_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
