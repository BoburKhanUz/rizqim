<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;
use Illuminate\Support\Carbon;

/**
 * M3: with PITR on, a restore drill (scripts/restore-drill.sh, monthly) must have passed within `threshold` days
 * (subject `overdue`, warning) and the newest one must not have failed (subject `failed`, critical). A new install
 * gets `threshold` days from the rule's creation for its first drill. With PITR off: quiet.
 */
class RestoreDrillStaleCheck implements AlertCheck
{
    public function __construct(
        private readonly BackupState $backups,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        if (!$this->backups->pitrEnabled()) {
            return [];
        }
        $drills = $this->backups->drills();
        if ($drills === null) {
            return null;
        }

        $findings = [];
        $last = $drills['last'];
        if ($last !== null && $last->status === 'failed') {
            $at = Carbon::parse($last->started_at, 'UTC');
            $findings[] = new AlertFinding('failed', 'Oxirgi tiklash mashqi (' . $at->setTimezone('Asia/Tashkent')->format('d.m.Y') . ") muvaffaqiyatsiz tugadi: zaxiradan tiklash ishlamasligi mumkin", [
                'drill_id' => (int) $last->id,
                'started_at' => $at->toIso8601String(),
                'error' => $last->error !== null ? mb_substr((string) $last->error, 0, 300) : null,
            ], null, 'critical');
        }

        $ok = $drills['last_ok'];
        $limit = now()->subDays($rule->threshold);
        if ($ok === null) {
            if ($rule->created_at === null || $rule->created_at->lt($limit)) {
                $findings[] = new AlertFinding('overdue', 'Zaxiradan tiklash mashqi hali bir marta ham muvaffaqiyatli o\'tkazilmagan', [
                    'last_ok_at' => null,
                    'threshold_days' => $rule->threshold,
                ], null, 'warning');
            }
        } elseif (Carbon::parse($ok->started_at, 'UTC')->lt($limit)) {
            $at = Carbon::parse($ok->started_at, 'UTC');
            $findings[] = new AlertFinding('overdue', 'Oxirgi muvaffaqiyatli tiklash mashqi ' . (int) floor($at->diffInDays(now(), true)) . ' kun oldin bo\'lgan', [
                'last_ok_at' => $at->toIso8601String(),
                'threshold_days' => $rule->threshold,
            ], null, 'warning');
        }

        return $findings;
    }
}
