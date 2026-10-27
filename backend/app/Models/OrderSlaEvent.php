<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P4-14: a marketplace order's SLA stage crossed into warning or late (once per order, stage and state; written by
 * App\Services\Admin\Orders\OrderSlaWatcher).
 *
 * @property string $stage accept | prepare | deliver
 * @property string $state warning | late
 * @property int $limit_minutes
 * @property \Illuminate\Support\Carbon $crossed_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class OrderSlaEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'limit_minutes' => 'integer',
            'crossed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
