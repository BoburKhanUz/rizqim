<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;

/**
 * Removes personal data and secrets from everything Sentry is about to send (config/sentry.php: before_send,
 * before_send_transaction, before_breadcrumb). Two passes: by KEY (Authorization header, initData, OTP code, phone,
 * payment keys, card number, tokens...) and by VALUE (Bearer/Sanctum tokens, Telegram initData strings, Uzbek and
 * international phone numbers, card numbers that pass the Luhn check) so a secret that ended up inside an
 * exception message or a SQL string is masked as well.
 *
 * Static methods only: config/sentry.php stores [SentryScrubber::class, '...'] callables, which survive
 * `php artisan config:cache` (closures do not).
 */
final class SentryScrubber
{
    public const MASK = '[Filtered]';

    /** A key containing any of these (lower-cased, letters and digits only) is masked. */
    private const KEY_FRAGMENTS = [
        'authorization', 'cookie', 'token', 'secret', 'password', 'passwd', 'initdata', 'phone', 'card', 'cvv', 'cvc',
        'apikey', 'privatekey', 'signature', 'signstring', 'xsrf', 'csrf', 'otp', 'smscode', 'verificationcode',
        'paymekey', 'clickkey', 'merchantkey', 'tgwebapp',
    ];

    /** A key equal to one of these is masked (too short or too common to match as a fragment). */
    private const KEY_EXACT = ['code', 'pan', 'key', 'hash', 'sign', 'auth', 'pin', 'expiry', 'exp'];

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        return self::scrubEvent($event);
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): Event
    {
        $event = self::scrubEvent($event);

        foreach ($event->getSpans() as $span) {
            if ($span->getDescription() !== null) {
                $span->setDescription(self::scrubString($span->getDescription()));
            }
            $span->setData(self::scrubArray($span->getData()));
        }

        return $event;
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        if ($breadcrumb->getMessage() !== null) {
            $breadcrumb = $breadcrumb->withMessage(self::scrubString($breadcrumb->getMessage()));
        }

        foreach (self::scrubArray($breadcrumb->getMetadata()) as $name => $value) {
            $breadcrumb = $breadcrumb->withMetadata((string) $name, $value);
        }

        return $breadcrumb;
    }

    public static function scrubEvent(Event $event): Event
    {
        $event->setRequest(self::scrubArray($event->getRequest()));
        $event->setExtra(self::scrubArray($event->getExtra()));

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext((string) $name, self::scrubArray($context));
        }

        $tags = [];
        foreach ($event->getTags() as $name => $value) {
            $tags[$name] = self::isSensitiveKey((string) $name) ? self::MASK : self::scrubString((string) $value);
        }
        $event->setTags($tags);

        if ($event->getMessage() !== null) {
            $event->setMessage(
                self::scrubString($event->getMessage()),
                array_map(fn (string $param) => self::scrubString($param), $event->getMessageParams()),
                $event->getMessageFormatted() !== null ? self::scrubString($event->getMessageFormatted()) : null,
            );
        }

        if ($event->getTransaction() !== null) {
            $event->setTransaction(self::scrubString($event->getTransaction()));
        }

        $exceptions = array_map(function (ExceptionDataBag $exception) {
            return $exception->setValue(self::scrubString($exception->getValue()));
        }, $event->getExceptions());
        $event->setExceptions($exceptions);

        $event->setBreadcrumb(array_map(
            fn (Breadcrumb $breadcrumb) => self::beforeBreadcrumb($breadcrumb),
            $event->getBreadcrumbs(),
        ));

        // Only the id identifies the user; email, username and IP address are personal data
        $user = $event->getUser();
        if ($user !== null) {
            $event->setUser(UserDataBag::createFromUserIdentifier($user->getId()));
        }

        return $event;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function scrubArray(array $data, int $depth = 0): array
    {
        if ($depth > 12) {
            return [];
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $clean[$key] = self::MASK;
            } elseif (is_array($value)) {
                $clean[$key] = self::scrubArray($value, $depth + 1);
            } elseif (is_string($value)) {
                $clean[$key] = self::scrubString($value, is_string($key) && self::isUrlKey($key));
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    public static function scrubString(string $value, bool $isUrl = false): string
    {
        if ($value === '') {
            return $value;
        }

        // A Mini App URL carries the whole signed initData in its fragment (#tgWebAppData=...)
        if ($isUrl || str_contains($value, 'tgWebApp')) {
            $value = (string) preg_replace('/#.*$/s', '#'.self::MASK, $value);
        }

        // Telegram initData as a query string: query_id=...&user=%7B...%7D&auth_date=...&hash=...
        if (str_contains($value, 'auth_date=') || str_contains($value, 'tgWebApp')) {
            $value = (string) preg_replace(
                '/(?<![A-Za-z0-9_])(tgWebApp\w*|query_id|user|chat|receiver|start_param|auth_date|signature|hash|chat_instance|chat_type)=[^&\s"\']*/i',
                '$1='.self::MASK,
                $value,
            );
        }

        // Authorization header values and Sanctum personal access tokens (id|40 random chars)
        $value = (string) preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i', '$1 '.self::MASK, $value);
        $value = (string) preg_replace('/\b\d{1,10}\|[A-Za-z0-9]{30,}/', self::MASK, $value);

        // key=value / "key":"value" pairs of sensitive names inside free text (SQL, JSON snippets, query strings)
        $value = (string) preg_replace(
            '/(["\']?\b(?:password|passwd|secret|token|api[_-]?key|authorization|init_?data|otp|sms_?code|verification_?code|secret_key|sign_string|card_?number|cvv|phone(?:_number)?)["\']?\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,&;)}\]]+)/i',
            '$1'.self::MASK,
            $value,
        );

        // A one-time code written out in text: "code 123456", "otp: 4821", "kod=1234"
        $value = (string) preg_replace('/\b(code|kod|otp|sms)(\s*[:=#]?\s*)\d{4,8}\b/i', '$1$2'.self::MASK, $value);

        // Phone numbers: +998 90 123-45-67, 998901234567, any +country-code number
        $value = (string) preg_replace('/(?<![\w.])\+?998[\s\-()]*(?:\d[\s\-()]*){9}(?!\w)/', self::MASK, $value);
        $value = (string) preg_replace('/(?<![\w.])\+\d(?:[\s\-()]*\d){8,14}(?!\w)/', self::MASK, $value);

        // Card numbers: 13-19 digits (spaces/dashes allowed) that pass the Luhn check, so order ids and timestamps stay
        $value = (string) preg_replace_callback('/(?<![\w.])\d(?:[ \-]?\d){12,18}(?!\w)/', function (array $match) {
            return self::passesLuhn($match[0]) ? self::MASK : $match[0];
        }, $value);

        return $value;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $key));

        if (in_array($normalized, self::KEY_EXACT, true)) {
            return true;
        }

        foreach (self::KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function isUrlKey(string $key): bool
    {
        return in_array(strtolower($key), ['url', 'to', 'from', 'referer', 'referrer', 'origin', 'href'], true);
    }

    private static function passesLuhn(string $number): bool
    {
        $digits = (string) preg_replace('/\D/', '', $number);
        $length = strlen($digits);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
