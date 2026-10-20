<?php

namespace App\Domain\Delivery;

use RuntimeException;

/**
 * No delivery to a point: `reason` is a stable code clients can branch on, the message is Uzbek and fit to show.
 */
class DeliveryUnavailableException extends RuntimeException
{
    /** The store has delivery zones, none of them covers the point */
    public const OUTSIDE_ZONE = 'outside_zone';
    /** The store has not drawn any delivery zone yet */
    public const NO_ZONES = 'zones_not_configured';
    /** The zone is covered, but no branch that serves it is open now */
    public const BRANCH_CLOSED = 'branch_closed';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function outsideZone(): self
    {
        return new self(self::OUTSIDE_ZONE, "Manzil yetkazib berish hududidan tashqarida: bu joy hududdan tashqarida.");
    }

    public static function noZones(): self
    {
        return new self(self::NO_ZONES, "Bu do'kon uchun yetkazib berish hududlari hali sozlanmagan.");
    }

    public static function branchClosed(): self
    {
        return new self(self::BRANCH_CLOSED, "Bu hududga xizmat ko'rsatadigan filial hozir yopiq.");
    }
}
