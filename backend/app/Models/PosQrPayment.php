<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
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
 * @property string|null $uzqr_api_key Wave P review: the till's UzQR key that created it (encrypted at rest; never returned)
 * @property \Illuminate\Support\Carbon|null $next_check_at when the operator is asked again about an ended UzQR (null: settled)
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property int $check_failures asks in a row the operator did not answer
 * @property \Illuminate\Support\Carbon|null $operator_cancelled_at the operator acknowledged the cancel
 * @property \Illuminate\Support\Carbon|null $alerted_at the owner was told it is still unsettled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PosQrPayment extends Model
{
    use BelongsToStore;
    use \App\Traits\HasPublicUuid;

    /** The account Click (`merchant_trans_id`) and Payme (`account.order_id`) carry for a POS QR. */
    public const ACCOUNT_PREFIX = 'pos_qr_';

    // Wave M1: every column but id, listed (a $guarded list made Eloquent read the table's columns once per request).
    // A new column must be added here, or mass assignment drops it: ModelFillableTest fails until it is.
    protected $fillable = [
        'uuid', 'store_id', 'branch_id', 'order_id', 'bill_id', 'bill_uuid', 'provider', 'driver', 'amount', 'status',
        'checkout_payload', 'gateway_reference', 'provider_transaction_id', 'provider_payload', 'expires_at',
        'held_at', 'paid_at', 'cancelled_at', 'cancel_reason', 'refunded_amount', 'refunded_at', 'payment_id',
        'shift_id', 'device_id', 'created_by', 'created_at', 'updated_at',
        'uzqr_api_key', 'next_check_at', 'last_checked_at', 'check_failures', 'operator_cancelled_at', 'alerted_at',
    ];

    /** Wave P review: the UzQR key it was made with never leaves the server. */
    protected $hidden = ['uzqr_api_key'];

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
            'uzqr_api_key' => 'encrypted',
            'next_check_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'check_failures' => 'integer',
            'operator_cancelled_at' => 'datetime',
            'alerted_at' => 'datetime',
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
