<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Go-live review M2: a platform payment provider whose merchant ids are not set (neither in Tizim → To'lov tizimlari
 * nor in .env) would build a checkout link Click / Payme cannot open. Checkout and store top-ups refuse it before any
 * row is created: a 422 like any validation error, with `code` and `data.reason` payment_provider_unconfigured
 * (bootstrap/app.php), the same shape as payment_provider_inactive.
 */
class PaymentProviderUnconfiguredException extends ValidationException
{
    public const CODE = 'payment_provider_unconfigured';

    public readonly string $reason;

    /**
     * @param  list<string>  $missing  the credential names (never their values)
     */
    public function __construct(public readonly string $method, public readonly array $missing, string $field = 'payment_method')
    {
        $validator = Validator::make([], []);
        $validator->errors()->add($field, self::message($method));
        $this->reason = self::CODE;

        parent::__construct($validator);
    }

    public static function message(string $method): string
    {
        return ucfirst($method) . " orqali to'lov hozircha sozlanmagan. Boshqa to'lov usulini tanlang.";
    }
}
