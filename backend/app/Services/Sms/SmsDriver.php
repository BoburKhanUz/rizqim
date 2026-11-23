<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * The SMS service the app is given: sends through the driver the control centre chose (setting('sms_driver'),
 * Tizim → Sozlamalar): `log` (the default: nothing is sent, the message goes to the server log, for development),
 * `eskiz` (EskizSmsService) or `onlinegroup` (the owner's gateway sms.onlinegroup.uz, OnlinegroupSmsService).
 */
class SmsDriver implements SmsServiceInterface
{
    public const LOG = 'log';

    /** Drivers that send a real SMS: with one of them on, a phone is only taken once proven by a code */
    public const REAL = ['eskiz', 'onlinegroup'];

    public function __construct(
        private readonly EskizSmsService $eskiz,
        private readonly OnlinegroupSmsService $onlinegroup,
    ) {}

    public static function current(): string
    {
        return (string) setting('sms_driver', self::LOG);
    }

    /**
     * Whether codes really reach the phone (the apps' `sms_verification_required`).
     */
    public static function verificationRequired(): bool
    {
        return in_array(self::current(), self::REAL, true);
    }

    public function send(string $phone, string $message): bool
    {
        return match (self::current()) {
            'eskiz' => $this->eskiz->send($phone, $message),
            'onlinegroup' => $this->onlinegroup->send($phone, $message),
            self::LOG => $this->log($phone, $message),
            default => $this->unknown(),
        };
    }

    private function log(string $phone, string $message): bool
    {
        Log::info("SMS Mocked to {$phone}: {$message}");

        return true;
    }

    private function unknown(): bool
    {
        Log::error('SMS: unknown sms_driver setting, nothing sent.');

        return false;
    }
}
