<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-41: a branch's QR payment settings (App\Domain\Pos\QrPayments\QrPaymentSettings reads it with the defaults).
 *
 * @property int $store_id
 * @property int $branch_id
 * @property list<string>|null $providers
 * @property string|null $default_provider
 * @property int $expiry_minutes
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PosQrSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'providers' => 'array',
            'expiry_minutes' => 'integer',
        ];
    }
}
