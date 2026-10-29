<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-23: a branch's staff pay settings (App\Domain\Pos\StaffPay\StaffPaySettings): the pay period.
 *
 * @property string $period App\Domain\Pos\StaffPay\PayPeriod value
 */
class StaffPaySetting extends Model
{
    protected $guarded = ['id'];
}
