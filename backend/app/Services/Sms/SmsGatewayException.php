<?php

namespace App\Services\Sms;

use RuntimeException;

/**
 * Review 17 M2: reported (report(), so Sentry sees it) when an SMS driver cannot send at all: no API key, the
 * gateway refused the key (401/403), a base URL that is not https. The message names the driver, the setting and
 * the HTTP status only: never the key, the phone or the text.
 */
class SmsGatewayException extends RuntimeException {}
