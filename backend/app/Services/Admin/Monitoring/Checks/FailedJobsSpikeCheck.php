<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\FailedJobs;
use Illuminate\Support\Facades\DB;

/**
 * At least `threshold` jobs failed in the last `window_minutes`.
 */
class FailedJobsSpikeCheck implements AlertCheck
{
    public function findings(AlertRule $rule): ?array
    {
        $window = max(1, (int) ($rule->window_minutes ?? 15));
        $since = now()->subMinutes($window);

        $count = DB::table('failed_jobs')->where('failed_at', '>=', $since)->count();
        if ($count < $rule->threshold) {
            return [];
        }

        return [new AlertFinding('global', "Oxirgi {$window} daqiqada {$count} ta vazifa yiqildi", [
            'count' => $count,
            'window_minutes' => $window,
            'threshold' => $rule->threshold,
            'top_jobs' => FailedJobs::topNames($since),
        ])];
    }
}
