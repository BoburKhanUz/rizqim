<?php

namespace Tests\Unit;

use App\Support\SentryScrubber;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Tracing\Span;
use Sentry\UserDataBag;
use Tests\TestCase;

class SentryScrubberTest extends TestCase
{
    private const MASK = '[Filtered]';

    public function test_sensitive_keys_are_masked_at_any_depth(): void
    {
        $clean = SentryScrubber::scrubArray([
            'headers' => [
                'Authorization' => ['Bearer 12|abcdefghijklmnopqrstuvwxyz0123456789ABCD'],
                'Cookie' => 'laravel_session=abc',
                'X-XSRF-TOKEN' => 'xyz',
                'X-Telegram-Bot-Api-Secret-Token' => 'wh-secret',
                'User-Agent' => 'Mozilla',
            ],
            'data' => [
                'initData' => 'query_id=AAH&user=%7B%22id%22%3A1%7D&auth_date=1&hash=abc',
                'phone' => '+998901234567',
                'code' => '123456',
                'otp' => '654321',
                'card_number' => '4111 1111 1111 1111',
                'password' => 'hunter2',
                'CLICK_SECRET_KEY' => 'click-key',
                'payme_key' => 'payme-key',
                'cvv' => '123',
                'address' => 'Tashkent',
            ],
        ]);

        $this->assertSame(self::MASK, $clean['headers']['Authorization']);
        $this->assertSame(self::MASK, $clean['headers']['Cookie']);
        $this->assertSame(self::MASK, $clean['headers']['X-XSRF-TOKEN']);
        $this->assertSame(self::MASK, $clean['headers']['X-Telegram-Bot-Api-Secret-Token']);
        $this->assertSame('Mozilla', $clean['headers']['User-Agent']);
        foreach (['initData', 'phone', 'code', 'otp', 'card_number', 'password', 'CLICK_SECRET_KEY', 'payme_key', 'cvv'] as $key) {
            $this->assertSame(self::MASK, $clean['data'][$key], $key);
        }
        $this->assertSame('Tashkent', $clean['data']['address']);
    }

    public function test_secrets_inside_free_text_are_masked(): void
    {
        $text = 'SQLSTATE[23505]: duplicate key (Connection: pgsql, SQL: insert into "users" ("phone") values (+998 90 123-45-67)) '
            .'from 998901234567, token 15|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcd, card 4111 1111 1111 1111, '
            .'Authorization: Bearer abcdef1234567890, "otp":"482913", password=hunter2';

        $clean = SentryScrubber::scrubString($text);

        foreach (['998 90 123-45-67', '998901234567', 'AbCdEfGhIjKl', '4111 1111', 'abcdef1234567890', '482913', 'hunter2'] as $secret) {
            $this->assertStringNotContainsString($secret, $clean, $secret);
        }
        $this->assertStringContainsString('SQLSTATE[23505]', $clean);
    }

    public function test_telegram_init_data_in_a_url_fragment_is_removed(): void
    {
        $url = 'https://food.example.uz/orders?tab=active#tgWebAppData=query_id%3DAAH%26user%3D%257B%2522id%2522%253A1%257D%26auth_date%3D1%26hash%3Dabc&tgWebAppVersion=8.0';

        $clean = SentryScrubber::scrubArray(['url' => $url, 'note' => $url]);

        $this->assertSame('https://food.example.uz/orders?tab=active#'.self::MASK, $clean['url']);
        $this->assertStringNotContainsString('query_id', $clean['note']);
        $this->assertStringNotContainsString('abc', $clean['note']);
    }

    public function test_init_data_query_string_is_masked(): void
    {
        $clean = SentryScrubber::scrubString('POST body: query_id=AAHdF6IQ&user=%7B%22id%22%3A279058397%7D&auth_date=1700000000&hash=deadbeef00');

        $this->assertStringNotContainsString('AAHdF6IQ', $clean);
        $this->assertStringNotContainsString('279058397', $clean);
        $this->assertStringNotContainsString('deadbeef00', $clean);
    }

    public function test_telegram_bot_token_in_bot_api_urls_is_masked(): void
    {
        $token = '7012345678:AAHdF6IQ-xYz_0123456789abcdefABCDEFg';
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $message = SentryScrubber::scrubString("cURL error 28: Operation timed out after 10001 milliseconds for {$url}");
        $this->assertStringNotContainsString('AAHdF6IQ', $message);
        $this->assertStringContainsString('https://api.telegram.org/bot'.self::MASK.'/sendMessage', $message);
        $this->assertStringNotContainsString('AAHdF6IQ', SentryScrubber::scrubString("https://api.telegram.org/file/bot{$token}/photos/1.jpg"));

        // http_client breadcrumb (metadata url) and span (description + data url) as sentry-laravel records them
        $breadcrumb = SentryScrubber::beforeBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_HTTP, 'http', null, [
            'url' => $url, 'http.request.method' => 'POST',
        ]));
        $this->assertSame('https://api.telegram.org/bot'.self::MASK.'/sendMessage', $breadcrumb->getMetadata()['url']);

        $transaction = Event::createTransaction();
        $span = new Span();
        $span->setDescription("POST {$url}");
        $span->setData(['url' => $url]);
        $transaction->setSpans([$span]);
        $sent = SentryScrubber::beforeSendTransaction($transaction);

        $this->assertStringNotContainsString('AAHdF6IQ', json_encode([
            array_map(fn (Span $s) => [$s->getDescription(), $s->getData()], $sent->getSpans()),
        ], JSON_THROW_ON_ERROR));
    }

    public function test_ordinary_numbers_are_not_masked(): void
    {
        $text = 'Order 1700000000123 total 150000 sum, uuid 9f1c2d3e-0000-4000-8000-123456789012, lat 41.311081 lng 69.240562, status 500';

        $this->assertSame($text, SentryScrubber::scrubString($text));
    }

    public function test_luhn_valid_card_numbers_are_masked_but_other_long_numbers_are_not(): void
    {
        $this->assertSame(self::MASK, SentryScrubber::scrubString('8600 4954 7331 6478'));
        $this->assertSame(self::MASK, SentryScrubber::scrubString('4111111111111111'));
        $this->assertSame('1234567890123', SentryScrubber::scrubString('1234567890123'));
    }

    public function test_event_is_scrubbed_end_to_end(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api.example.uz/api/v1/phone/verify-code',
            'headers' => ['Authorization' => 'Bearer 1|secretsecretsecretsecret', 'Accept' => 'application/json'],
            'data' => ['phone' => '+998901234567', 'code' => '123456'],
            'cookies' => ['laravel_session' => 'abc'],
            'query_string' => 'initData=abc',
        ]);
        $event->setExtra(['card' => '4111111111111111', 'order_id' => 17]);
        $event->setTags(['request_id' => 'abc-123-req', 'phone' => '+998901234567']);
        $event->setMessage('Failed for +998901234567');
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('Cannot send code 123456 to +998 90 123 45 67 token=abc123'))]);
        $event->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'log', 'otp: 999111', ['phone' => '+998900000000'])]);
        $event->setUser(new UserDataBag('42', 'a@b.uz', '1.2.3.4', 'bob'));

        $sent = SentryScrubber::beforeSend($event);

        $this->assertNotNull($sent);
        $json = json_encode([
            $sent->getRequest(), $sent->getExtra(), $sent->getTags(), $sent->getMessage(),
            array_map(fn ($e) => $e->getValue(), $sent->getExceptions()),
            array_map(fn ($b) => [$b->getMessage(), $b->getMetadata()], $sent->getBreadcrumbs()),
        ], JSON_THROW_ON_ERROR);

        foreach (['998901234567', '998 90 123', '123456', 'secretsecret', 'laravel_session', '4111', 'abc123', '999111', '998900000000', 'initData=abc'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
        }
        $this->assertSame('abc-123-req', $sent->getTags()['request_id']);
        $this->assertSame(17, $sent->getExtra()['order_id']);
        $this->assertSame('application/json', $sent->getRequest()['headers']['Accept']);
        $this->assertSame('42', $sent->getUser()->getId());
        $this->assertNull($sent->getUser()->getEmail());
        $this->assertNull($sent->getUser()->getIpAddress());
        $this->assertNull($sent->getUser()->getUsername());
    }

    public function test_callbacks_in_config_survive_config_cache(): void
    {
        foreach (['before_send', 'before_send_transaction', 'before_breadcrumb'] as $option) {
            $callback = config("sentry.{$option}");
            $this->assertIsArray($callback, $option);
            $this->assertTrue(is_callable($callback), $option);
        }
        $this->assertSame('none', config('sentry.max_request_body_size'));
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertLessThanOrEqual(0.1, config('sentry.traces_sample_rate'));
    }
}
