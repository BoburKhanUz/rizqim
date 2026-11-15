<?php

namespace App\Auth;

use Laravel\Sanctum\Guard;

/**
 * The `sanctum` guard (Mini App, owner/partner panel, staff PIN sessions): Sanctum's own guard with the last_used_at
 * write throttled (ThrottlesLastUsedAt). Registered over Sanctum's driver in AccessServiceProvider.
 */
class SanctumTokenGuard extends Guard
{
    use ThrottlesLastUsedAt;
}
