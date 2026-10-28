<?php

namespace App\Models;

use App\Enums\DunningStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-21: one step of the unpaid-invoice routine (docs/BILLING.md, "Eslatmalar va cheklov"): a reminder, the lock or
 * the unlock of an invoice, at most once each (unique invoice + stage). Written by App\Services\Finance\Billing\
 * DunningService and SubscriptionStatusResolver only.
 *
 * @property int $id
 * @property int $store_id
 * @property int $subscription_invoice_id
 * @property DunningStage $stage
 * @property bool $notified
 * @property int $recipients
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class SubscriptionDunningEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stage' => DunningStage::class,
            'notified' => 'boolean',
            'recipients' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SubscriptionInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    /**
     * @return array{stage: string, label: string, notified: bool, recipients: int, occurred_at: string, invoice: string|null}
     */
    public function toPublicArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'label' => $this->stage->label(),
            'notified' => $this->notified,
            'recipients' => $this->recipients,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'invoice' => $this->invoice?->number,
        ];
    }
}
