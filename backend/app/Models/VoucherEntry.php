<?php

namespace App\Models;

use App\Domain\Pos\Vouchers\VoucherEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-43: one movement of a voucher (sale, redemption on a bill, refund back to it, block, unblock) with the balance
 * after it.
 *
 * @property VoucherEntryType $type
 * @property numeric-string $amount
 * @property numeric-string $balance_after
 * @property \Illuminate\Support\Carbon|null $business_date
 */
class VoucherEntry extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => VoucherEntryType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'business_date' => 'date',
    ];

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
