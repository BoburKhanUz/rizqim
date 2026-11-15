<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An order's status history: one row per transition (from_status → status); the creation row has no from_status.
 *
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property \App\Enums\OrderStatus $status
 * @property \App\Enums\OrderStatus|null $from_status
 */
class OrderStatusHistory extends Model
{
    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'order_id', 'user_id', 'status', 'notes', 'created_at', 'updated_at', 'from_status', 'order_status_log_id',
    ];

    // Kept out of GET /orders, which embeds the history in the shape the Mini App reads
    protected $hidden = ['from_status', 'order_status_log_id'];
    
    protected function casts(): array
    {
        return [
            'status' => \App\Enums\OrderStatus::class,
            'from_status' => \App\Enums\OrderStatus::class,
        ];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
