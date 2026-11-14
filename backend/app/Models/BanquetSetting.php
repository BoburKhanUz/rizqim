<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-51/P6-52: the store's banquet settings (App\Domain\Pos\Banquet\BanquetSettings reads them with the defaults).
 *
 * @property int $id
 * @property int $store_id
 * @property array<string, mixed>|null $slots
 * @property int $hold_hours
 * @property array<int, mixed>|null $guest_warning_thresholds
 * @property string $window_start
 * @property string $window_end
 * @property string|null $advance_mxik
 * @property string|null $advance_package_code
 * @property string|null $cancellation_mxik
 * @property string|null $cancellation_package_code
 * @property string|null $quote_currency
 * @property int $version
 */
class BanquetSetting extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'hold_hours' => 72,
        'window_start' => '06:00',
        'window_end' => '23:00',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'hold_hours' => 'integer',
            'guest_warning_thresholds' => 'array',
            'version' => 'integer',
        ];
    }
}
