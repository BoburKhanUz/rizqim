<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\BackupState;
use App\Services\Admin\Monitoring\PostgresStats;
use App\Support\Database\OwnerConnection;
use App\Support\Partitions\PartitionManager;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * M3: the journals' monthly partitions (PartitionManager::status, the same report as `db:partitions:status`). Fewer
 * than `threshold` future months attached (or the current one missing) is critical: the next month's rows would land
 * in DEFAULT; rows in DEFAULT, a table not converted yet and archive failures or stale
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

        return array_merge($findings, $this->maintenanceRole(), $this->archiveStorage($status), $this->paymentsStore());
    }

    /**
     * M4: can the partition commands do their DDL? Without the owner's credentials in this (the scheduler's)
     * environment, with a wrong owner password, or with the journal tables not owned by that role, every 00:40 and
     * 22:40 run fails and future_months_low would only say so weeks later: critical at once. With the owner configured
     * the question is asked as the owner (one short connection per evaluation, connect timeout of pgsql_owner); a
     * failure to connect is the finding. Objects of the schema the owner does not own (db-roles could not move them;
     * objects of an extension are left out, as db-roles leaves them) are a warning: the app works, but a migration changing one of them would stop the next deploy. Never throws.
     *
     * @return list<AlertFinding>
     */
    private function maintenanceRole(): array
    {
        $findings = [];
        if (OwnerConnection::configured()) {
            $problem = $this->asOwner();
            $how = 'DB_OWNER_USERNAME/DB_OWNER_PASSWORD ni tekshiring yoki `docker compose run --rm db-roles` bajaring';
        } else {
            $problem = PostgresStats::bounded(fn () => $this->partitions->maintenanceRoleProblem(), 'partitions_role');
            $how = 'Scheduler konteyneriga DB_OWNER_USERNAME va DB_OWNER_PASSWORD bering';
        }
        if (is_string($problem)) {
            $findings[] = new AlertFinding('maintenance_role', "Jurnallarning oylik bo'limlarini yaratib bo'lmaydi: {$problem}. {$how} (docs/OPERATIONS.md, «Baza rollari»)", [
                'problem' => mb_substr($problem, 0, 200),
            ], null, 'critical');
        }

        $owner = OwnerConnection::username();
        $foreign = $owner === null ? null : PostgresStats::bounded(fn () => DB::select(
            "SELECT c.relname AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
              WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p', 'v', 'm', 'f') AND pg_get_userbyid(c.relowner) <> ?
                AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid AND d.deptype = 'e')
              ORDER BY c.relname",
            [$owner],
        ), 'partitions_owner');
        if (is_array($foreign) && $foreign !== []) {
            $names = array_map(fn (object $row) => (string) $row->name, $foreign);
            $findings[] = new AlertFinding('owner_mismatch', count($names) . " ta jadval {$owner} niki emas (" . implode(', ', array_slice($names, 0, 5)) . (count($names) > 5 ? ', ...' : '') . "): ularni o'zgartiradigan migratsiya keyingi deploy'ni to'xtatadi. Tinch paytda `docker compose run --rm db-roles` bajaring (docs/OPERATIONS.md, «Baza rollari»)", [
                'count' => count($names),
                'tables' => array_slice($names, 0, 20),
            ], null, 'warning');
        }

        return $findings;
    }

    /**
     * maintenanceRoleProblem() on the owner connection; a connection or query failure is returned as the problem.
     */
    private function asOwner(): ?string
    {
        try {
            return OwnerConnection::run(function (): ?string {
                try {
                    DB::connection()->select('SELECT 1');
                } catch (Throwable $e) {
                    return "egasi roli bilan bazaga ulanib bo'lmadi (" . mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 160) . ')';
                }
                $problem = PostgresStats::bounded(fn () => $this->partitions->maintenanceRoleProblem() ?? '', 'partitions_role_owner');

                return $problem === null ? "egasi roli bilan tekshirib bo'lmadi" : ($problem === '' ? null : $problem);
            });
        } catch (Throwable $e) {
            return "egasi roli bilan tekshirib bo'lmadi (" . mb_substr($e->getMessage(), 0, 160) . ')';
        }
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
     * M4 stage 2: payments.store_id is still nullable (migration 2026_11_27_100000 found payments without a parent
     * store and left the NOT NULL step undone; such rows are invisible to a till bound to its store). A warning until
     * docs/OPERATIONS.md section 23 is done, with the count of rows without a store when it can be taken. Two bounded
     * reads as the application role: the catalog (NOT NULL is the normal state and costs only this), then, only while
     * the column is nullable, the count. The count scans payments and may exceed the statement timeout on a large
     * table: the finding is then emitted without the number, never dropped (a dropped finding closes the alert).
     *
     * @return list<AlertFinding>
     */
    private function paymentsStore(): array
    {
        $nullable = PostgresStats::bounded(
            fn () => (bool) (DB::selectOne("SELECT NOT a.attnotnull AS nullable FROM pg_attribute a WHERE a.attrelid = to_regclass('payments') AND a.attname = 'store_id' AND NOT a.attisdropped")->nullable ?? false),
            'payments_store',
        );
        if ($nullable !== true) {
            return [];
        }

        $missing = PostgresStats::bounded(fn () => $this->paymentsWithoutStore(), 'payments_store_count');
        $what = is_int($missing)
            ? "{$missing} ta to'lovning do'koni yo'q (kassa ularni ko'rmaydi)"
            : "do'koni yo'q to'lovlarni sanab bo'lmadi (so'rov vaqtida tugamadi yoki xato berdi; kassa ularni ko'rmaydi)";

        return [new AlertFinding('payments_store_nullable', "payments.store_id hali NULL bo'lishi mumkin: {$what}. Tekshirib, NOT NULL qadamini yakunlang (docs/OPERATIONS.md, 23-bo'lim)", [
            'missing' => is_int($missing) ? $missing : null,
        ], null, 'warning')];
    }

    /**
     * Payments without a store: a scan of the table (no index on store_id), so it can exceed the statement timeout.
     */
    protected function paymentsWithoutStore(): int
    {
        return (int) DB::selectOne('SELECT count(*) AS n FROM payments WHERE store_id IS NULL')->n;
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
