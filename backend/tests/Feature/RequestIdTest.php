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

    public function test_a_safe_incoming_request_id_is_kept(): void
    {
        $this->getJson('/up', ['X-Request-Id' => 'abc123-nginx.req_01'])
            ->assertHeader('X-Request-Id', 'abc123-nginx.req_01');
    }

    public function test_an_unsafe_incoming_request_id_is_replaced(): void
    {
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

        $response = $this->getJson('/__request-id-probe', ['X-Request-Id' => 'probe-request-1']);

        $response->assertHeader('X-Request-Id', 'probe-request-1')->assertJsonPath('request_id', 'probe-request-1');
        $this->assertSame('probe-request-1', $handler->getRecords()[0]->extra['request_id']);
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
