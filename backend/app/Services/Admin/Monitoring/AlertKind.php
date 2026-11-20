<?php

namespace App\Services\Admin\Monitoring;

use App\Services\Admin\Monitoring\Checks\AlertCheck;
use App\Services\Admin\Monitoring\Checks\BackupStaleCheck;
use App\Services\Admin\Monitoring\Checks\FailedJobsSpikeCheck;
use App\Services\Admin\Monitoring\Checks\FiscalFailuresCheck;
use App\Services\Admin\Monitoring\Checks\PosDeviceOfflineCheck;
use App\Services\Admin\Monitoring\Checks\PosSyncSignatureCheck;
use App\Services\Admin\Monitoring\Checks\QueueBacklogCheck;
use App\Services\Admin\Monitoring\Checks\SchedulerStaleCheck;
use App\Services\Admin\Monitoring\Checks\SettlementFailuresCheck;
use App\Services\Admin\Monitoring\Checks\DbConnectionsCheck;
use App\Services\Admin\Monitoring\Checks\DbLongTransactionCheck;
use App\Services\Admin\Monitoring\Checks\DbLockWaitsCheck;
use App\Services\Admin\Monitoring\Checks\WalArchiveCheck;
use App\Services\Admin\Monitoring\Checks\PitrBackupStaleCheck;
use App\Services\Admin\Monitoring\Checks\RestoreDrillStaleCheck;
use App\Services\Admin\Monitoring\Checks\DiskUsageCheck;
use App\Services\Admin\Monitoring\Checks\RedisMemoryCheck;
use App\Services\Admin\Monitoring\Checks\QueueWorkerStaleCheck;
use App\Services\Admin\Monitoring\Checks\RealtimeDownCheck;
use App\Services\Admin\Monitoring\Checks\TableBloatCheck;
use App\Services\Admin\Monitoring\Checks\PartitionsCheck;
use App\Services\Admin\Monitoring\Checks\TlsExpiryCheck;

/**
 * P4-15: the built-in alert rule kinds (Monitoring → Qoidalar). Each kind has a check (what the evaluator measures),
 * a severity, the meaning and bounds of its thresholds and its defaults. The rule row (App\Models\AlertRule) holds
 * the values a superadmin or operator changed; a missing row is created from the defaults.
 *
 * M3 (docs/scale/M3_DESIGN.md section 7) adds the server-level kinds, from db_connections on. A finding may carry its
 * own severity (a disk at 80 % warns, at 90 % it is critical); severity() is the kind's highest.
 */
enum AlertKind: string
{
    case FAILED_JOBS_SPIKE = 'failed_jobs_spike';
    case QUEUE_BACKLOG = 'queue_backlog';
    case POS_DEVICE_OFFLINE = 'pos_device_offline';
    case FISCAL_FAILURES = 'fiscal_failures';
    case SETTLEMENT_FAILURES = 'settlement_failures';
    case BACKUP_STALE = 'backup_stale';
    case SCHEDULER_STALE = 'scheduler_stale';
    case DB_CONNECTIONS = 'db_connections';
    case DB_LONG_TRANSACTION = 'db_long_transaction';
    case DB_LOCK_WAITS = 'db_lock_waits';
    case WAL_ARCHIVE = 'wal_archive';
    case PITR_BACKUP_STALE = 'pitr_backup_stale';
    case RESTORE_DRILL_STALE = 'restore_drill_stale';
    case DISK_USAGE = 'disk_usage';
    case REDIS_MEMORY = 'redis_memory';
    case QUEUE_WORKER_STALE = 'queue_worker_stale';
    case REALTIME_DOWN = 'realtime_down';
    case TABLE_BLOAT = 'table_bloat';
    case PARTITIONS = 'partitions';
    case TLS_EXPIRY = 'tls_expiry';
    // Wave M5 S3-d: the signed offline queue of the tills (docs/scale/M5_DESIGN.md section 4)
    case POS_SYNC_SIGNATURE = 'pos_sync_signature';

    public function label(): string
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE => 'Yiqilgan vazifalar ko\'payishi',
            self::QUEUE_BACKLOG => 'Navbat to\'planishi',
            self::POS_DEVICE_OFFLINE => 'Ish vaqtida oflayn kassa',
            self::FISCAL_FAILURES => 'Fiskal cheklar yiqilishi',
            self::SETTLEMENT_FAILURES => 'Hisob-kitob xatolari',
            self::BACKUP_STALE => 'Zaxira nusxa eskirgan',
            self::SCHEDULER_STALE => 'Scheduler to\'xtagan',
            self::DB_CONNECTIONS => 'Bazaga ulanishlar ko\'p',
            self::DB_LONG_TRANSACTION => 'Uzoq davom etayotgan tranzaksiya',
            self::DB_LOCK_WAITS => 'Bazada qulf kutayotgan so\'rovlar',
            self::WAL_ARCHIVE => 'Uzluksiz zaxira (WAL) yuborilmayapti',
            self::PITR_BACKUP_STALE => 'Uzluksiz zaxira (PITR) eskirgan',
            self::RESTORE_DRILL_STALE => 'Tiklash mashqi',
            self::DISK_USAGE => 'Disk to\'lib bormoqda',
            self::REDIS_MEMORY => 'Redis xotirasi to\'lib bormoqda',
            self::QUEUE_WORKER_STALE => 'Navbat ishchisi to\'xtagan',
            self::REALTIME_DOWN => 'Real vaqt (Reverb) ishlamayapti',
            self::TABLE_BLOAT => 'Jadvallarda o\'lik qatorlar ko\'p',
            self::PARTITIONS => 'Jurnallarning oylik bo\'limlari',
            self::TLS_EXPIRY => 'HTTPS sertifikati muddati tugayapti',
            self::POS_SYNC_SIGNATURE => 'Kassa navbati imzosi',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE => 'Oxirgi daqiqalarda yiqilgan fon vazifalari soni chegaradan oshdi.',
            self::QUEUE_BACKLOG => 'Navbatda vazifalar to\'planib qoldi yoki eng eskisi juda uzoq kutmoqda.',
            self::POS_DEVICE_OFFLINE => 'Ovqatxona ish vaqtida kassa yoki oshxona ekrani aloqada emas.',
            self::FISCAL_FAILURES => 'Fiskal chek soliq tizimida ro\'yxatdan o\'tmadi (yiqilgan cheklar).',
            self::SETTLEMENT_FAILURES => 'Yetkazilgan buyurtma puli hisobga yozilmadi.',
            self::BACKUP_STALE => 'Ma\'lumotlar bazasining oxirgi zaxira nusxasi belgilangan muddatdan eski.',
            self::SCHEDULER_STALE => 'Scheduler yurak urishi yo\'q yoki eskirgan: rejalashtirilgan ishlar ishlamayapti.',
            self::DB_CONNECTIONS => 'Postgres\'ga ochiq ulanishlar ruxsat etilgan eng ko\'p sonning belgilangan ulushidan oshdi: yangi so\'rovlar ulana olmay qolishi mumkin.',
            self::DB_LONG_TRANSACTION => 'Bazada bitta tranzaksiya juda uzoq ochiq turibdi: u jadvallarni tozalashni to\'xtatadi va boshqalarni qulflab qo\'yishi mumkin.',
            self::DB_LOCK_WAITS => 'Bir nechta so\'rov boshqa so\'rov qo\'ygan qulfni uzoq kutmoqda: kassalar va ilova sekinlashadi.',
            self::WAL_ARCHIVE => 'Bazaning o\'zgarishlar jurnali (WAL) zaxira omboriga yuborilmayapti: biror vaqtga qaytarib tiklash (PITR) zanjiri uzilmoqda.',
            self::PITR_BACKUP_STALE => 'Uzluksiz zaxiraning oxirgi nusxasi eski yoki zaxira xizmati holatini yozmay qo\'ydi.',
            self::RESTORE_DRILL_STALE => 'Zaxiradan tiklash mashqi uzoq vaqt o\'tkazilmagan yoki oxirgisi muvaffaqiyatsiz tugagan.',
            self::DISK_USAGE => 'Baza turgan disk to\'lib bormoqda yoki zaxira xizmati disk holatini yozmay qo\'ydi.',
            self::REDIS_MEMORY => 'Redis xotirasi chegaraga yaqin: to\'lsa navbatga vazifa qo\'shish va real vaqt hisoblagichlari ishlamay qoladi.',
            self::QUEUE_WORKER_STALE => 'Navbat ishchisi har daqiqalik tekshiruv vazifasini bajarmadi: Telegram xabarlari va fon vazifalari ketmayapti.',
            self::REALTIME_DOWN => 'Real vaqt yangilanishlari ishonchsiz: ilovalar tezroq so\'rov bilan ishlaydi, buyurtmalar yo\'qolmaydi.',
            self::TABLE_BLOAT => 'Asosiy jadvallarda o\'chirilgan, lekin tozalanmagan qatorlar ko\'p yoki uzoq vaqt tozalanmagan: so\'rovlar sekinlashadi.',
            self::PARTITIONS => 'Jurnallar (audit, fiskal, bildirishnoma) uchun oldindagi oylar tayyor emas, zaxira bo\'limda qatorlar bor yoki arxivlashda xato.',
            self::TLS_EXPIRY => 'Saytning HTTPS sertifikati muddati tugashiga oz qoldi: tugasa mijozlar ilovani ocholmaydi.',
            self::POS_SYNC_SIGNATURE => 'Kassaning internetsiz navbatidan kelgan amal imzosi noto\'g\'ri yoki navbatdan amal yo\'qolgan: navbat fayli qo\'lda o\'zgartirilgan bo\'lishi mumkin.',
        };
    }

    /**
     * M3: what the person on duty should do, one sentence (shown in the Telegram message and on the rules screen).
     */
    public function action(): string
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE => 'Monitoring → Yiqilgan vazifalar bo\'limida xatoni o\'qing va sababini tuzatib, vazifani qayta yuboring.',
            self::QUEUE_BACKLOG => 'Navbat ishchisi ishlayotganini tekshiring (docker compose ps queue) va kerak bo\'lsa qayta ishga tushiring.',
            self::POS_DEVICE_OFFLINE => 'Ovqatxonaga qo\'ng\'iroq qilib kassaning interneti va ilovasini tekshirtiring.',
            self::FISCAL_FAILURES => 'Yiqilgan cheklarni Kassalar bo\'limida ko\'ring va xato sababini (STIR, kassa raqami, OFD) tuzating.',
            self::SETTLEMENT_FAILURES => 'Moliya bo\'limida hisobga yozilmagan buyurtmalarni ko\'ring; tizim ularni har 10 daqiqada qayta urinadi.',
            self::BACKUP_STALE => 'backup xizmatini tekshiring: docker compose logs backup; so\'ng docker compose exec backup sh /usr/local/bin/backup.sh bilan zaxira oling.',
            self::SCHEDULER_STALE => 'scheduler xizmatini tekshiring va qayta ishga tushiring: docker compose restart scheduler.',
            self::DB_CONNECTIONS => 'Tafsilotlarda qaysi manzil ko\'p ulanish ochganini ko\'ring va o\'sha xizmatni qayta ishga tushiring.',
            self::DB_LONG_TRANSACTION => 'Tafsilotlardagi jarayon (pid) nima ekanini aniqlang; osilib qolgan bo\'lsa SELECT pg_terminate_backend(pid) bilan to\'xtating.',
            self::DB_LOCK_WAITS => 'Qulfni ushlab turgan uzoq tranzaksiyani toping (Uzoq davom etayotgan tranzaksiya ogohlantirishi) va uni to\'xtating.',
            self::WAL_ARCHIVE => 'Zaxira omborini (S3) va backup xizmati logini tekshiring; ishlay boshlagach to\'liq zaxira o\'zi olinadi.',
            self::PITR_BACKUP_STALE => 'backup xizmati ishlayotganini tekshiring (docker compose logs backup), so\'ng docker compose exec backup sh /usr/local/bin/backup.sh --pitr-backup bajaring.',
            self::RESTORE_DRILL_STALE => 'Serverda scripts/restore-drill.sh ni ishga tushiring va hisobotdagi xato qatorini o\'qing.',
            self::DISK_USAGE => 'Keraksiz fayllarni o\'chiring yoki diskni kengaytiring; pg_wal katta bo\'lsa WAL yuborilishini tekshiring.',
            self::REDIS_MEMORY => 'Navbatda to\'planib qolgan vazifalar bormi ko\'ring (navbat ishchisi), yoki Redis maxmemory ni oshiring.',
            self::QUEUE_WORKER_STALE => 'queue xizmatini qayta ishga tushiring: docker compose restart queue; logida xato bo\'lsa uni tuzating.',
            self::REALTIME_DOWN => 'reverb va queue xizmatlarini tekshiring: docker compose ps reverb queue; kerak bo\'lsa qayta ishga tushiring.',
            self::TABLE_BLOAT => 'Kechasi o\'sha jadvalga VACUUM (ANALYZE) bajaring; takrorlansa autovacuum sozlamasini ko\'rib chiqing.',
            self::PARTITIONS => 'php artisan db:partitions:ensure bajaring; xabar boshqa buyruqni aytsa, o\'shani bajaring.',
            self::TLS_EXPIRY => 'Sertifikatni yangilang (masalan certbot renew) va proksi serverni qayta yuklang.',
            self::POS_SYNC_SIGNATURE => 'Qurilmalar bo\'limida kassani oching, ovqatxona egasi bilan bog\'lanib o\'sha kungi sotuv va smena pulini solishtiring; kassa qayta o\'rnatilgan bo\'lsa bu kutilgan holat.',
        };
    }

    /**
     * critical | warning | info
     */
    public function severity(): string
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE, self::QUEUE_BACKLOG, self::POS_DEVICE_OFFLINE => 'warning',
            self::FISCAL_FAILURES, self::SETTLEMENT_FAILURES, self::BACKUP_STALE, self::SCHEDULER_STALE => 'critical',
            self::DB_LONG_TRANSACTION, self::DB_LOCK_WAITS, self::REALTIME_DOWN, self::TABLE_BLOAT, self::POS_SYNC_SIGNATURE => 'warning',
            // Their findings say warning or critical (restore_drill_stale: overdue warns, a failed drill is critical)
            self::DB_CONNECTIONS, self::WAL_ARCHIVE, self::PITR_BACKUP_STALE, self::RESTORE_DRILL_STALE, self::DISK_USAGE,
            self::REDIS_MEMORY, self::QUEUE_WORKER_STALE, self::PARTITIONS, self::TLS_EXPIRY => 'critical',
        };
    }

    /**
     * @return array{threshold: int, threshold_secondary: int|null, window_minutes: int|null}
     */
    public function defaults(): array
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE => ['threshold' => 10, 'threshold_secondary' => null, 'window_minutes' => 15],
            self::QUEUE_BACKLOG => ['threshold' => 500, 'threshold_secondary' => 10, 'window_minutes' => null],
            self::POS_DEVICE_OFFLINE => ['threshold' => 15, 'threshold_secondary' => null, 'window_minutes' => null],
            self::FISCAL_FAILURES => ['threshold' => 1, 'threshold_secondary' => null, 'window_minutes' => 60],
            self::SETTLEMENT_FAILURES => ['threshold' => 1, 'threshold_secondary' => null, 'window_minutes' => null],
            self::BACKUP_STALE => ['threshold' => 26, 'threshold_secondary' => null, 'window_minutes' => null],
            self::SCHEDULER_STALE => ['threshold' => 180, 'threshold_secondary' => null, 'window_minutes' => null],
            self::DB_CONNECTIONS => ['threshold' => 80, 'threshold_secondary' => null, 'window_minutes' => null],
            self::DB_LONG_TRANSACTION => ['threshold' => 300, 'threshold_secondary' => 60, 'window_minutes' => null],
            self::DB_LOCK_WAITS => ['threshold' => 3, 'threshold_secondary' => 30, 'window_minutes' => null],
            self::WAL_ARCHIVE => ['threshold' => 10, 'threshold_secondary' => 5, 'window_minutes' => null],
            self::PITR_BACKUP_STALE => ['threshold' => 26, 'threshold_secondary' => 8, 'window_minutes' => 15],
            self::RESTORE_DRILL_STALE => ['threshold' => 35, 'threshold_secondary' => null, 'window_minutes' => null],
            self::DISK_USAGE => ['threshold' => 80, 'threshold_secondary' => 90, 'window_minutes' => 15],
            self::REDIS_MEMORY => ['threshold' => 80, 'threshold_secondary' => 90, 'window_minutes' => null],
            self::QUEUE_WORKER_STALE => ['threshold' => 180, 'threshold_secondary' => null, 'window_minutes' => null],
            self::REALTIME_DOWN => ['threshold' => 3, 'threshold_secondary' => null, 'window_minutes' => null],
            self::TABLE_BLOAT => ['threshold' => 20, 'threshold_secondary' => 24, 'window_minutes' => null],
            self::PARTITIONS => ['threshold' => 2, 'threshold_secondary' => null, 'window_minutes' => null],
            self::TLS_EXPIRY => ['threshold' => 14, 'threshold_secondary' => 3, 'window_minutes' => null],
            self::POS_SYNC_SIGNATURE => ['threshold' => 1, 'threshold_secondary' => null, 'window_minutes' => 1440],
        };
    }

    /**
     * What each threshold means, for the rules screen and for validation: `threshold` always exists, the secondary
     * threshold and the window only for kinds that use them.
     *
     * @return array{threshold: array{label: string, unit: string, min: int, max: int}, threshold_secondary: array{label: string, unit: string, min: int, max: int}|null, window: array{label: string, unit: string, min: int, max: int}|null}
     */
    public function fields(): array
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE => [
                'threshold' => ['label' => 'Yiqilgan vazifalar soni', 'unit' => 'ta', 'min' => 1, 'max' => 10000],
                'threshold_secondary' => null,
                'window' => ['label' => 'Oxirgi qancha vaqt ichida', 'unit' => 'daqiqa', 'min' => 1, 'max' => 1440],
            ],
            self::QUEUE_BACKLOG => [
                'threshold' => ['label' => 'Navbatdagi vazifalar soni', 'unit' => 'ta', 'min' => 1, 'max' => 1000000],
                'threshold_secondary' => ['label' => 'Eng eski vazifa kutish vaqti', 'unit' => 'daqiqa', 'min' => 1, 'max' => 1440],
                'window' => null,
            ],
            self::POS_DEVICE_OFFLINE => [
                'threshold' => ['label' => 'Aloqasiz turgan vaqt (ish vaqti ichida)', 'unit' => 'daqiqa', 'min' => 5, 'max' => 720],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::FISCAL_FAILURES => [
                'threshold' => ['label' => 'Yiqilgan cheklar soni', 'unit' => 'ta', 'min' => 1, 'max' => 10000],
                'threshold_secondary' => null,
                'window' => ['label' => 'Oxirgi qancha vaqt ichida', 'unit' => 'daqiqa', 'min' => 5, 'max' => 10080],
            ],
            self::SETTLEMENT_FAILURES => [
                'threshold' => ['label' => 'Hisobga yozilmagan buyurtmalar soni', 'unit' => 'ta', 'min' => 1, 'max' => 10000],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::BACKUP_STALE => [
                'threshold' => ['label' => 'Oxirgi zaxiradan beri', 'unit' => 'soat', 'min' => 1, 'max' => 720],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::SCHEDULER_STALE => [
                'threshold' => ['label' => 'Yurak urishi eskirishi', 'unit' => 'soniya', 'min' => 60, 'max' => 3600],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::DB_CONNECTIONS => [
                'threshold' => ['label' => 'Band ulanishlar ulushi (max_connections dan)', 'unit' => '%', 'min' => 10, 'max' => 100],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::DB_LONG_TRANSACTION => [
                'threshold' => ['label' => 'Ishlayotgan tranzaksiya davomiyligi', 'unit' => 'soniya', 'min' => 30, 'max' => 86400],
                'threshold_secondary' => ['label' => 'Ochiq qolib, hech narsa qilmayotgan tranzaksiya', 'unit' => 'soniya', 'min' => 10, 'max' => 86400],
                'window' => null,
            ],
            self::DB_LOCK_WAITS => [
                'threshold' => ['label' => 'Qulf kutayotgan so\'rovlar soni', 'unit' => 'ta', 'min' => 1, 'max' => 1000],
                'threshold_secondary' => ['label' => 'Kutish vaqti', 'unit' => 'soniya', 'min' => 5, 'max' => 3600],
                'window' => null,
            ],
            self::WAL_ARCHIVE => [
                'threshold' => ['label' => 'Yuborilishini kutayotgan WAL fayllar', 'unit' => 'ta', 'min' => 1, 'max' => 100000],
                'threshold_secondary' => ['label' => 'Yuborish xato berib turgan vaqt', 'unit' => 'daqiqa', 'min' => 1, 'max' => 1440],
                'window' => null,
            ],
            self::PITR_BACKUP_STALE => [
                'threshold' => ['label' => 'Oxirgi zaxiradan beri (har qanday)', 'unit' => 'soat', 'min' => 1, 'max' => 720],
                'threshold_secondary' => ['label' => 'Oxirgi to\'liq zaxiradan beri', 'unit' => 'kun', 'min' => 1, 'max' => 60],
                'window' => ['label' => 'Zaxira xizmati holat yozmagan vaqt', 'unit' => 'daqiqa', 'min' => 5, 'max' => 1440],
            ],
            self::RESTORE_DRILL_STALE => [
                'threshold' => ['label' => 'Oxirgi muvaffaqiyatli mashqdan beri', 'unit' => 'kun', 'min' => 1, 'max' => 365],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::DISK_USAGE => [
                'threshold' => ['label' => 'Diqqat: band joy', 'unit' => '%', 'min' => 10, 'max' => 99],
                'threshold_secondary' => ['label' => 'Kritik: band joy', 'unit' => '%', 'min' => 10, 'max' => 99],
                'window' => ['label' => 'Zaxira xizmati disk holatini yozmagan vaqt', 'unit' => 'daqiqa', 'min' => 5, 'max' => 1440],
            ],
            self::REDIS_MEMORY => [
                'threshold' => ['label' => 'Diqqat: band xotira (maxmemory dan)', 'unit' => '%', 'min' => 10, 'max' => 99],
                'threshold_secondary' => ['label' => 'Kritik: band xotira', 'unit' => '%', 'min' => 10, 'max' => 99],
                'window' => null,
            ],
            self::QUEUE_WORKER_STALE => [
                'threshold' => ['label' => 'Navbat ishchisi javob bermagan vaqt', 'unit' => 'soniya', 'min' => 60, 'max' => 3600],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::REALTIME_DOWN => [
                'threshold' => ['label' => 'Ishonchsiz bo\'lib turgan vaqt', 'unit' => 'daqiqa', 'min' => 1, 'max' => 120],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::TABLE_BLOAT => [
                'threshold' => ['label' => 'O\'lik qatorlar ulushi (tirik qatorlardan)', 'unit' => '%', 'min' => 5, 'max' => 500],
                'threshold_secondary' => ['label' => 'Tozalanmagan vaqt (o\'lik qatorlar bor bo\'lsa)', 'unit' => 'soat', 'min' => 1, 'max' => 720],
                'window' => null,
            ],
            self::PARTITIONS => [
                'threshold' => ['label' => 'Oldindan tayyor turishi kerak bo\'lgan oylar', 'unit' => 'oy', 'min' => 1, 'max' => 3],
                'threshold_secondary' => null,
                'window' => null,
            ],
            self::TLS_EXPIRY => [
                'threshold' => ['label' => 'Diqqat: muddat tugashiga qolgan', 'unit' => 'kun', 'min' => 1, 'max' => 90],
                'threshold_secondary' => ['label' => 'Kritik: muddat tugashiga qolgan', 'unit' => 'kun', 'min' => 1, 'max' => 30],
                'window' => null,
            ],
            self::POS_SYNC_SIGNATURE => [
                'threshold' => ['label' => 'Bitta kassadan kelgan shubhali amallar soni', 'unit' => 'ta', 'min' => 1, 'max' => 10000],
                'threshold_secondary' => null,
                'window' => ['label' => 'Oxirgi qancha vaqt ichida', 'unit' => 'daqiqa', 'min' => 5, 'max' => 10080],
            ],
        };
    }

    /**
     * @return class-string<AlertCheck>
     */
    public function check(): string
    {
        return match ($this) {
            self::FAILED_JOBS_SPIKE => FailedJobsSpikeCheck::class,
            self::QUEUE_BACKLOG => QueueBacklogCheck::class,
            self::POS_DEVICE_OFFLINE => PosDeviceOfflineCheck::class,
            self::FISCAL_FAILURES => FiscalFailuresCheck::class,
            self::SETTLEMENT_FAILURES => SettlementFailuresCheck::class,
            self::BACKUP_STALE => BackupStaleCheck::class,
            self::SCHEDULER_STALE => SchedulerStaleCheck::class,
            self::DB_CONNECTIONS => DbConnectionsCheck::class,
            self::DB_LONG_TRANSACTION => DbLongTransactionCheck::class,
            self::DB_LOCK_WAITS => DbLockWaitsCheck::class,
            self::WAL_ARCHIVE => WalArchiveCheck::class,
            self::PITR_BACKUP_STALE => PitrBackupStaleCheck::class,
            self::RESTORE_DRILL_STALE => RestoreDrillStaleCheck::class,
            self::DISK_USAGE => DiskUsageCheck::class,
            self::REDIS_MEMORY => RedisMemoryCheck::class,
            self::QUEUE_WORKER_STALE => QueueWorkerStaleCheck::class,
            self::REALTIME_DOWN => RealtimeDownCheck::class,
            self::TABLE_BLOAT => TableBloatCheck::class,
            self::PARTITIONS => PartitionsCheck::class,
            self::TLS_EXPIRY => TlsExpiryCheck::class,
            self::POS_SYNC_SIGNATURE => PosSyncSignatureCheck::class,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind) => $kind->value, self::cases());
    }
}
