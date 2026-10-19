<?php

namespace Tests\Feature;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\ResponseStatus;
use Sentry\Transport\Result;
use Sentry\Transport\TransportInterface;
use Tests\TestCase;

class SentryEnabledTest extends TestCase
{
    public function test_options_from_config_send_a_scrubbed_event_with_release_and_environment(): void
    {
        $transport = new class implements TransportInterface
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
}
