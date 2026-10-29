<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * P4-32: checkout with a payment provider the RIZQIM team switched off (PaymentProviderSwitch). A 422 like any
 * validation error (`errors.payment_method`), with `code` and `data.reason` payment_provider_inactive (bootstrap/app.php).
 */
class PaymentProviderInactiveException extends ValidationException
{
    public readonly string $reason;

    public function __construct(public readonly string $method)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('payment_method', PaymentProviderSwitch::inactiveMessage($method));
        $this->reason = PaymentProviderSwitch::INACTIVE_CODE;

        parent::__construct($validator);
    }

    public static function for(string $method): self
    {
        return new self($method);
    }
}
