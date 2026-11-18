<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: a queued job (or queued listener) runs without a tenant, as in production's worker, also when the tests'
 * sync queue runs it inside a tenant request, its failed() method included; the request is bound again afterwards, also
 * when the job throws. A job that is not queued (dispatchSync of a plain job) is part of the request and stays bound.
 */
class QueuedJobsUnboundTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    /** @var list<string> */
    public static array $seen = [];

    public static function note(string $what): void
    {
        self::$seen[] = $what . '=' . var_export(app(TenantContext::class)->storeId(), true);
    }

    public function test_queued_work_runs_unbound_and_the_request_is_bound_again(): void
    {
        self::$seen = [];
        Event::listen(QueuedTenantProbeEvent::class, QueuedTenantProbeListener::class);
        Route::middleware(['api', 'auth:sanctum', 'tenant'])->post('api/test-tenant/jobs', function () {
            QueuedTenantProbe::dispatch('queued');
            self::note('after-queued');
            try {
                QueuedTenantProbe::dispatch('throws', true);
            } catch (RuntimeException) {
            }
            self::note('after-throw');
            QueuedTenantProbe::dispatchSync('queued-sync');
            Bus::chain([new QueuedTenantProbe('chain-1'), new QueuedTenantProbe('chain-2')])->dispatch();
            PlainTenantProbe::dispatchSync('plain-sync');
            event(new QueuedTenantProbeEvent());
            self::note('end');

            return response()->json(['ok' => true]);
        });
        $store = $this->makeStore('S');
        $owner = $this->makeStaff(UserRole::OWNER, null, $store);

        $this->bearerJson('POST', '/api/test-tenant/jobs', $owner->createToken('t')->plainTextToken)->assertOk();

        $bound = var_export($store->id, true);
        $this->assertSame([
            'queued=NULL', 'nested=NULL', "after-queued={$bound}",
            // failed() too: the sync queue calls it while handling the exception, the worker after the job
            'throws=NULL', 'failed:throws=NULL', "after-throw={$bound}",
            'queued-sync=NULL', 'chain-1=NULL', 'chain-2=NULL',
            "plain-sync={$bound}",
            'listener=NULL',
            "end={$bound}",
        ], self::$seen);
        $this->assertNull(app(TenantContext::class)->storeId());
    }
}

class QueuedTenantProbe implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public string $name, public bool $throws = false) {}

    public function handle(): void
    {
        QueuedJobsUnboundTest::note($this->name);
        if ($this->name === 'queued') {
            self::dispatch('nested');
        }
        if ($this->throws) {
            throw new RuntimeException('job failed');
        }
    }

    public function failed(\Throwable $e): void
    {
        QueuedJobsUnboundTest::note('failed:' . $this->name);
    }
}

class PlainTenantProbe
{
    use Dispatchable;

    public function __construct(public string $name) {}

    public function handle(): void
    {
        QueuedJobsUnboundTest::note($this->name);
    }
}

class QueuedTenantProbeEvent
{
}

class QueuedTenantProbeListener implements ShouldQueue
{
    public function handle(QueuedTenantProbeEvent $event): void
    {
        QueuedJobsUnboundTest::note('listener');
    }
}
