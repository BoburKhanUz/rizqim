<?php

namespace Tests\Feature;

use App\Support\HealthReport;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_healthy_system_returns_200_without_authentication(): void
    {
        HealthReport::beat();

        $response = $this->getJson('/health');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.redis.status', 'not_used')
            ->assertJsonPath('checks.queue.status', 'ok')
            ->assertJsonPath('checks.queue.default_backlog', 0)
            ->assertJsonPath('checks.queue.reports_backlog', 0)
            ->assertJsonPath('checks.scheduler.status', 'ok');
        $this->assertLessThanOrEqual(1, $response->json('checks.scheduler.last_heartbeat_age_seconds'));
    }

    public function test_same_report_is_available_through_the_api_prefix(): void
    {
        HealthReport::beat();

        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_report_does_not_expose_secrets_or_versions(): void
    {
        $body = strtolower($this->getJson('/health')->getContent());

        foreach ([PHP_VERSION, app()->version(), config('app.key'), config('database.connections.pgsql.password'), 'password', 'laravel', 'postgres'] as $secret) {
            $this->assertStringNotContainsString(strtolower((string) $secret), $body);
        }
    }

    public function test_missing_scheduler_heartbeat_is_degraded_but_still_200(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler.status', 'missing')
            ->assertJsonPath('checks.scheduler.last_heartbeat_age_seconds', null);
    }

    public function test_stale_scheduler_heartbeat_is_degraded(): void
    {
        Cache::put(HealthReport::HEARTBEAT_KEY, time() - 400, 600);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler.status', 'stale')
            ->assertJsonPath('checks.scheduler.last_heartbeat_age_seconds', 400);
    }

    public function test_queue_backlog_is_reported_and_a_long_queue_is_degraded(): void
    {
        HealthReport::beat();
        config(['health.queue_backlog_warn' => 10, 'health.reports_backlog_warn' => 5, 'orders.reports.queue' => 'reports']);
        Queue::shouldReceive('size')->with('default')->andReturn(11);
        Queue::shouldReceive('size')->with('reports')->andReturn(2);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'backlog')
            ->assertJsonPath('checks.queue.default_backlog', 11)
            ->assertJsonPath('checks.queue.reports_backlog', 2);
    }

    public function test_unreadable_queue_size_is_unknown_not_a_failure(): void
    {
        HealthReport::beat();
        Queue::shouldReceive('size')->andThrow(new RuntimeException('queue down'));

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.queue.status', 'unknown')
            ->assertJsonPath('checks.queue.default_backlog', null);
    }

    public function test_database_failure_returns_503_without_leaking_the_error(): void
    {
        HealthReport::beat();
        DB::shouldReceive('select')->andThrow(new RuntimeException('connection refused to db.internal:5432 password=hunter2'));

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('checks.database.status', 'fail');
        $this->assertStringNotContainsString('hunter2', $response->getContent());
        $this->assertStringNotContainsString('db.internal', $response->getContent());
    }

    public function test_redis_failure_returns_503_when_redis_is_in_use(): void
    {
        HealthReport::beat();
        config(['queue.default' => 'redis']);
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('checks.redis.status', 'fail');
    }

    public function test_redis_is_pinged_on_both_connections_when_in_use(): void
    {
        HealthReport::beat();
        config(['queue.default' => 'redis']);
        $connection = new class
        {
            public int $pings = 0;

            public function ping(): bool
            {
                $this->pings++;

                return true;
            }
        };
        Redis::shouldReceive('connection')->with('default')->once()->andReturn($connection);
        Redis::shouldReceive('connection')->with('cache')->once()->andReturn($connection);
        Queue::shouldReceive('size')->andReturn(0);

        $this->getJson('/health')->assertOk()->assertJsonPath('checks.redis.status', 'ok');
        $this->assertSame(2, $connection->pings);
    }

    public function test_a_redis_outage_is_reported_even_though_the_cache_is_down_too(): void
    {
        // Redis down takes the cache store (rate limiter, heartbeat) with it, not only the ping
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('get', 'many', 'put', 'add', 'increment', 'forget', 'forever')->andThrow(new RuntimeException('Connection refused'));
        Cache::extend('down', fn () => Cache::repository($store));
        config(['cache.stores.down' => ['driver' => 'down'], 'cache.default' => 'down', 'queue.default' => 'redis']);
        $this->app->instance(RateLimiter::class, new RateLimiter(Cache::store('down')));
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

        foreach (['/health', '/api/v1/health'] as $uri) {
            $this->getJson($uri)
                ->assertStatus(503)
                ->assertJsonPath('status', 'fail')
                ->assertJsonPath('checks.redis.status', 'fail')
                ->assertJsonPath('checks.scheduler.status', 'missing');
        }
    }

    public function test_scheduler_writes_a_heartbeat_every_minute(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('health-heartbeat')->assertSuccessful();

        $this->assertNull(HealthReport::heartbeatAge());
        HealthReport::beat();
        $this->assertLessThanOrEqual(1, HealthReport::heartbeatAge());
    }

    public function test_health_scheduler_command_reflects_the_heartbeat(): void
    {
        $this->artisan('health:scheduler')->assertFailed();

        Cache::put(HealthReport::HEARTBEAT_KEY, time() - 1000, 600);
        $this->artisan('health:scheduler')->assertFailed();

        HealthReport::beat();
        $this->artisan('health:scheduler')->assertSuccessful();
    }
}
