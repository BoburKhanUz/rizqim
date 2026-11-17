<?php

namespace App\Console\Commands;

use App\Support\Partitions\PartitionManager;
use Illuminate\Console\Command;

/**
 * Wave M3: the state of the journal partitions. Without options a table a person can read; --json prints the array of
 * PartitionManager::status() (the monitoring check and the scripts read it). Exit code 0 when everything is in order,
 * 1 when there is a problem (the problems list says what to run).
 */
class PartitionsStatusCommand extends Command
{
    protected $signature = 'db:partitions:status {--json : Mashina o\'qiydigan JSON}';

    protected $description = "Jurnal jadvallarining oylik bo'limlari va arxiv holati";

    public function handle(PartitionManager $partitions): int
    {
        $status = $partitions->status();

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $status['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Jadval', "Bo'lingan", 'Oylar', 'Eng eski', 'Eng yangi', 'Oldinda (oy)', 'DEFAULT qatorlar', 'Arxivga navbatda', 'Onlayn (oy)', 'Hajm'],
            array_map(fn (array $t) => [
                $t['table'],
                $t['partitioned'] ? 'ha' : "yo'q",
                $t['partitions'],
                $t['oldest_month'] ?? '-',
                $t['newest_month'] ?? '-',
                $t['future_months'],
                $t['default_rows'],
                $t['due_for_archive'],
                $t['online_months'] . ($t['archive'] ? ' (arxiv)' : " (o'chiriladi)"),
                $this->bytes((int) $t['bytes']),
            ], $status['tables']),
        );

        $a = $status['archives'];
        $this->line("Arxiv: ajratilgan {$a['detached']}, yuklangan {$a['uploaded']}, tasdiqlangan {$a['verified']}, o'chirilgan {$a['dropped']}, xato {$a['failed']}, qo'lda ulangan {$a['held']}"
            . ($a['local_only_allowed'] ? " (ARCHIVE_ALLOW_LOCAL_ONLY=1: tashqi nusxasiz ham o'chiriladi)" : ''));

        if ($status['ok']) {
            $this->info('Hammasi joyida.');

            return self::SUCCESS;
        }
        foreach ($status['problems'] as $problem) {
            $this->warn('- ' . $problem['message']);
        }

        return self::FAILURE;
    }

    private function bytes(int $bytes): string
    {
        $value = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($value < 1024) {
                return round($value, 1) . ' ' . $unit;
            }
            $value /= 1024;
        }

        return round($value, 1) . ' TB';
    }
}
