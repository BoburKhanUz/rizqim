<?php

namespace App\Domain\Pos\QrPayments\UzQr;

/**
 * Wave P: the UzQR operator did not do what was asked. `transient`: no answer, a timeout, a 5xx or a 429 (it may have
 * done it: ask again later); otherwise it said no (`code` is its error code, or `http_<status>`). The message never
 * holds the key or the request body.
 */
final class UzQrOperatorException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly bool $transient)
    {
        parent::__construct($message);
    }
}
