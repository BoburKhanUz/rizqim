<?php

namespace App\Models;

use App\Domain\Pos\Vouchers\VoucherKind;
use App\Domain\Pos\Vouchers\VoucherStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-43: a gift certificate (App\Domain\Pos\Vouchers). The balance moves only under the row's lock, each move a
 * VoucherEntry with the balance after it.
 *
 * @property VoucherKind $kind
 * @property VoucherStatus $status
 * @property numeric-string $face_value
 * @property numeric-string $balance
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
class Voucher extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'kind' => VoucherKind::class,
        'status' => VoucherStatus::class,
        'face_value' => 'decimal:2',
        'balance' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'sold_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    protected $hidden = ['idempotency_key'];

    /**
     * @return HasMany<VoucherEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }
}
