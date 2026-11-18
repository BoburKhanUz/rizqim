<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;
use App\Services\Admin\Monitoring\PostgresStats;
use App\Services\Admin\Monitoring\WalFailureClock;

/**
 * M3: with PITR on, WAL must reach the pgBackRest repository: archiving has been failing for `threshold_secondary` minutes
 * (pg_stat_archiver: the last failure is newer than the last success; timed by WalFailureClock from when that was first
 * seen, not from the last success: an idle server archives nothing for hours), more than `threshold` segments wait to be
 * archived, or archive_mode is off on a server that should archive. With PITR off: quiet. Unknown when the
 * archiver cannot be read.
 */
class WalArchiveCheck implements AlertCheck
{
    public function __construct(
        private readonly PostgresStats $stats,
        private readonly BackupState $backups,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        if (!$this->backups->pitrEnabled()) {
            return [];
        }
        $state = $this->stats->archiver();
        if ($state === null) {
            return null;
        }

        if ($state['archive_mode'] === 'off') {
            return [new AlertFinding('archive_mode', "PITR yoqilgan, lekin Postgres'da archive_mode o'chiq: WAL yuborilmayapti", [
                'archive_mode' => 'off',
            ])];
        }

        $findings = [];
        $limitMinutes = $rule->threshold_secondary ?? 5;
        $failingFor = WalFailureClock::failingSeconds($state);
        if ($failingFor !== null && $failingFor >= $limitMinutes * 60) {
            $minutes = intdiv($failingFor, 60);
            // Only what is known: it has failed for at least this long (since it was first seen failing)
            $findings[] = new AlertFinding('failing', "WAL kamida {$minutes} daqiqadan beri arxivga yuborilmayapti: davom etsa PITR zanjiri uziladi va to'liq zaxira kerak bo'ladi", [
                'failing_minutes' => $minutes,
                'last_failed_wal' => $state['last_failed_wal'],
                'last_failed_at' => $state['last_failed_at'],
                'last_archived_at' => $state['last_archived_at'],
                'threshold_minutes' => $limitMinutes,
            ]);
        }
        if ($state['ready_wal'] !== null && $state['ready_wal'] > $rule->threshold) {
            $findings[] = new AlertFinding('backlog', "{$state['ready_wal']} ta WAL fayl arxivga yuborilishini kutmoqda", [
                'ready_wal' => $state['ready_wal'],
                'threshold' => $rule->threshold,
                'last_archived_at' => $state['last_archived_at'],
            ]);
        }

        return $findings;
    }
}
