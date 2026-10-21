<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A branch's weekend days and holidays for the room tariffs' day types (P3-12). The holidays of the branch POS
 * settings (branches.pos_settings.holidays, P3-13) count too (App\Domain\Pos\Hall\BusinessCalendar).
 *
 * @property list<int>|null $weekend_days ISO weekdays, 1 = Monday; null: Saturday and Sunday
 * @property list<string>|null $holidays Y-m-d
 */
class RoomTariffCalendar extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'weekend_days' => 'array',
        'holidays' => 'array',
    ];
}
