<?php

namespace App\Enums;

enum PaymentAccountType: string
{
    case OWN_ACCOUNT = 'own_account';
    case PLATFORM_ACCOUNT = 'platform_account';
}
