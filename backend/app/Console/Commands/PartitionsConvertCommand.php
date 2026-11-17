<?php

namespace App\Console\Commands;

use App\Support\Partitions\PartitionConverter;
use App\Support\Partitions\PartitionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Wave M3 (design section 4): converts a journal table with data into a table partitioned by month while the system
 * keeps working (App\Support\Partitions\PartitionConverter). Run at night:
 *   php artisan db:partitions:convert audit_logs            copy in batches (stop with Ctrl+C any time, run again to go on)
 *   php artisan db:partitions:convert audit_logs --finish   the short locked switch-over (seconds)
 *   php artisan db:partitions:convert audit_logs --rollback within 7 days: back to the plain table, nothing lost
 *   php artisan db:partitions:convert audit_logs --drop-old after 7 days: the kept old table is dropped
 */
class PartitionsConvertCommand extends Command
{
    protected $signature = 'db:partitions:convert
        {table : audit_logs, fiscal_receipts yoki notification_logs}
        {--batch=20000 : Bir tranzaksiyada ko\'chiriladigan qatorlar}
        {--finish : Ko\'chirishni tugatib, jadvallarni almashtirish (qisqa qulf)}
        {--rollback : Almashtirishdan keyin 7 kun ichida oddiy jadvalga qaytish}
        {--drop-old : 7 kundan keyin saqlangan eski jadvalni o\'chirish}';

    protected $description = "Ma'lumotli jurnal jadvalini tizimni to'xtatmasdan oylik bo'limlarga o'tkazadi";

    public function handle(PartitionConverter $converter): int
    {
        $table = (string) $this->argument('table');
        try {
            PartitionManager::table($table);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
        $modes = array_filter(['finish' => $this->option('finish'), 'rollback' => $this->option('rollback'), 'drop-old' => $this->option('drop-old')]);
        if (count($modes) > 1) {
            $this->error('Bir vaqtda faqat bittasi: --finish, --rollback yoki --drop-old');

            return self::INVALID;
        }
        $batch = max(100, (int) $this->option('batch'));

        $lock = Cache::lock("partitions:convert:{$table}", 6 * 3600);
        if (!$lock->get()) {
            $this->error("{$table}: boshqa konversiya jarayoni ishlayapti. U tugashini kuting.");

            return self::FAILURE;
        }

        try {
            return match (array_key_first($modes)) {
                'finish' => $this->finish($converter, $table, $batch),
                'rollback' => $this->rollback($converter, $table, $batch),
                'drop-old' => $this->dropOld($converter, $table),
                default => $this->copy($converter, $table, $batch),
            };
        } catch (Throwable $e) {
            $this->error("{$table}: to'xtadi: {$e->getMessage()}");
            $this->line("Hech narsa yo'qolmadi: buyruqni qayta ishga tushirsangiz, to'xtagan joyidan davom etadi.");

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function copy(PartitionConverter $converter, string $table, int $batch): int
    {
        $state = $converter->state($table);
        if ($state === 'partitioned' || $state === 'swapped') {
            $this->info("{$table} allaqachon oylik bo'limlarga o'tkazilgan.");

            return self::SUCCESS;
        }
        $converter->prepare($table);
        $live = $converter->progress($table)['live_max_id'];
        $this->info("{$table}: ko'chirish boshlandi (oxirgi id {$live}). To'xtatish mumkin: qayta ishga tushirsangiz davom etadi.");
        $copied = $converter->copy($table, $batch, function (int $rows, int $maxId) use ($live) {
            $percent = $live > 0 ? min(100, (int) floor($maxId * 100 / $live)) : 100;
            $this->line("  ... id {$maxId} gacha ({$percent}%)");
        });
        $this->info("{$table}: {$copied} qator ko'chirildi. Indekslar quriladi...");
        $converter->build($table);
        $caught = $converter->catchUp($table, $batch);
        $this->info("{$table}: tayyor ({$caught} ta o'zgargan qator yangilandi). Endi: `php artisan db:partitions:convert {$table} --finish` (tunda).");

        return self::SUCCESS;
    }

    private function finish(PartitionConverter $converter, string $table, int $batch): int
    {
        if ($converter->state($table) === 'plain') {
            $this->error("{$table}: avval `php artisan db:partitions:convert {$table}` (ko'chirish) bajarilsin.");

            return self::FAILURE;
        }
        if ($converter->state($table) !== 'copying') {
            $this->info("{$table} allaqachon almashtirilgan.");

            return self::SUCCESS;
        }
        $converter->finish($table, $batch);
        $keep = (int) config('partitions.keep_old_days', 7);
        $this->info("{$table}: oylik bo'limlarga o'tkazildi. Eski jadval {$table}_m3_old {$keep} kun saqlanadi (`--rollback` bilan qaytish mumkin), so'ng `--drop-old`.");
        $this->line('Keyingi qadam: `php artisan migrate` (migratsiya bu jadvalni o\'tkazib yuboradi).');

        return self::SUCCESS;
    }

    private function rollback(PartitionConverter $converter, string $table, int $batch): int
    {
        $result = $converter->rollback($table, $batch);
        $this->info("{$table}: oddiy jadvalga qaytarildi ({$result['rows_plain']} qator).");
        if (!$result['dropped_partitioned']) {
            $ids = $result['differing_ids'] === [] ? '' : ' Farq qiluvchi id: ' . implode(', ', $result['differing_ids']) . '.';
            $this->warn("Bo'lingan nusxa ({$table}_m3_new, {$result['rows_partitioned']} qator) mazmuni mos kelmagani uchun o'chirilmadi.{$ids} Texnik mutaxassisga ko'rsating.");
        }

        return self::SUCCESS;
    }

    private function dropOld(PartitionConverter $converter, string $table): int
    {
        $result = $converter->dropOld($table);
        if ($result['days_left'] > 0) {
            $this->warn("{$table}_m3_old hali {$result['days_left']} kun saqlanadi (orqaga qaytish imkoniyati). Keyinroq bajaring.");

            return self::FAILURE;
        }
        if (!$result['dropped']) {
            $ids = $result['differing_ids'] === [] ? '' : ' Mazmuni farq qiluvchi id: ' . implode(', ', $result['differing_ids']) . '.';
            $this->error("{$table}: eski jadvalda {$result['rows_old']} qator, yangisida {$result['rows_new']}: mos emas, hech narsa o'chirilmadi.{$ids} Texnik mutaxassisga ko'rsating.");

            return self::FAILURE;
        }
        $this->info("{$table}_m3_old o'chirildi ({$result['rows_old']} qatorning mazmuni yangi jadvaldagi bilan bir xilligi tekshirildi).");

        return self::SUCCESS;
    }
}
