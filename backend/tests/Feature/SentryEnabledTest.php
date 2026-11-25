<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\RequestIntegration;
use Sentry\Laravel\Features\HttpClientIntegration;
use Sentry\Laravel\Http\LaravelRequestFetcher;
use Sentry\Laravel\Integration as LaravelIntegration;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;
use Sentry\ResponseStatus;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Tracing\TransactionContext;
use Sentry\Transport\Result;
use Sentry\Transport\TransportInterface;
use Tests\TestCase;

class SentryEnabledTest extends TestCase
{
    private const MASK = '[Filtered]';

    private ?HubInterface $previousHub = null;

    protected function tearDown(): void
    {
        if ($this->previousHub !== null) {
            SentrySdk::setCurrentHub($this->previousHub);
        }
        parent::tearDown();
    }

    public function test_options_from_config_send_a_scrubbed_event_with_release_and_environment(): void
    {
        $transport = $this->transport();

        config(['sentry.dsn' => 'https://public@o0.ingest.sentry.io/1', 'sentry.release' => 'abc1234', 'sentry.environment' => 'production']);
        $options = collect(config('sentry'))->only([
            'dsn', 'release', 'environment', 'sample_rate', 'traces_sample_rate', 'send_default_pii',
            'max_request_body_size', 'before_send', 'before_send_transaction', 'before_breadcrumb',
        ])->all();

        $client = ClientBuilder::create($options)->setTransport($transport)->getClient();
        $hub = new \Sentry\State\Hub($client);
        $hub->configureScope(fn ($scope) => $scope->setExtra('phone', '+998901234567'));
        $hub->addBreadcrumb(new \Sentry\Breadcrumb('info', 'default', 'log', 'sent code 482913 to +998901234567'));
        $hub->captureException(new \RuntimeException('Cannot reach +998 90 123 45 67, token=abc123'));

        $this->assertCount(1, $transport->sent);
        $event = $transport->sent[0];
        $this->assertSame('abc1234', $event->getRelease());
        $this->assertSame('production', $event->getEnvironment());

        $json = json_encode([$event->getExtra(), $event->getBreadcrumbs()[0]->getMessage(), $event->getExceptions()[0]->getValue()]);
        foreach (['998901234567', '998 90 123', '482913', 'abc123'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $json, $secret);
        }
    }

    /** Privacy policy s8: request body, headers, cookies and URL parameters of the incoming request are not sent. */
    public function test_an_event_for_an_incoming_request_carries_no_headers_cookies_body_or_query_string(): void
    {
        $transport = $this->transport();
        $hub = $this->hub($transport, [new RequestIntegration(new LaravelRequestFetcher())]);

        $request = (new ServerRequest('POST', 'https://api.rizqim.uz/api/v1/orders/1017/cancel?q=Amir+Temur+108&lat=41.31&tgWebAppStartParam=ref_77', [
            'Authorization' => 'Bearer 12|abcdefghijklmnopqrstuvwxyz0123456789ABCD',
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Telegram-iOS',
            'X-Forwarded-For' => '203.0.113.7',
            'Accept-Language' => 'uz-UZ',
            'Content-Type' => 'application/json',
        ], '{"reason":"Amir Temur 108, +998901234567"}', '1.1', ['REMOTE_ADDR' => '203.0.113.7']))
            ->withCookieParams(['laravel_session' => 'sess-abc'])
            ->withQueryParams(['q' => 'Amir Temur 108', 'lat' => '41.31']);
        $this->app->instance(LaravelRequestFetcher::CONTAINER_PSR7_INSTANCE_KEY, $request);

        $hub->captureException(new \RuntimeException('Order 1017 could not be cancelled'));

        $this->assertCount(1, $transport->sent);
        $sent = $transport->sent[0]->getRequest();
        $this->assertSame('https://api.rizqim.uz/api/v1/orders/1017/cancel', $sent['url'] ?? null);
        $this->assertSame('POST', $sent['method'] ?? null);
        foreach (['headers', 'cookies', 'query_string', 'data', 'env'] as $key) {
            $this->assertArrayNotHasKey($key, $sent, $key);
        }
        $json = (string) json_encode($transport->sent[0]->getRequest() + ['user' => $transport->sent[0]->getUser()?->getIpAddress()]);
        foreach (['Amir', 'Temur', '41.31', 'ref_77', 'Mozilla', '203.0.113.7', 'uz-UZ', 'sess-abc', '998901234567'] as $leak) {
            $this->assertStringNotContainsString($leak, $json, $leak);
        }
        $this->assertSame('Order 1017 could not be cancelled', $transport->sent[0]->getExceptions()[0]->getValue());
    }

    /** Log context keys that point at a person (account, order, store, address, location, search text) are masked. */
    public function test_log_breadcrumb_context_that_identifies_a_customer_is_masked(): void
    {
        $transport = $this->transport();
        $hub = $this->hub($transport);

        // As sentry-laravel's EventHandler::messageLoggedHandler records a Log::warning('...', $context) call
        $hub->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_DEFAULT, 'log.warning', 'Geocoding failed for checkout', [
            'user_id' => 42, 'order_id' => 1017, 'store_id' => 5, 'customer_id' => 42, 'chat_id' => 279058397,
            'phone' => '+998901234567', 'address' => 'Amir Temur 108', 'lat' => 41.311081, 'lng' => 69.240562,
            'q' => 'Amir Temur 108', 'query' => 'Chilonzor 9', 'status' => 503, 'provider' => 'nominatim',
        ]));
        $hub->captureException(new \RuntimeException('Geocoder unavailable'));

        $breadcrumb = $transport->sent[0]->getBreadcrumbs()[0];
        $this->assertSame('Geocoding failed for checkout', $breadcrumb->getMessage());
        $metadata = $breadcrumb->getMetadata();
        foreach (['user_id', 'order_id', 'store_id', 'customer_id', 'chat_id', 'phone', 'address', 'lat', 'lng', 'q', 'query'] as $key) {
            $this->assertSame(self::MASK, $metadata[$key], $key);
        }
        $this->assertSame(503, $metadata['status']);
        $this->assertSame('nominatim', $metadata['provider']);
        $this->assertSame('Geocoder unavailable', $transport->sent[0]->getExceptions()[0]->getValue());
    }

    /** Outgoing HTTP client calls (Nominatim address search: ?q=<address>) keep no query string in breadcrumbs or spans. */
    public function test_outgoing_http_breadcrumbs_and_spans_carry_no_query_string(): void
    {
        $transport = $this->transport();
        $hub = $this->hub($transport, [new LaravelIntegration()], ['traces_sample_rate' => 1.0]);

        $url = 'https://nominatim.openstreetmap.org/search?q=Amir+Temur+108%2C+Toshkent&format=jsonv2&lat=41.311081';
        $request = new ClientRequest(new PsrRequest('GET', $url));
        $response = new ClientResponse(new PsrResponse(200, ['Content-Type' => 'application/json'], '[{"display_name":"Amir Temur 108"}]'));

        $feature = $this->app->make(HttpClientIntegration::class);
        $transaction = $hub->startTransaction(TransactionContext::make()->setName('GET /api/v1/geocode')->setOp('http.server'));
        $hub->setSpan($transaction);
        $feature->handleRequestSendingHandlerForTracing(new RequestSending($request));
        $feature->handleResponseReceivedHandlerForTracing(new ResponseReceived($request, $response));
        $feature->handleResponseReceivedHandlerForBreadcrumb(new ResponseReceived($request, $response));
        $hub->captureException(new \RuntimeException('Geocoder returned nothing'));
        $transaction->finish();

        $error = collect($transport->sent)->first(fn (Event $e) => (string) $e->getType() === 'event');
        $sentTransaction = collect($transport->sent)->first(fn (Event $e) => (string) $e->getType() === 'transaction');
        $this->assertNotNull($error);
        $this->assertNotNull($sentTransaction);

        $breadcrumb = collect($error->getBreadcrumbs())->first(fn (Breadcrumb $b) => $b->getType() === Breadcrumb::TYPE_HTTP);
        $this->assertNotNull($breadcrumb);
        $this->assertSame('https://nominatim.openstreetmap.org/search', $breadcrumb->getMetadata()['url']);
        $this->assertSame(200, $breadcrumb->getMetadata()['http.response.status_code']);

        $json = (string) json_encode([
            $breadcrumb->getMetadata(),
            array_map(fn ($span) => [$span->getDescription(), $span->getData()], $sentTransaction->getSpans()),
        ]);
        $this->assertStringContainsString('nominatim.openstreetmap.org', $json);
        foreach (['Amir', 'Temur', 'Toshkent', 'jsonv2', '41.311081', 'http.query', '"q='] as $leak) {
            $this->assertStringNotContainsString($leak, $json, $leak);
        }
    }

    /**
     * The client the Laravel service provider would build from config/sentry.php (Laravel-only keys removed), with
     * the given integrations only, made the current hub for the global event processors.
     *
     * @param  list<\Sentry\Integration\IntegrationInterface>  $integrations
     * @param  array<string, mixed>  $override
     */
    private function hub(TransportInterface $transport, array $integrations = [], array $override = []): HubInterface
    {
        $laravelOnly = (new \ReflectionClassConstant(SentryServiceProvider::class, 'LARAVEL_SPECIFIC_OPTIONS'))->getValue();
        $options = array_diff_key(config('sentry'), array_flip($laravelOnly));
        $options = array_merge($options, [
            'dsn' => 'https://public@o0.ingest.sentry.io/1',
            'default_integrations' => false,
            'integrations' => $integrations,
        ], $override);

        $hub = new Hub(ClientBuilder::create($options)->setTransport($transport)->getClient());
        $this->previousHub = SentrySdk::getCurrentHub();
        SentrySdk::setCurrentHub($hub);

        return $hub;
    }

    private function transport(): TransportInterface
    {
        return new class implements TransportInterface
        {
            /** @var list<Event> */
            public array $sent = [];

            public function send(Event $event): Result
            {
                $this->sent[] = $event;

                return new Result(ResponseStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResponseStatus::success());
            }
        };
    }
}
