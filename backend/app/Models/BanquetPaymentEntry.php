<?php

namespace App\Models;

use App\Domain\Pos\Banquet\Payments\BanquetEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-53: one movement of money against a banquet event's schedule row: a payment at the till (ADVANCE receipt), a
 * bank transfer recorded by the manager, a refund or a retained part on a cancellation (App\Domain\Pos\Banquet\
 * Payments). The shift's X/Z and the "Tadbirlar" report read the money from here.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property int $banquet_event_id
 * @property int|null $banquet_payment_id
 * @property BanquetEntryType $type
 * @property numeric-string $amount
 * @property string $tender
 * @property string|null $card_type
 * @property string|null $transaction_id
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon $business_date
 * @property int|null $shift_id
 * @property int|null $device_id
 * @property int|null $user_id
 * @property int|null $approved_by
 * @property string|null $reason
 * @property bool $transfer_pending
 * @property string|null $fiscal_receipt_uuid
 * @property string|null $fiscal_status
 * @property string|null $original_receipt_uuid
 * @property string|null $idempotency_key
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class BanquetPaymentEntry extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'transfer_pending' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => BanquetEntryType::class,
            'amount' => 'decimal:2',
            'business_date' => 'date',
            'transfer_pending' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BanquetEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(BanquetEvent::class, 'banquet_event_id');
    }

    /**
     * @return BelongsTo<BanquetPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(BanquetPayment::class, 'banquet_payment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
