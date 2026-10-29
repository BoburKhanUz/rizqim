<?php

namespace App\Models;

use App\Domain\Pos\Tips\TipKind;
use App\Domain\Pos\Tips\TipMethod;
use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-22: a tip taken with a payment, or a manager's correction of one (App\Domain\Pos\Tips). Never revenue: never a
 * payment, never on the fiscal receipt, never touched by a refund.
 *
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property int|null $order_id
 * @property int|null $bill_id
 * @property int|null $payment_id
 * @property int|null $shift_id
 * @property \Illuminate\Support\Carbon $business_date
 * @property TipKind $kind
 * @property TipMethod $method
 * @property TipSource|null $source
 * @property TipMode $mode
 * @property numeric-string $amount
 * @property int|null $staff_id
 * @property int|null $corrects_id
 * @property string|null $reason
 * @property int|null $recorded_by
 * @property int|null $approved_by
 * @property int|null $device_id
 */
class PosTip extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'kind' => TipKind::class,
            'method' => TipMethod::class,
            'source' => TipSource::class,
            'mode' => TipMode::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * The corrections of this tip.
     *
     * @return HasMany<PosTip, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(PosTip::class, 'corrects_id');
    }
}
