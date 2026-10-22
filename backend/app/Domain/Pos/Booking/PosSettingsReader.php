<?php

namespace App\Domain\Pos\Booking;

use App\Models\Branch;
use App\Models\Store;

/**
 * The few settings bookings and accounts read from columns other tasks own: the branch's POS settings (P3-13,
 * branches.pos_settings) and the store's fiscal settings (P3-11, stores.fiscal_settings). Read as plain JSON, so a
 * missing column or key falls back to config/pos_bookings.php.
 *
 * Keys read (all optional):
 *  - branches.pos_settings.booking_slot_minutes (int): the default booking length of the branch;
 *  - stores.fiscal_settings.deposit_refund_receipt (bool, default false): a REFUND receipt when a deposit is given
 *    back. UNVERIFIED (research report §5: the accountant's advice is no receipt, a refund act instead);
 *  - stores.fiscal_settings.retained_deposit_receipt (bool, default true): a SALE receipt (booking service) for a
 *    retained deposit. UNVERIFIED (research report T3).
 */
final class PosSettingsReader
{
    public static function slotMinutes(Branch $branch): int
    {
        $value = self::json($branch->getAttribute('pos_settings'))['booking_slot_minutes'] ?? null;
        $minutes = is_numeric($value) ? (int) $value : (int) config('pos_bookings.default_slot_minutes', 300);

        return max(30, min($minutes, (int) config('pos_bookings.max_slot_minutes', 1440)));
    }

    /**
     * deposit_refund_receipt or retained_deposit_receipt: the store's own choice, else config/pos_bookings.php.
     */
    public static function fiscalFlag(Store $store, string $key): bool
    {
        $value = self::json($store->getAttribute('fiscal_settings'))[$key] ?? null;

        return is_bool($value) ? $value : (bool) config("pos_bookings.{$key}", false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
