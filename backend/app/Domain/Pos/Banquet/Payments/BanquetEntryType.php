<?php

namespace App\Domain\Pos\Banquet\Payments;

/**
 * A movement of money against a banquet event's schedule (P6-53): a payment taken at the till (ADVANCE receipt), a
 * bank transfer the manager recorded (no shift, no receipt), a refund on a cancellation (REFUND receipt) or a
 * retained part (SALE receipt of the cancellation service paid by the advance).
 */
enum BanquetEntryType: string
{
    case PAYMENT = 'payment';
    case TRANSFER = 'transfer';
    case REFUND = 'refund';
    case RETAIN = 'retain';

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT => "To'lov (avans)",
            self::TRANSFER => "Bank o'tkazmasi",
            self::REFUND => 'Qaytarildi',
            self::RETAIN => 'Ushlab qolindi',
        };
    }

    /**
     * Money received for the event (paid toward its total).
     */
    public function isReceipt(): bool
    {
        return $this === self::PAYMENT || $this === self::TRANSFER;
    }
}
