<?php

namespace App\Services\Auth;

/**
 * P4-25: a Telegram id or phone that only a superadmin / RIZQIM team account holds. Those accounts sign in through the
 * control center (the admin API), never by Telegram or SMS code, so no store or customer token is issued. Answered with
 * 403 `platform_account`; nothing is created or changed.
 */
class PlatformAccountSignInException extends \RuntimeException
{
    public const REASON = 'platform_account';

    public function __construct()
    {
        parent::__construct("Bu hisob uchun Telegram yoki telefon orqali kirish yopiq. Boshqaruv markazidan kiring.");
    }
}
