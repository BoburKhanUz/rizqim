<?php

namespace App\Support\Tenancy;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use WeakMap;

/**
 * M4 stage 2: a queued job or queued listener runs without a tenant, wherever it runs. Production's worker never binds
 * one; the sync queue (tests, `dispatchSync` of a ShouldQueue job, chains and batches on it) would otherwise run it
 * inside the tenant request that dispatched it, where the scope could hide a job's missing store filter. So the
 * context is forgotten from JobProcessing and the previous value comes back when the job is done (JobAttempted, which
 * the sync queue and the worker fire in a `finally`; JobProcessed / JobExceptionOccurred / JobFailed too, whichever
 * comes first: restoring is done once per job).
 *
 * Not a queued job, so still part of the request and still bound: `dispatchSync`/`dispatchNow` of a job that is not
 * ShouldQueue, inline (non-queued) event listeners, DB::afterCommit callbacks. Work after the response
 * (`dispatchAfterResponse`, terminating callbacks) runs after the request's binding is gone: unbound.
 */
final class QueuedJobsRunUnbound
{
    /** @var WeakMap<Job, int|null> the store bound when each running job started */
    private WeakMap $previous;

    public function __construct()
    {
        $this->previous = new WeakMap();
    }

    public static function register(Dispatcher $events): void
    {
        $events->listen(JobProcessing::class, fn (JobProcessing $event) => app(self::class)->started($event->job));
        $events->listen(
            [JobProcessed::class, JobExceptionOccurred::class, JobFailed::class, JobAttempted::class],
            fn (object $event) => app(self::class)->finished($event->job),
        );
    }

    public function started(Job $job): void
    {
        // The current instance each time (resolved per call, never kept: a test may rebind it)
        $tenant = app(TenantContext::class);
        $this->previous[$job] = $tenant->storeId();
        $tenant->forget();
    }

    public function finished(Job $job): void
    {
        if (!$this->previous->offsetExists($job)) {
            return;
        }
        $storeId = $this->previous[$job];
        unset($this->previous[$job]);
        if ($storeId !== null) {
            app(TenantContext::class)->bind($storeId);
        } else {
            app(TenantContext::class)->forget();
        }
    }
}
