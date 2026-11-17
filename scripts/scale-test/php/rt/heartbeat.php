<?php
// Kit-only stand-in for `schedule:work` in the M2 real-time runs (server.sh, RT=1): queues the real-time heartbeat job
// (App\Domain\Realtime\Heartbeat\SendRealtimeHeartbeat) on the broadcasts queue every realtime.heartbeat_seconds, as the
// scheduler does, without the scheduler's other minute jobs (fiscal retry, SLA and alert checks), which the M1 runs did
// not have either. RT_SCHEDULER=full in server.sh runs the real `php artisan schedule:work` instead.
$backend = getenv('BACKEND') ?: dirname(__DIR__, 4) . '/backend';
require $backend . '/vendor/autoload.php';
$app = require $backend . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$every = max(1, (int) config('realtime.heartbeat_seconds', 30));
$queue = (string) config('realtime.broadcast_queue', 'broadcasts');
while (true) {
    dispatch(new App\Domain\Realtime\Heartbeat\SendRealtimeHeartbeat)->onQueue($queue);
    sleep($every - (time() % $every));
}
