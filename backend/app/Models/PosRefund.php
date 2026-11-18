<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-18: money given back from a paid POS bill within the shift, with its refund receipt (POS_SPEC 2.10).
 *
 * @property numeric-string $amount
 */
class PosRefund extends Model
{
    use BelongsToStore;
    use \App\Traits\HasPublicUuid;

    /** fiscal_receipts.subject_type of a refund receipt */
    public const FISCAL_SUBJECT = 'pos_refund';

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'uuid', 'store_id', 'branch_id', 'order_id', 'bill_id', 'payment_id', 'shift_id', 'amount', 'method',
        'reason', 'user_id', 'approved_by', 'device_id', 'fiscal_receipt_uuid', 'fiscal_status', 'fiscal_error',
        'created_at', 'updated_at',
    ];

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
