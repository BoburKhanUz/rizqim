<?php

namespace App\Services\Admin\System;

use App\Services\Sms\EskizSmsService;
use App\Services\Sms\OnlinegroupSmsService;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsFailures;

/**
 * Tizim → Sozlamalar → SMS, "Sinov SMS yuborish": one fixed text (TEXT, never the admin's own: the button cannot be
 * used to message people) to a phone the admin gives, through the SAVED settings, so the owner sees whether the key
 * works without a store and a sign-in.
 *
 * Which provider (provider()): the saved sms_driver (SmsDriver::current(), an empty value is `log`) when it sends real
 * SMS (onlinegroup: with its API key and an https gateway address; eskiz: with its email and password). With SMS off
 * (log) a saved Onlinegroup key is checked through Onlinegroup, so the key can be tried before switching sign-in codes
 * over to it; such a check is not counted for the `sms_failures` alert (codes do not use that key yet). Anything else
 * is `sms_not_configured`.
 *
 * A failure is said in plain words from the reason the driver recorded (SmsFailures): the key refused, the provider's
 * limit, the data refused, no answer / 5xx, or an answer that is not the provider's message. The providers log as
 * they always do (the phone masked, never the key or the text); this class logs nothing.
 */
final class SmsTestSender
{
    public const TEXT = 'RIZQIM: sinov xabari. SMS sozlamalari ishlayapti.';

    /** Short-lived: the message is only a check */
    private const TTL_SECONDS = 600;

    /** SmsFailures reason => [code, message] */
    private const FAILURES = [
        SmsFailures::CONFIG => ['sms_key_refused', 'SMS xizmati kalitni (yoki email va parolni) qabul qilmadi. Ularni tekshiring yoki almashtiring (Onlinegroup kalitiga «sms:send» ruxsati kerak).'],
        SmsFailures::RATE_LIMITED => ['sms_provider_rate_limited', "SMS xizmati limitga yetdi. Birozdan so'ng qayta urinib ko'ring."],
        SmsFailures::REFUSED => ['sms_provider_refused', 'SMS xizmati xabarni qabul qilmadi. Telefon raqamini tekshiring.'],
        SmsFailures::UNAVAILABLE => ['sms_provider_unavailable', "SMS xizmati javob bermadi. Birozdan so'ng qayta urinib ko'ring."],
    ];

    private const FAILED = ['sms_send_failed', 'SMS yuborilmadi: xizmat kutilmagan javob qaytardi. Server jurnalini tekshiring.'];

    public function __construct(
        private readonly OnlinegroupSmsService $onlinegroup,
        private readonly EskizSmsService $eskiz,
    ) {}

    /**
     * The provider a test goes through with the saved settings.
     *
     * @throws SystemAdminException 422 sms_not_configured, saying what is missing
     */
    public function provider(): string
    {
        $driver = SmsDriver::current();
        $onlinegroupKey = !blank(trim((string) setting('onlinegroup_api_key')));

        if ($driver === 'eskiz') {
            if (blank(setting('eskiz_email')) || blank(setting('eskiz_password'))) {
                throw self::notConfigured('SMS sozlanmagan: Eskiz uchun email va parolni kiriting.');
            }

            return 'eskiz';
        }

        if (!in_array($driver, ['onlinegroup', SmsDriver::LOG], true) || !$onlinegroupKey) {
            throw self::notConfigured();
        }

        $base = rtrim((string) config('services.onlinegroup.base_url', 'https://sms.onlinegroup.uz'), '/');
        if (!OnlinegroupSmsService::safeBaseUrl($base)) {
            throw self::notConfigured("SMS sozlanmagan: Onlinegroup manzili (ONLINEGROUP_SMS_BASE_URL) https emas, kalit bunday manzilga yuborilmaydi. Server sozlamasini tuzating.");
        }

        return 'onlinegroup';
    }

    /**
     * Sends TEXT to `$phone` (already +998XXXXXXXXX) through `$provider` (from provider()).
     *
     * @throws SystemAdminException the provider did not take it (code and message for the admin; data.driver)
     */
    public function send(string $provider, string $phone): void
    {
        $failures = app(SmsFailures::class);
        $failures->clearLast();

        $send = fn (): bool => match ($provider) {
            'onlinegroup' => $this->onlinegroup->send($phone, self::TEXT, self::TTL_SECONDS),
            'eskiz' => $this->eskiz->send($phone, self::TEXT, self::TTL_SECONDS),
            default => false,
        };
        // A key check while SMS is off: sign-in codes do not depend on it, so no alert
        $sent = SmsDriver::current() === SmsDriver::LOG ? $failures->uncounted($send) : $send();

        if ($sent) {
            return;
        }

        $last = $failures->last();
        [$code, $message] = $last !== null ? (self::FAILURES[$last] ?? self::FAILED) : self::FAILED;

        throw new SystemAdminException($message, $code, 422, ['driver' => $provider]);
    }

    public static function notConfigured(?string $message = null): SystemAdminException
    {
        return new SystemAdminException(
            $message ?? 'SMS sozlanmagan: avval yuborish usulini tanlang va uning kalitini kiriting (Onlinegroup — API kaliti, Eskiz — email va parol).',
            'sms_not_configured',
        );
    }
}
