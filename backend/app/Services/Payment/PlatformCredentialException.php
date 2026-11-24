<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision 1: a store's own-account credential equal to the platform's (PlatformCredentialGuard). A 422 like any
 * validation error, with `code` and `data.reason` platform_credential and `data.fields` (the field names, never a
 * value: bootstrap/app.php); each field carries the same message in Uzbek, which the partner panel replaces with its
 * own text in the panel's language (uz/ru/en) by the reason.
 */
class PlatformCredentialException extends ValidationException
{
    public const CODE = 'platform_credential';

    public readonly string $reason;

    /**
     * @param  list<string>  $fields  "payment_settings.<provider>.<field>"
     */
    public function __construct(public readonly array $fields)
    {
        $validator = Validator::make([], []);
        foreach ($fields as $field) {
            $validator->errors()->add($field, self::message($field));
        }
        $this->reason = self::CODE;

        parent::__construct($validator);
    }

    public static function message(string $field): string
    {
        $provider = ucfirst(explode('.', $field)[1] ?? '');

        return "Bu RIZQIM platformasining {$provider} kaliti: do'kon o'z hisobi uchun uni kirita olmaydi (mijozlar puli platforma hisobiga tushib qoladi). {$provider} kabinetingizdagi o'z ma'lumotlaringizni kiriting.";
    }
}
