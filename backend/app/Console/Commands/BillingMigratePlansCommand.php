<?php

namespace App\Console\Commands;

use App\Services\Finance\Billing\PlanMigration;
use Illuminate\Console\Command;

/**
 * P4-21: existing stores onto Billing v2 (docs/BILLING.md, "Mavjud do'konlarni o'tkazish"). Run with --dry-run first:
 * it does the same in a transaction that is rolled back and prints the same report. Safe to run again.
 */
class BillingMigratePlansCommand extends Command
{
    protected $signature = 'billing:migrate-plans
        {--dry-run : Show what would change and change nothing}
        {--store=* : Only these store ids}';

    protected $description = 'Move existing stores to the v2 tariffs (Start / Asosiy / Pro), commission by channel and the split debt';

    public function handle(PlanMigration $migration): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_values(array_map('intval', (array) $this->option('store')));
        $rows = $migration->run($dryRun, $ids !== [] ? $ids : null);

        $this->info($dryRun ? "Sinov (--dry-run): hech narsa o'zgarmadi." : "O'tkazildi.");
        if ($rows === []) {
            $this->info("O'zgaradigan do'kon yo'q.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', "Do'kon", 'Tarif', 'Qachondan', 'Eski narx', 'Yangi narx', '1-oy', 'Komissiya', 'Obuna qarzi', 'Amallar'],
            array_map(fn (array $row) => [
                $row['store_id'],
                $row['store'],
                ($row['plan_from'] ?? '—') . ($row['plan_to'] !== null ? " → {$row['plan_to']}" : ''),
                $row['from'] ?? '—',
                $row['price_old'] ?? '—',
                $row['price_new'] ?? '—',
                $row['first_month'] ?? '—',
                $row['commission_from'] === $row['commission_to'] ? $row['commission_from'] : "{$row['commission_from']} → {$row['commission_to']}",
                $row['subscription_debt'] ?? '—',
                implode(', ', $row['actions']),
            ], $rows),
        );

        $counts = array_count_values(array_merge(...array_map(fn (array $row) => $row['actions'], $rows)));
        ksort($counts);
        foreach ($counts as $action => $count) {
            $this->line("{$action}: {$count}");
        }

        return self::SUCCESS;
    }
}
