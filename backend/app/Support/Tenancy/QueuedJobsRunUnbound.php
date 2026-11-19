<?php

namespace App\Support\Tenancy;

use App\Domain\Realtime\RevisionPostgresConnection;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * M4 stage 2: a queued job or queued listener runs without a tenant, wherever it runs. Production's worker never binds
 * one; the sync queue (tests, `dispatchSync` of a ShouldQueue job, chains and batches on it) would otherwise run it
 * inside the tenant request that dispatched it, where the scope could hide a job's missing store filter. So the
 * context is forgotten from JobProcessing and the previous value comes back on JobAttempted only, which the sync queue
 * (SyncQueue::executeJob) and the worker (Worker::process) both fire in a `finally`, after the job's failure handling:
 * the job's failed() method, which the sync queue calls after JobExceptionOccurred and before JobFailed, runs unbound
 * there as it does in the worker. No other event restores: JobProcessing is always followed by JobAttempted unless the
 * worker process dies mid-job (a timeout kill), and then there is nothing left to restore.
 * Under `Event::fake()` without arguments neither event reaches this hook, so queued jobs run bound in such tests.
 *
 * Not a queued job, so still part of the request and still bound: `dispatchSync`/`dispatchNow` of a job that is not
 * ShouldQueue, inline (non-queued) event listeners, DB::afterCommit callbacks. Work after the response
 * (`dispatchAfterResponse`, terminating callbacks) runs after the request's binding is gone: unbound.
 */
final class QueuedJobsRunUnbound
{
    /** @var WeakMap<Job, array{0: int|null, 1: RevisionPostgresConnection|null}> the store bound when each running job started, and the connection whose tenant role it suspended */
    private WeakMap $previous;

    public function __construct()
    {
        $this->previous = new WeakMap();
    }

    public static function register(Dispatcher $events): void
    {
        $events->listen(JobProcessing::class, fn (JobProcessing $event) => app(self::class)->started($event->job));
        $events->listen(JobAttempted::class, fn (JobAttempted $event) => app(self::class)->finished($event->job));
    }

    public function started(Job $job): void
    {
        // The current instance each time (resolved per call, never kept: a test may rebind it)
        $tenant = app(TenantContext::class);
        $storeId = $tenant->storeId();
        // M4 stage 3: a worker runs the job in its own session, as the login role. Inline, inside the dispatching
        // request's tenant transaction, the job gets the login role too for as long as it runs (TenantRls)
        $connection = $storeId !== null ? DB::connection() : null;
        if ($connection instanceof RevisionPostgresConnection) {
            $connection->suspendTenantRole();
        } else {
            $connection = null;
        }
        $this->previous[$job] = [$storeId, $connection];
        $tenant->forget();
    }

    public function finished(Job $job): void
    {
        if (!$this->previous->offsetExists($job)) {
            return;
        }
        [$storeId, $connection] = $this->previous[$job];
        unset($this->previous[$job]);
        $connection?->resumeTenantRole();
        if ($storeId !== null) {
            app(TenantContext::class)->bind($storeId);
        } else {
            app(TenantContext::class)->forget();
        }
    }
}
