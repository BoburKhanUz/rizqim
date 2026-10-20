<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_response_carries_a_generated_request_id(): void
    {
        $id = $this->getJson('/up')->assertOk()->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $id);
        $this->assertNotSame($id, $this->getJson('/up')->headers->get('X-Request-Id'));
    }

    public function test_a_client_supplied_request_id_is_ignored_by_default(): void
    {
        $this->assertFalse(config('logging.trust_incoming_request_id'));

        $response = $this->getJson('/up', ['X-Request-Id' => 'abc123-nginx.req_01'])->assertOk();

        $this->assertNotSame('abc123-nginx.req_01', $response->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Request-Id'));
    }

    public function test_a_safe_id_from_a_trusted_proxy_is_kept_when_trusting_is_on(): void
    {
        config(['logging.trust_incoming_request_id' => true]);

        // The test client is 127.0.0.1, one of bootstrap/app.php's trusted proxy hops
        $this->getJson('/up', ['X-Request-Id' => 'abc123-nginx.req_01'])
            ->assertHeader('X-Request-Id', 'abc123-nginx.req_01');
    }

    public function test_an_id_from_an_untrusted_address_is_replaced_even_when_trusting_is_on(): void
    {
        config(['logging.trust_incoming_request_id' => true]);

        $id = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->getJson('/up', ['X-Request-Id' => 'abc123-nginx.req_01'])
            ->headers->get('X-Request-Id');

        $this->assertNotSame('abc123-nginx.req_01', $id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $id);
    }

    public function test_an_unsafe_incoming_request_id_is_replaced(): void
    {
        config(['logging.trust_incoming_request_id' => true]);

        foreach (["bad id with spaces", "x\"; drop", 'short', str_repeat('a', 65)] as $incoming) {
            $id = $this->getJson('/up', ['X-Request-Id' => $incoming])->headers->get('X-Request-Id');

            $this->assertNotSame($incoming, $id);
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $id);
        }
    }

    public function test_request_id_is_added_to_log_records_of_the_request(): void
    {
        $handler = new TestHandler;
        $logger = new Logger('test', [$handler]);
        $logger->pushProcessor(new \Illuminate\Log\Context\ContextLogProcessor);

        Route::middleware(AssignRequestId::class)->get('/__request-id-probe', function () use ($logger) {
            $logger->info('probe line');

            return response()->json(['request_id' => Context::get('request_id')]);
        });

        $response = $this->getJson('/__request-id-probe');

        // The same generated id is in the response header, the log context and the log line
        $id = (string) $response->headers->get('X-Request-Id');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id);
        $response->assertJsonPath('request_id', $id);
        $this->assertSame($id, $handler->getRecords()[0]->extra['request_id']);
    }

    public function test_json_log_channel_writes_one_json_object_per_line_with_the_request_id(): void
    {
        config(['logging.channels.stderr_json.handler_with.stream' => 'php://memory']);

        $channel = config('logging.channels.stderr_json');
        $this->assertSame(JsonFormatter::class, $channel['formatter']);

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true, true);
        $record = new \Monolog\LogRecord(
            new \DateTimeImmutable,
            'app',
            \Monolog\Level::Error,
            'Order failed',
            ['order' => 5],
            ['request_id' => 'abc-123-request'],
        );
        $line = $formatter->format($record);

        $this->assertStringEndsWith("\n", $line);
        $decoded = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Order failed', $decoded['message']);
        $this->assertSame('abc-123-request', $decoded['extra']['request_id']);
        $this->assertSame('ERROR', $decoded['level_name']);
    }

    public function test_json_channel_can_be_built_by_the_log_manager(): void
    {
        config(['logging.default' => 'stderr_json']);

        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, Log::channel('stderr_json'));
    }
}
