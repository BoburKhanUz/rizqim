<?php

namespace App\Enums;

/**
 * P6 stage B: which POS app a terminal runs, as the app says when it pairs (POST /devices/pair `app`). A waiter's phone
 * and a cashier till are both `pos` terminals; the branch's default LAN hub is a cashier till, never a waiter's phone
 * (App\Domain\Pos\Lan\BranchLan). Devices paired before this was recorded have none (null).
 *
 * P6 review (S4): the apps also say it on every request (HEADER); GET /pos/bootstrap and GET /pos/lan record it on a
 * terminal that has none, and a terminal saying it runs another app than the cashier's never gets the hub's role.
 */
enum DeviceApp: string
{
    /** The request header the POS apps send their app in (rizqim_api RizqimClient `app`). */
    public const HEADER = 'X-Rizqim-App';

    case CASHIER = 'cashier';
    case WAITER = 'waiter';
    case KDS = 'kds';
    case CUSTOMER_DISPLAY = 'customer_display';

    /**
     * The terminal type the app runs on (a code for another type keeps no app: the device is paired as before).
     */
    public function deviceType(): DeviceType
    {
        return match ($this) {
            self::CASHIER, self::WAITER => DeviceType::POS,
            self::KDS => DeviceType::KDS,
            self::CUSTOMER_DISPLAY => DeviceType::DISPLAY,
        };
    }
}
