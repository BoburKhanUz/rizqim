<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-18: money given back from a paid POS bill within the shift, with its refund receipt (POS_SPEC 2.10).
 *
 * @property numeric-string $amount
 */
class PosRefund extends Model
{
    use \App\Traits\HasPublicUuid;

    /** fiscal_receipts.subject_type of a refund receipt */
    public const FISCAL_SUBJECT = 'pos_refund';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(OrderBill::class, 'bill_id');
    }
}
