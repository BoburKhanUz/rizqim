<?php

namespace App\Services\Sms;

use App\Support\PiiMask;
use Illuminate\Support\Facades\Log;

/**
 * The SMS service the app is given: sends through the driver the control centre chose (setting('sms_driver'),
 * Tizim → Sozlamalar): `log` (the default: nothing is sent; in local/testing the message goes to the server log, for
 * development, anywhere else only the masked phone and the `sms_failures` alert),
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

    /**
     * The chosen driver. An empty value (SMS_DRIVER= in .env, or an empty row) is `log`, as the control centre shows
     * it (SystemSettings treats an empty .env value as unset, so the card says "Log").
     */
    public static function current(): string
    {
        $driver = trim((string) setting('sms_driver', self::LOG));

        return $driver === '' ? self::LOG : $driver;
    }

    /**
     * Whether codes really reach the phone (the apps' `sms_verification_required`).
     */
    public static function verificationRequired(): bool
    {
        return in_array(self::current(), self::REAL, true);
    }

    public function send(string $phone, string $message, ?int $ttlSeconds = null): bool
    {
        $failures = app(SmsFailures::class);
        $failures->clearLast();

        $sent = match (self::current()) {
            'eskiz' => $this->eskiz->send($phone, $message, $ttlSeconds),
            'onlinegroup' => $this->onlinegroup->send($phone, $message, $ttlSeconds),
            self::LOG => $this->log($phone, $message),
            default => $this->unknown(),
        };

        // A driver that did not say why (Eskiz) still counts for the `sms_failures` alert
        if (!$sent && $failures->last() === null) {
            $failures->record(SmsFailures::UNAVAILABLE);
        }

        return $sent;
    }

    /**
     * Review 17 L1: in local development, tests and the e2e stacks (APP_ENV=e2e) the whole message is logged (the code is read from the log);
     * anywhere else only the masked phone, never the text, and the `sms_failures` alert says SMS is off.
     */
    private function log(string $phone, string $message): bool
    {
        if (app()->environment('local', 'testing', 'e2e')) {
            Log::info("SMS Mocked to {$phone}: {$message}");

            return true;
        }

        Log::warning('SMS is off (sms_driver = log): nothing was sent.', ['phone' => PiiMask::phone($phone)]);
        app(SmsFailures::class)->record(SmsFailures::DISABLED);

        return true;
    }

    private function unknown(): bool
    {
        Log::error('SMS: unknown sms_driver setting, nothing sent.');
        app(SmsFailures::class)->record(SmsFailures::CONFIG);

        return false;
    }
}
