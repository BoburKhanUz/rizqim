<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-23: a branch's staff pay settings (App\Domain\Pos\StaffPay\StaffPaySettings): the pay period and the sales
 * attribution of a table handed to another waiter.
 *
 * @property string $period App\Domain\Pos\StaffPay\PayPeriod value
 * @property string $sales_attribution App\Domain\Pos\StaffPay\SalesAttribution value
 */
class StaffPaySetting extends Model
{
    protected $guarded = ['id'];
}
