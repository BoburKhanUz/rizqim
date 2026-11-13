<?php

namespace App\Models;

use App\Domain\Pos\QrPayments\QrPaymentStatus;
use App\Domain\Pos\QrPayments\QrProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * P6-41/P6-42: a dynamic QR shown at the till for a bill (App\Domain\Pos\QrPayments\QrPaymentService).
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property int $order_id
 * @property int $bill_id
 * @property string $bill_uuid
 * @property QrProvider $provider
 * @property string $driver
 * @property numeric-string $amount
 * @property QrPaymentStatus $status
 * @property string $checkout_payload
 * @property string|null $gateway_reference
 * @property string|null $provider_transaction_id
 * @property array<string, mixed>|null $provider_payload
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $held_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property numeric-string $refunded_amount
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property int|null $payment_id
 * @property int|null $shift_id
 * @property int|null $device_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PosQrPayment extends Model
{
    use \App\Traits\HasPublicUuid;

    /** The account Click (`merchant_trans_id`) and Payme (`account.order_id`) carry for a POS QR. */
    public const ACCOUNT_PREFIX = 'pos_qr_';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'provider' => QrProvider::class,
            'status' => QrPaymentStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'provider_payload' => 'array',
            'expires_at' => 'datetime',
            'held_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'payment_id' => 'integer',
            'shift_id' => 'integer',
            'device_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Order, $this>
     */
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function account(): string
    {
        return self::ACCOUNT_PREFIX . $this->uuid;
    }

    /**
     * The request a provider's account reference names (`pos_qr_<uuid>`), or null.
     */
    public static function forAccount(string $reference): ?self
    {
        if (!str_starts_with($reference, self::ACCOUNT_PREFIX)) {
            return null;
        }
        $uuid = substr($reference, strlen(self::ACCOUNT_PREFIX));

        return \Illuminate\Support\Str::isUuid($uuid) ? self::where('uuid', $uuid)->first() : null;
    }

    public static function isAccount(string $reference): bool
    {
        return str_starts_with($reference, self::ACCOUNT_PREFIX);
    }
}
