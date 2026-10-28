<?php

namespace App\Services\Admin\Monitoring;

use App\Services\Admin\Monitoring\Checks\AlertCheck;
use App\Services\Admin\Monitoring\Checks\BackupStaleCheck;
use App\Services\Admin\Monitoring\Checks\FailedJobsSpikeCheck;
use App\Services\Admin\Monitoring\Checks\FiscalFailuresCheck;
use App\Services\Admin\Monitoring\Checks\PosDeviceOfflineCheck;
use App\Services\Admin\Monitoring\Checks\QueueBacklogCheck;
use App\Services\Admin\Monitoring\Checks\SchedulerStaleCheck;
use App\Services\Admin\Monitoring\Checks\SettlementFailuresCheck;

/**
 * P4-15: the built-in alert rule kinds (Monitoring → Qoidalar). Each kind has a check (what the evaluator measures),
 * a severity, the meaning and bounds of its thresholds and its defaults. The rule row (App\Models\AlertRule) holds
 * the values a superadmin or operator changed; a missing row is created from the defaults.
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
