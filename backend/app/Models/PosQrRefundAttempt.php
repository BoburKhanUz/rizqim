<?php

namespace App\Models;

use App\Domain\Pos\QrPayments\QrRefundStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Review P6W4 C2: one refund of a provider-confirmed QR payment, recorded before the provider is asked
 * (App\Domain\Pos\QrPayments\QrRefunds). Its uuid becomes the PosRefund's.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int $qr_payment_id
 * @property int $payment_id
 * @property int $order_id
 * @property int $bill_id
 * @property int $shift_id
 * @property numeric-string $amount
 * @property string $reason
 * @property int $user_id
 * @property int|null $approved_by
 * @property int|null $device_id
 * @property QrRefundStatus $status
 * @property string|null $message
 * @property string|null $reference
 * @property int|null $refund_id
 * @property int $provider_calls
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PosQrRefundAttempt extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => QrRefundStatus::class,
            'amount' => 'decimal:2',
            'store_id' => 'integer',
            'qr_payment_id' => 'integer',
            'payment_id' => 'integer',
            'order_id' => 'integer',
            'bill_id' => 'integer',
            'shift_id' => 'integer',
            'user_id' => 'integer',
            'approved_by' => 'integer',
            'device_id' => 'integer',
            'refund_id' => 'integer',
            'provider_calls' => 'integer',
            'checked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
