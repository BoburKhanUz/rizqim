<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-53: the store's banquet payment rules (App\Domain\Pos\Banquet\Payments\BanquetPaymentSettings reads them with
 * the defaults): the schedule template, the cancellation rule table and the reminder days.
 *
 * @property int $id
 * @property int $store_id
 * @property list<array{kind: string, percent: float|int|string|null, days_before: int|null}>|null $schedule_template
 * @property list<array{min_days: int, retained_percent: float|int|string}>|null $cancellation_rules
 * @property list<int>|null $reminder_days
 * @property bool $overdue_reminders
 * @property int $version
 */
class BanquetPaymentSetting extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'overdue_reminders' => true,
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'schedule_template' => 'array',
            'cancellation_rules' => 'array',
            'reminder_days' => 'array',
            'overdue_reminders' => 'boolean',
            'version' => 'integer',
        ];
    }
}
