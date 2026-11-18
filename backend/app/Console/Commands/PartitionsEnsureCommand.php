<?php

namespace App\Console\Commands;

use App\Support\Partitions\PartitionManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Wave M3: keeps the months of the journal tables attached up to now + config partitions.months_ahead and moves rows
 * out of the DEFAULT partitions. Idempotent; scheduled daily 00:40 UTC (App\Providers\PartitionsServiceProvider).
 */
class PartitionsEnsureCommand extends Command
{
    protected $signature = 'db:partitions:ensure';

    protected $description = "Jurnal jadvallarining kelgusi oylarini tayyorlaydi va DEFAULT bo'limdagi qatorlarni o'z oyiga ko'chiradi";

    public function handle(PartitionManager $partitions): int
    {
        $failed = false;
        foreach (array_keys(PartitionManager::tables()) as $table) {
            try {
                $report = $partitions->ensure($table);
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$table}: bajarilmadi ({$e->getMessage()}). Keyinroq qayta urinib ko'ring: `php artisan db:partitions:ensure`");

                continue;
            }
            if ($report['repaired'] !== []) {
                $this->info("{$table}: himoya triggeri tiklandi: " . implode(', ', $report['repaired']));
            }
            if (!$report['partitioned']) {
                $this->warn("{$table}: hali oylarga bo'linmagan, o'tkazib yuborildi (`php artisan db:partitions:status`)");

                continue;
            }
            $created = $report['created'] === [] ? "yangi bo'lim kerak emas" : 'yaratildi: ' . implode(', ', $report['created']);
            $moved = $report['moved'] > 0 ? "; DEFAULT dan ko'chirildi: {$report['moved']} qator" : '';
            $skipped = $report['skipped'] === [] ? '' : '; arxivlangan, qayta yaratilmadi: ' . implode(', ', $report['skipped']);
            $this->info("{$table}: {$created}{$moved}{$skipped}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
