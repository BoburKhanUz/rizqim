<?php

namespace App\Domain\Pos\QrPayments;

/**
 * P6-41/P6-42: who takes the money of a dynamic QR shown at the till. Click and Payme confirm it through their
 * webhooks into the store's own merchant account; UzQR (the national interbank QR) through the store's UzQR gateway
 * (UzQrGateway, P3-32-dependent; a sandbox until then).
 */
enum QrProvider: string
{
    case CLICK = 'click';
    case PAYME = 'payme';
    case UZQR = 'uzqr';

    public function label(): string
    {
        return match ($this) {
            self::CLICK => 'Click',
            self::PAYME => 'Payme',
            self::UZQR => 'UzQR',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $provider) => $provider->value, self::cases());
    }
}
