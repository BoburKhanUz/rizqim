<?php

namespace App\Models;

use App\Domain\Pos\Shifts\CashMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-18: money into or out of a shift's cash drawer. `amount` is positive; CashMovementType::sign() gives the direction.
 *
 * @property CashMovementType $type
 * @property numeric-string $amount
 */
class CashMovement extends Model
{
    use \App\Traits\HasPublicUuid;

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'uuid', 'store_id', 'branch_id', 'shift_id', 'type', 'category', 'amount', 'note', 'payment_id', 'user_id',
        'employee_id', 'device_id', 'approved_by', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
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
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
