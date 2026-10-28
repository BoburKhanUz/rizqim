<?php

namespace App\Services\Finance\Adjustments;

/**
 * P4-23: what a manual adjustment changes. The amount is always positive; the kind says which ledger and which way.
 */
enum AdjustmentKind: string
{
    // Debt up (e.g. a commission that was never booked): the commission part, so it counts against the credit limit
    case DEBT_CHARGE = 'debt_charge';
    // Debt down as a correction of the commission debt (a wrong commission): not a payment of invoices
    case DEBT_CREDIT = 'debt_credit';
    // Money received outside the top-up flow (bank transfer, cash): pays the oldest invoices first, like a top-up
    case DEBT_PAYMENT = 'debt_payment';
    // Wallet up (money RIZQIM owes the store that was not booked)
    case WALLET_CREDIT = 'wallet_credit';
    // Wallet down (booked by mistake); never below zero
    case WALLET_DEBIT = 'wallet_debit';

    public function label(): string
    {
        return match ($this) {
            self::DEBT_CHARGE => "Qarzga qo'shish",
            self::DEBT_CREDIT => 'Komissiya qarzini kamaytirish',
            self::DEBT_PAYMENT => "To'lov qabul qilindi (hisob raqami, naqd)",
            self::WALLET_CREDIT => "Hamyonga qo'shish",
            self::WALLET_DEBIT => 'Hamyondan ayirish',
        };
    }

    public function ledger(): string
    {
        return in_array($this, [self::WALLET_CREDIT, self::WALLET_DEBIT], true) ? 'wallet' : 'debt';
    }

    /**
     * +1 when the ledger's balance goes up (debt up or wallet up), -1 when it goes down.
     */
    public function sign(): int
    {
        return in_array($this, [self::DEBT_CHARGE, self::WALLET_CREDIT], true) ? 1 : -1;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind) => $kind->value, self::cases());
    }
}
