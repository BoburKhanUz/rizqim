<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Tracing\Span;
use Sentry\UserDataBag;

/**
 * Removes personal data and secrets from everything Sentry is about to send (config/sentry.php: before_send,
 * before_send_transaction, before_breadcrumb). Two passes: by KEY (Authorization header, initData, OTP code, phone,
 * payment keys, card number, tokens...) and by VALUE (Bearer/Sanctum tokens, Telegram bot tokens in Bot API URLs,
 * Telegram initData strings, Uzbek and international phone numbers, card numbers that pass the Luhn check) so a
 * secret that ended up inside an exception message or a SQL string is masked as well.
 *
 * Two more rules, so that a report says nothing about who the customer is (privacy policy s8, docs/legal/README.md):
 * - the incoming request keeps only its method and its URL without query string and fragment (no headers, cookies,
 *   body or URL parameters), and HTTP breadcrumbs and spans lose their query string (Nominatim ?q=<address>). The SDK
 *   already collects none of this with config/sentry.php `data_collection`; this is the second line of defence;
 * - breadcrumb data (log context: Log::warning('...', ['order_id' => ...])) masks the keys that point at a person:
 *   account, order and store ids, names, addresses, coordinates and search text (BREADCRUMB_KEY_*). Messages stay.
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

    /** Breadcrumb data keys equal to one of these (normalized as in isSensitiveKey) are masked as well. */
    private const BREADCRUMB_KEY_EXACT = [
        'userid', 'customerid', 'orderid', 'storeid', 'courierid', 'staffid', 'chatid', 'telegramid', 'tgid', 'tguserid',
        'name', 'firstname', 'lastname', 'fullname', 'username', 'lat', 'lng', 'lon', 'latitude', 'longitude',
        'location', 'coords', 'coordinates', 'q', 'query', 'search', 'searchquery', 'term',
    ];

    /** ... and so are breadcrumb data keys containing one of these. */
    private const BREADCRUMB_KEY_FRAGMENTS = ['address', 'email', 'comment'];

    /** Data keys of HTTP breadcrumbs and spans that hold a URL: their query string and fragment are dropped. */
    private const HTTP_URL_KEYS = ['url', 'url.full', 'http.url'];

    /** The only parts of the incoming request that are kept. */
    private const REQUEST_KEYS = ['url', 'method'];

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        return self::scrubEvent($event);
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): Event
    {
        $event = self::scrubEvent($event);

        foreach ($event->getSpans() as $span) {
            if ($span->getDescription() !== null) {
                $span->setDescription(self::withoutUrlQueries(self::scrubString($span->getDescription())));
            }
            self::replaceSpanData($span, self::withoutHttpQuery(self::scrubArray($span->getData())));
        }

        return $event;
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        if ($breadcrumb->getMessage() !== null) {
            $breadcrumb = $breadcrumb->withMessage(self::scrubString($breadcrumb->getMessage()));
        }

        $metadata = self::withoutHttpQuery(self::scrubArray($breadcrumb->getMetadata(), 0, true));

        // withMetadata() cannot remove a key (http.query), so the breadcrumb is rebuilt
        return new Breadcrumb(
            $breadcrumb->getLevel(),
            $breadcrumb->getType(),
            $breadcrumb->getCategory(),
            $breadcrumb->getMessage(),
            $metadata,
            $breadcrumb->getTimestamp(),
        );
    }

    public static function scrubEvent(Event $event): Event
    {
        $request = array_intersect_key($event->getRequest(), array_flip(self::REQUEST_KEYS));
        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = self::withoutQuery($request['url']);
        }
        $event->setRequest(self::scrubArray($request));
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
    public static function scrubArray(array $data, int $depth = 0, bool $breadcrumb = false): array
    {
        if ($depth > 12) {
            return [];
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && (self::isSensitiveKey($key) || ($breadcrumb && self::isIdentifyingKey($key)))) {
                $clean[$key] = self::MASK;
            } elseif (is_array($value)) {
                $clean[$key] = self::scrubArray($value, $depth + 1, $breadcrumb);
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

        // Telegram Bot API URLs carry the bot token in the path: https://api.telegram.org/bot<id>:<secret>/sendMessage
        // (http_client breadcrumbs and spans, "cURL error 28 ... for <url>" exception messages)
        $value = (string) preg_replace('~/bot\d+(?::|%3A)[A-Za-z0-9_-]{20,}~i', '/bot'.self::MASK, $value);

        // Authorization header values and Sanctum personal access tokens (id|40 random chars)
        $value = (string) preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i', '$1 '.self::MASK, $value);
        $value = (string) preg_replace('/\b\d{1,10}\|[A-Za-z0-9]{30,}/', self::MASK, $value);

        // key=value / "key":"value" pairs of sensitive names inside free text (SQL, JSON snippets, query strings)
        $value = (string) preg_replace(
            '/(["\']?\b(?:password|passwd|secret|token|api[_-]?key|authorization|init_?data|otp|sms_?code|verification_?code|secret_key|sign_string|card_?number|cvv|phone(?:_number)?)["\']?\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,&;)}\]]+)/i',
            '$1'.self::MASK,
            $value,
        );

        // A one-time code written out in text: "code 123456", "otp: 4821", "kod=1234", "code is 4821", and the
        // Uzbek forms of our own SMS (review 17): "kodi: 482913", "kodingiz 4821", "kodni 4821", "parol: 4821"
        $value = (string) preg_replace('/\b(code(?:\s+is)?|kod(?:i|ingiz|ni|ini|ingizni)?|otp|sms|parol(?:i|ingiz)?)(\s*[:=#\-]?\s*)\d{4,8}\b/iu', '$1$2'.self::MASK, $value);

        // Onlinegroup SMS gateway keys (sk_ and random characters)
        $value = (string) preg_replace('/\bsk_[A-Za-z0-9]{16,}/', self::MASK, $value);

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

    /** A breadcrumb data key that points at a customer: account/order/store id, name, address, location, search text. */
    public static function isIdentifyingKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $key));

        if (in_array($normalized, self::BREADCRUMB_KEY_EXACT, true)) {
            return true;
        }

        foreach (self::BREADCRUMB_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Span::setData() only merges, so a removed key (http.query) would survive; the data is replaced instead.
     *
     * @param  array<string, mixed>  $data
     */
    private static function replaceSpanData(Span $span, array $data): void
    {
        \Closure::bind(function (array $data): void {
            $this->data = $data;
        }, $span, Span::class)($data);
    }

    /** The URL without query string and fragment. */
    public static function withoutQuery(string $url): string
    {
        return (string) preg_replace('/[?#].*$/s', '', $url);
    }

    /** Every http(s) URL inside the text without query string and fragment ("GET https://host/search?q=..."). */
    private static function withoutUrlQueries(string $text): string
    {
        return (string) preg_replace('~(https?://[^\s?#"\']*)[?#][^\s"\']*~i', '$1', $text);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function withoutHttpQuery(array $data): array
    {
        unset($data['http.query'], $data['http.fragment']);

        foreach (self::HTTP_URL_KEYS as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = self::withoutQuery($data[$key]);
            }
        }

        return $data;
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
