<?php

namespace App\Models;

use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-53: a row of an event's payment schedule (deposit, interim, final) and, once paid, the money taken against it
 * with its ADVANCE receipt.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property int $banquet_event_id
 * @property BanquetPaymentKind $kind
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property numeric-string $amount
 * @property BanquetPaymentStatus $status
 * @property string|null $tender
 * @property string|null $card_type
 * @property string|null $transaction_id
 * @property string|null $reference
 * @property int|null $shift_id
 * @property int|null $device_id
 * @property string|null $fiscal_receipt_uuid
 * @property string|null $fiscal_status
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property int|null $paid_by
 * @property numeric-string $refunded_amount
 * @property numeric-string $retained_amount
 * @property string|null $settle_receipt_uuid
 * @property string|null $settle_fiscal_status
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property string|null $note
 * @property int $sort_order
 * @property string|null $idempotency_key
 */
class BanquetPayment extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'due',
        'refunded_amount' => '0.00',
        'retained_amount' => '0.00',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => BanquetPaymentKind::class,
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'status' => BanquetPaymentStatus::class,
            'paid_at' => 'datetime',
            'refunded_amount' => 'decimal:2',
            'retained_amount' => 'decimal:2',
            'settled_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BanquetEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(BanquetEvent::class, 'banquet_event_id');
    }
}
