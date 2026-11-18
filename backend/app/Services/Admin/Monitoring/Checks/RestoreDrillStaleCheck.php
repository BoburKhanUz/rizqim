<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;
use Illuminate\Support\Carbon;

/**
 * M3: with PITR on, a restore drill (scripts/restore-drill.sh, monthly) must have passed within `threshold` days
 * (subject `overdue`, warning) and the newest one must not have failed (subject `failed`, critical). A new install
 * gets `threshold` days from the rule's creation for its first drill. The automatic monthly drill satisfies that even
 * though it skips the app check (checks.app_smoke = skipped); a separate mild subject `app_check` (warning) asks for a
 * drill whose app check passed (the manual scripts/restore-drill.sh) within monitoring.drill_app_check_days. With PITR
 * off: quiet.
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

        // The monthly automatic drill skips the app check; a fully verified one (manual) is wanted about once a quarter
        $appDays = max(1, (int) config('monitoring.drill_app_check_days', 100));
        $appOk = $drills['last_app_ok'];
        $appLimit = now()->subDays($appDays);
        $graceOver = $rule->created_at === null || $rule->created_at->lt($appLimit);
        if (($appOk === null && $graceOver) || ($appOk !== null && Carbon::parse($appOk->started_at, 'UTC')->lt($appLimit))) {
            $findings[] = new AlertFinding('app_check', $appOk === null
                ? "Ilova tekshiruvi bilan to'liq tiklash mashqi hali o'tkazilmagan: serverda scripts/restore-drill.sh ni ishga tushiring"
                : "Ilova tekshiruvi bilan to'liq tiklash mashqi {$appDays} kundan beri o'tkazilmagan: serverda scripts/restore-drill.sh ni ishga tushiring", [
                    'last_app_ok_at' => $appOk !== null ? Carbon::parse($appOk->started_at, 'UTC')->toIso8601String() : null,
                    'threshold_days' => $appDays,
                ], null, 'warning');
        }

        return $findings;
    }
}
