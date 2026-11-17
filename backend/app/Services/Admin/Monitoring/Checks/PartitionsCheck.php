<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;
use App\Services\Admin\Monitoring\PostgresStats;
use App\Support\Partitions\PartitionManager;

/**
 * M3: the journals' monthly partitions (PartitionManager::status, the same report as `db:partitions:status`). Fewer
 * than `threshold` future months attached (or the current one missing) is critical: the next month's rows would land
 * in DEFAULT; rows in DEFAULT, a table not converted yet, an unfinished conversion and archive failures or stale
 * detached partitions are warnings; a missing protective trigger (trigger_missing) is critical. One subject per problem and table; the texts are the status report's.
 */
class PartitionsCheck implements AlertCheck
{
    /**
     * Problem codes that are critical; any other code (also one PartitionManager adds later) is shown as a warning
     * with the status report's own message. trigger_missing: a protective trigger is gone (e.g.
     * fiscal_receipt_keys_insert: fiscal receipts are no longer protected against duplicates).
     */
    private const CRITICAL_CODES = ['trigger_missing' => true];

    public function __construct(
        private readonly PartitionManager $partitions,
        private readonly ?BackupState $backups = null,
    ) {}

    public function findings(AlertRule $rule): ?array
    {
        $status = $this->status();
        if ($status === null) {
            return null;
        }

        $findings = [];
        foreach ($status['tables'] as $table) {
            if (!$table['partitioned']) {
                continue;
            }
            if (!$table['current_month_attached'] || $table['future_months'] < $rule->threshold) {
                $findings[] = new AlertFinding("future_months:{$table['table']}", "{$table['table']}: oldinda {$table['future_months']} oy tayyor (kamida {$rule->threshold} kerak): `php artisan db:partitions:ensure` bajaring", [
                    'table' => $table['table'],
                    'future_months' => $table['future_months'],
                    'current_month_attached' => $table['current_month_attached'],
                    'threshold_months' => $rule->threshold,
                ], null, 'critical');
            }
        }

        foreach ($status['problems'] as $problem) {
            // future_months_low is measured above against the rule's own threshold
            if ($problem['code'] === 'future_months_low') {
                continue;
            }
            $findings[] = new AlertFinding($problem['code'] . ':' . ($problem['table'] ?? 'global'), $problem['message'], [
                'table' => $problem['table'],
                'code' => $problem['code'],
            ], null, (self::CRITICAL_CODES[$problem['code']] ?? false) ? 'critical' : 'warning');
        }

        return array_merge($findings, $this->archiveStorage($status));
    }

    /**
     * The journal archive's storage, from the `archive` marker (scripts/archive-partitions.sh, nightly): a storage
     * key that can delete objects (`archive_delete_allowed`), and months archived only on this server while there is
     * no off-site copy (`archive_local_only`: such rows stay `uploaded` with a file:// copy, no partition problem code
     * says so). Both warnings.
     *
     * @param  array<string, mixed>  $status
     * @return list<AlertFinding>
     */
    private function archiveStorage(array $status): array
    {
        $marker = ($this->backups ?? app(BackupState::class))->marker(MonitoringMarker::ARCHIVE);
        if ($marker === null) {
            return [];
        }
        $meta = (array) $marker->meta;
        $findings = [];
        if (($meta['delete_allowed'] ?? null) === true) {
            $findings[] = new AlertFinding('archive_delete_allowed', "Jurnallar arxivi kaliti S3 dan fayl o'chira oladi: arxiv himoyasiz. O'chirish huquqi yo'q alohida kalit bering (ARCHIVE_S3_ACCESS_KEY, ARCHIVE_S3_SECRET; OPERATIONS.md, 20-bo'lim)", [
                'target' => isset($meta['target']) ? mb_substr((string) $meta['target'], 0, 200) : null,
            ], null, 'warning');
        }
        $uploaded = (int) ($status['archives']['uploaded'] ?? 0);
        if (($meta['offsite'] ?? null) === false && $uploaded > 0) {
            $findings[] = new AlertFinding('archive_local_only', "{$uploaded} ta jurnal oyi faqat shu serverda arxivlangan: tashqi nusxa (S3) yo'q, bazadan o'chirilmaydi va server diski buzilsa yo'qoladi. S3 kalitlarini yozing (OPERATIONS.md, 20-bo'lim)", [
                'uploaded' => $uploaded,
            ], null, 'warning');
        }

        return $findings;
    }

    /**
     * PartitionManager::status() under the statement timeout; null = could not be read.
     *
     * @return array<string, mixed>|null
     */
    protected function status(): ?array
    {
        return PostgresStats::bounded(fn () => $this->partitions->status(), 'partitions');
    }
}
