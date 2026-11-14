<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-54: the store's production sheet, osh calculator, final-sale MXIK and contract settings
 * (App\Domain\Pos\Banquet\Ops\BanquetOpsSettings reads them with the defaults).
 *
 * @property int $id
 * @property int $store_id
 * @property numeric-string $reserve_percent
 * @property array<string, mixed>|null $osh
 * @property list<array{product_id: int, grams: int}>|null $portion_weights
 * @property string|null $package_mxik
 * @property string|null $package_package_code
 * @property string|null $hall_mxik
 * @property string|null $hall_package_code
 * @property string|null $extra_mxik
 * @property string|null $extra_package_code
 * @property string|null $contract_template
 * @property int|null $contract_year
 * @property int $contract_seq
 * @property int $version
 */
class BanquetOpsSetting extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'reserve_percent' => '0.00',
        'contract_seq' => 0,
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'reserve_percent' => 'decimal:2',
            'osh' => 'array',
            'portion_weights' => 'array',
            'contract_year' => 'integer',
            'contract_seq' => 'integer',
            'version' => 'integer',
        ];
    }
}
