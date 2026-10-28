<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-23: one reversal of a delivered order's ledger booking (LedgerService::reverseDeliveredOrder and
 * reverseDeliveredOrderShare). `components` holds what this reversal took back of each thing delivery booked
 * (decimal strings): commission_wallet, commission_debt, settlement_wallet, payoff_commission, payoff_invoices
 * {invoice id: amount}; and, for information, added_to_debt (the settlement the wallet no longer held) and
 * invoices_reopened {invoice id: amount}. The next reversal takes only the difference to its target.
 *
 * @property int $id
 * @property int $order_id
 * @property int $store_id
 * @property int|null $order_refund_request_id
 * @property bool $is_full
 * @property numeric-string|null $refunded_total
 * @property numeric-string|null $paid_total
 * @property array<string, mixed> $components
 * @property int|null $performed_by
 */
class OrderLedgerReversal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_full' => 'boolean',
            'refunded_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'components' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
