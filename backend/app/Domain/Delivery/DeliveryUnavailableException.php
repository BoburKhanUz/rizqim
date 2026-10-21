<?php

namespace App\Domain\Delivery;

use App\Support\Money;
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
    /** Checkout (P1-32): the branch the customer chose serves none of the zones that cover the point */
    public const BRANCH_OUTSIDE_ZONE = 'branch_outside_zone';
    /** Checkout (P1-32): the cart is below the zone's minimum order */
    public const BELOW_MIN_ORDER = 'below_minimum_order';

    /**
     * @param array<string, mixed> $context extra machine-readable fields for the response (`data`), next to `reason`
     */
    private function __construct(public readonly string $reason, string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }

    /**
     * The `data` of the 422 response: the reason, plus the context (e.g. the minimum order).
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['reason' => $this->reason] + $this->context;
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

    public static function branchOutsideZone(): self
    {
        return new self(self::BRANCH_OUTSIDE_ZONE, "Tanlangan filial bu manzilga yetkazib bermaydi. Boshqa filialni tanlang yoki filialni tanlamasdan buyurtma bering.");
    }

    public static function belowMinOrder(Money $minOrderAmount, Money $subtotal): self
    {
        return new self(
            self::BELOW_MIN_ORDER,
            "Bu hududda eng kam buyurtma summasi: " . self::uzs($minOrderAmount) . " UZS.",
            ['min_order_amount' => $minOrderAmount->toString(), 'subtotal' => $subtotal->toString()],
        );
    }

    /**
     * "30 000" (tiyin shown only when there are any: "30 000.50"), without going through floats.
     */
    private static function uzs(Money $amount): string
    {
        [$whole, $fraction] = explode('.', $amount->toString());
        $sign = str_starts_with($whole, '-') ? '-' : '';
        $grouped = strrev(implode(' ', str_split(strrev(ltrim($whole, '-')), 3)));

        return $sign . $grouped . ($fraction === '00' ? '' : ".{$fraction}");
    }
}
