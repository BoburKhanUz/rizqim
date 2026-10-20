<?php

namespace App\Services\Promo;

use RuntimeException;

class InvalidPromoCodeException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
