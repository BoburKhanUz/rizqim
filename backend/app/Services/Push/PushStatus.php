<?php

namespace App\Services\Push;

enum PushStatus: string
{
    case SENT = 'sent';
    // No Firebase credentials: the feature is off
    case DISABLED = 'disabled';
    // FCM says the token is dead; it has been cleared from the device
    case INVALID_TOKEN = 'invalid_token';
    // Try again later (rate limit, FCM or network trouble)
    case RETRY = 'retry';
    // FCM refused and trying again will not help (bad payload, missing permission), or the device has no token
    case FAILED = 'failed';
}
