<?php

namespace App\Domain\Fiscal;

/**
 * fiscal_receipts.status: `pending` until the provider confirms (the POS shows "fiskal kutmoqda" and it is retried),
 * `issued` once registered (or recorded from Payme/Click), `failed` when the provider refused it or every retry ran
 * out (the owner sees it in the panel and may retry by hand).
 */
enum FiscalReceiptStatus: string
{
    case PENDING = 'pending';
    case ISSUED = 'issued';
    case FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
