<?php

namespace App\Models;

use App\Domain\Pos\Shifts\ShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P3-18: one till's shift (POS_SPEC 2.3): opened on a terminal with the opening cash, closed with the Z report.
 *
 * @property ShiftStatus $status
 * @property \Illuminate\Support\Carbon $business_date
 * @property \Illuminate\Support\Carbon $opened_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property numeric-string $opening_cash
 * @property numeric-string|null $expected_cash
 * @property numeric-string|null $counted_cash
 * @property numeric-string|null $difference
 * @property array<string, int>|null $counted_breakdown
 * @property array<string, mixed>|null $summary
 * @property bool $force_closed
 */
class PosShift extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'counted_breakdown' => 'array',
            'summary' => 'array',
            'force_closed' => 'boolean',
            'z_number' => 'integer',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::OPEN;
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
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }
}
