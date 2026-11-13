<?php

namespace App\Domain\Pos\Vouchers;

/**
 * P6-43: one line of a voucher's history (voucher_entries.type): the sale (the advance), a redemption on a bill, a
 * refund of a voucher-paid bill back to the voucher (never cash), a manager's block or unblock (amount 0).
 */
enum VoucherEntryType: string
{
    case SALE = 'sale';
    case REDEMPTION = 'redemption';
    case REFUND = 'refund';
    case BLOCK = 'block';
    case UNBLOCK = 'unblock';

    public function label(): string
    {
        return match ($this) {
            self::SALE => 'Sotildi',
            self::REDEMPTION => "To'lovda ishlatildi",
            self::REFUND => 'Qaytarishdan qaytdi',
            self::BLOCK => 'Bloklandi',
            self::UNBLOCK => 'Blokdan chiqarildi',
        };
    }
}
