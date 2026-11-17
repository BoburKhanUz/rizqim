<?php

namespace App\Domain\Realtime;

use App\Domain\Realtime\Broadcasting\RevisionLogBroadcaster;
use App\Domain\Realtime\Broadcasting\RevisionPusherBroadcaster;
use App\Domain\Realtime\Health\RealtimeHealthCommand;
use App\Domain\Realtime\Heartbeat\SendRealtimeHeartbeat;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\TransactionRolledBack;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wave M2: change revisions (ChangeRevision) and their write hooks. The pgsql driver gets RevisionPostgresConnection,
 * whose query builder touches the tokens of every written row (RevisionMap); the Eloquent update/delete events only
 * announce the row so that single-row writes need no pre-select.
 */
class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('pgsql', static fn ($pdo, string $database, string $prefix, array $config) => new RevisionPostgresConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        $announce = static function (string $event, array $payload): void {
            $model = $payload[0] ?? null;
            if (!$model instanceof Model || !$model->exists) {
                return;
            }
            app(RevisionHooks::class)->expect(
                $model->getTable(),
                $model->getKeyName(),
                $model->getRawOriginal($model->getKeyName()) ?? $model->getKey(),
                $model->getRawOriginal(),
                $model->getAttributes(),
            );
        };

        Event::listen('eloquent.updating: *', $announce);
        Event::listen('eloquent.deleting: *', $announce);

        // One after-commit flush per transaction (ChangeRevision::schedule): a rollback drops it, the next touch re-registers
        Event::listen(TransactionRolledBack::class, static fn () => app(ChangeRevision::class)->transactionRolledBack());

        // Every broadcast payload carries `rev` (Broadcasting\RevisionStamp): the reverb and log drivers, built lazily
        $this->callAfterResolving(BroadcastManager::class, function (BroadcastManager $manager): void {
            $manager->extend('reverb', fn ($app, array $config) => new RevisionPusherBroadcaster($manager->pusher($config), $config['jsonp'] ?? false));
            $manager->extend('log', fn ($app) => new RevisionLogBroadcaster($app->make(LoggerInterface::class)));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RealtimeHealthCommand::class]);

            // The heartbeat (docs/OPERATIONS.md, "Real-time"): sub-minute, so schedule:work runs it between minutes
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $event = $schedule->job(new SendRealtimeHeartbeat, (string) config('realtime.broadcast_queue', 'broadcasts'))
                    ->name('realtime-heartbeat');
                match ((int) config('realtime.heartbeat_seconds', 30)) {
                    0 => $event->when(fn () => false),
                    10 => $event->everyTenSeconds(),
                    15 => $event->everyFifteenSeconds(),
                    20 => $event->everyTwentySeconds(),
                    60 => $event->everyMinute(),
                    default => $event->everyThirtySeconds(),
                };
            });
        }
    }
}
