<?php

namespace App\Services\Finance\Netting;

use App\Models\SubscriptionInvoice;
use App\Support\Money;

/**
 * P4-23: one deduction of a netting: a subscription invoice (or the part of it paid) or the commission debt, with the
 * debt ledger row that booked it (null in a preview).
 */
final class NettingLine
{
    public const INVOICE = 'invoice';

    public const COMMISSION = 'commission';

    public function __construct(
        public readonly string $kind,
        public readonly Money $amount,
        public readonly ?SubscriptionInvoice $invoice = null,
        public readonly ?int $transactionId = null,
    ) {}

    public function label(): string
    {
        return $this->kind === self::INVOICE && $this->invoice !== null
            ? "Obuna hisob-fakturasi {$this->invoice->number} ({$this->invoice->period})"
            : 'Komissiya qarzi';
    }

    /**
     * @return array{kind: string, label: string, amount: string, invoice: array{id: int, uuid: string, number: string, period: string}|null, transaction_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label(),
            'amount' => $this->amount->toString(),
            'invoice' => $this->invoice !== null ? [
                'id' => (int) $this->invoice->id,
                'uuid' => (string) $this->invoice->uuid,
                'number' => (string) $this->invoice->number,
                'period' => (string) $this->invoice->period,
            ] : null,
            'transaction_id' => $this->transactionId,
        ];
    }
}
