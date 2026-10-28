<?php

namespace App\Enums;

/**
 * P4-21: what a tariff plan lets a store use (docs/BILLING.md, "Funksiyalar"). The one list every check goes through
 * (App\Services\Finance\Billing\Entitlements): the base features every plan has and that are never gated (the till
 * keeps taking payments, printing receipts, running shifts and refunds whatever the plan or the debt), and the gated
 * ones a plan lists in tariff_plans.features.
 */
enum PlanFeature: string
{
    // Base: every plan, never gated
    case HALL = 'hall';
    case ROOM_FEE = 'room_fee';
    case GUEST_ITEMS = 'guest_items';
    case SHIFTS = 'shifts';
    case RIZQIM_ORDERS = 'rizqim_orders';

    // Asosiy
    case BOOKINGS = 'bookings';
    case DEPOSITS = 'deposits';
    case CUSTOMER_ACCOUNTS = 'customer_accounts';
    case KDS = 'kds';
    case SERVICE_CHARGE = 'service_charge';

    // Pro
    case MULTI_BRANCH_REPORTS = 'multi_branch_reports';
    case AGGREGATOR_ORDERS = 'aggregator_orders';
    case BANQUET = 'banquet';

    public function label(): string
    {
        return match ($this) {
            self::HALL => 'Zal va stollar',
            self::ROOM_FEE => 'Xona puli',
            self::GUEST_ITEMS => 'Choy-non',
            self::SHIFTS => 'Smena va Z-hisobot',
            self::RIZQIM_ORDERS => 'RIZQIM buyurtmalari',
            self::BOOKINGS => 'Bron',
            self::DEPOSITS => "Bo'nak (avans)",
            self::CUSTOMER_ACCOUNTS => 'Nasiya',
            self::KDS => 'Oshxona ekrani (KDS)',
            self::SERVICE_CHARGE => 'Xizmat haqi',
            self::MULTI_BRANCH_REPORTS => 'Filiallar hisoboti',
            self::AGGREGATOR_ORDERS => 'Yandex va Uzum buyurtmalari',
            self::BANQUET => 'Banket',
        };
    }

    public function isBase(): bool
    {
        return in_array($this, self::base(), true);
    }

    /**
     * @return list<self>
     */
    public static function base(): array
    {
        return [self::HALL, self::ROOM_FEE, self::GUEST_ITEMS, self::SHIFTS, self::RIZQIM_ORDERS];
    }

    /**
     * @return list<self>
     */
    public static function gated(): array
    {
        return array_values(array_filter(self::cases(), fn (self $feature) => !$feature->isBase()));
    }

    /**
     * @return list<string>
     */
    public static function gatedValues(): array
    {
        return array_map(fn (self $feature) => $feature->value, self::gated());
    }
}
