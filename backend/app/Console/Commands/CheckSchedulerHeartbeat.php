<?php

namespace App\Console\Commands;

use App\Support\HealthReport;
use Illuminate\Console\Command;

class CheckSchedulerHeartbeat extends Command
{
    protected $signature = 'health:scheduler';

    protected $description = 'Exit 1 if the scheduler heartbeat is missing or stale (Docker healthcheck of the scheduler service)';

    public function handle(): int
    {
        $age = HealthReport::heartbeatAge();

        if (!HealthReport::schedulerIsFresh()) {
            $this->error($age === null ? 'Scheduler heartbeat is missing.' : "Scheduler heartbeat is stale ({$age}s old).");

            return self::FAILURE;
        }

        $this->info("Scheduler heartbeat is fresh ({$age}s old).");

        return self::SUCCESS;
    }
}
