<?php

namespace App\Console\Commands;

use App\Support\Partitions\PartitionArchiver;
use Illuminate\Console\Command;

/**
 * Wave M3: old journal months leave the live database (App\Support\Partitions\PartitionArchiver). Scheduled daily
 * 22:40 UTC (03:40 Tashkent). Audit and fiscal months are only detached here; scripts/archive-partitions.sh dumps,
 * uploads and verifies them, and a later run drops a month only once its off-site copy is verified.
 */
class PartitionsArchiveCommand extends Command
{
    protected $signature = 'db:partitions:archive';

    protected $description = "Eski oylarni jurnal jadvallaridan ajratadi (arxivga) va tashqi nusxasi tasdiqlanganlarini o'chiradi";

    public function handle(PartitionArchiver $archiver): int
    {
        $report = $archiver->run();

        foreach ($report['dropped'] as $name) {
            $this->info("{$name}: tashqi nusxasi tasdiqlangan, bazadan o'chirildi");
        }
        foreach ($report['kept'] as $name) {
            $this->warn("{$name}: qayta ulangan (held deb belgilanmagan), o'chirilmadi");
        }
        foreach ($report['detached'] as $name) {
            $this->info("{$name}: ajratildi, arxivga yuklanishini kutmoqda (zaxira xizmati, scripts/archive-partitions.sh)");
        }
        foreach ($report['deleted'] as $name) {
            $this->info("{$name}: eski bildirishnomalar, ajratildi va o'chirildi (arxivsiz)");
        }
        foreach ($report['failed'] as $name => $reason) {
            $this->warn("{$name}: hozir bajarilmadi (jadval band: {$reason}). Kassalarni kuttirmaslik uchun keyingi ishga tushishda qayta uriniladi.");
        }
        if (array_merge($report['dropped'], $report['kept'], $report['detached'], $report['deleted'], array_keys($report['failed'])) === []) {
            $this->info('Arxivlanadigan oy yo\'q.');
        }

        return $report['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
