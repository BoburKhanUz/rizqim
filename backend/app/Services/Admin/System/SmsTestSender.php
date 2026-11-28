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
 * Which provider: the saved sms_driver when it sends real SMS (onlinegroup: with its API key; eskiz: with its email and
 * password). With SMS off (log) a saved Onlinegroup key is checked through Onlinegroup, so the key can be tried before
 * switching sign-in codes over to it. Anything else is `sms_not_configured`.
 *
 * A failure is said in plain words from the reason the driver recorded (SmsFailures): the key refused (401/403), the
 * gateway's limit (429), the data refused (422), no answer / 5xx, or an answer that is not the gateway's message.
 * The providers log as they always do (Onlinegroup: the phone masked, never the key or the text); this class logs
 * nothing. A failed test is also counted for the `sms_failures` alert, like any send that did not go out.
 */
final class SmsTestSender
{
    public const TEXT = 'RIZQIM: sinov xabari. SMS sozlamalari ishlayapti.';

    /** Short-lived: the message is only a check */
    private const TTL_SECONDS = 600;

    /** SmsFailures reason => [code, message] */
    private const FAILURES = [
        SmsFailures::CONFIG => ['sms_key_refused', "SMS xizmati API kalitini qabul qilmadi. Kalitni tekshiring yoki almashtiring (unga «sms:send» ruxsati kerak)."],
        SmsFailures::RATE_LIMITED => ['sms_provider_rate_limited', "SMS xizmati limitga yetdi. Birozdan so'ng qayta urinib ko'ring."],
        SmsFailures::REFUSED => ['sms_provider_refused', "SMS xizmati xabarni qabul qilmadi. Telefon raqamini tekshiring."],
        SmsFailures::UNAVAILABLE => ['sms_provider_unavailable', "SMS xizmati javob bermadi. Birozdan so'ng qayta urinib ko'ring."],
    ];

    private const FAILED = ['sms_send_failed', "SMS yuborilmadi: xizmat kutilmagan javob qaytardi. Server jurnalini tekshiring."];

    public function __construct(
        private readonly OnlinegroupSmsService $onlinegroup,
        private readonly EskizSmsService $eskiz,
    ) {}

    /**
     * The provider a test goes through with the saved settings, or null when SMS is not set up.
     */
    public function provider(): ?string
    {
        $onlinegroupKey = !blank(trim((string) setting('onlinegroup_api_key')));

        return match (SmsDriver::current()) {
            'onlinegroup' => $onlinegroupKey ? 'onlinegroup' : null,
            'eskiz' => !blank(setting('eskiz_email')) && !blank(setting('eskiz_password')) ? 'eskiz' : null,
            SmsDriver::LOG => $onlinegroupKey ? 'onlinegroup' : null,
            default => null,
        };
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

        $sent = match ($provider) {
            'onlinegroup' => $this->onlinegroup->send($phone, self::TEXT, self::TTL_SECONDS),
            'eskiz' => $this->eskiz->send($phone, self::TEXT, self::TTL_SECONDS),
            default => false,
        };

        if ($sent) {
            return;
        }

        $last = $failures->last();
        if ($last === null) {
            // A driver that does not say why (Eskiz) still counts for the `sms_failures` alert
            $failures->record(SmsFailures::UNAVAILABLE);
        }
        [$code, $message] = $last !== null ? (self::FAILURES[$last] ?? self::FAILED) : self::FAILED;

        throw new SystemAdminException($message, $code, 422, ['driver' => $provider]);
    }

    public static function notConfigured(): SystemAdminException
    {
        return new SystemAdminException(
            "SMS sozlanmagan: avval yuborish usulini tanlang va uning kalitini kiriting (Onlinegroup — API kaliti, Eskiz — email va parol).",
            'sms_not_configured',
        );
    }
}
