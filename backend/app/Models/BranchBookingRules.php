<?php

namespace App\Models;

use App\Domain\Pos\Booking\BookingDepositMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-26: a branch's table booking rules (App\Domain\Pos\Booking\BookingRules reads and changes it). One row per
 * branch; a branch without one keeps the choyxona's behaviour.
 *
 * @property int $table_slot_minutes
 * @property int|null $room_slot_minutes
 * @property int|null $tapchan_slot_minutes
 * @property int $table_min_advance_minutes
 * @property int $room_min_advance_minutes
 * @property int $tapchan_min_advance_minutes
 * @property int|null $max_advance_days
 * @property BookingDepositMode $deposit_mode
 * @property numeric-string $deposit_amount
 * @property int $deposit_from_guests
 * @property bool $deposit_required_to_confirm
 */
class BranchBookingRules extends Model
{
    protected $table = 'branch_booking_rules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'table_slot_minutes' => 'integer',
            'room_slot_minutes' => 'integer',
            'tapchan_slot_minutes' => 'integer',
            'table_min_advance_minutes' => 'integer',
            'room_min_advance_minutes' => 'integer',
            'tapchan_min_advance_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'deposit_mode' => BookingDepositMode::class,
            'deposit_amount' => 'decimal:2',
            'deposit_from_guests' => 'integer',
            'deposit_required_to_confirm' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
