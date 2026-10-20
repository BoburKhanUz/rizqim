<?php

namespace App\Models;

use App\Enums\PlaceFeeMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store's service charge (%) and place fee ("joy narxi"), or a branch's override of them
 * (App\Services\Order\DiningFeeCalculator).
 *
 * @property numeric-string $service_charge_percent
 * @property list<string>|null $service_charge_channels
 * @property numeric-string $place_fee_amount
 * @property PlaceFeeMode $place_fee_mode
 * @property list<string>|null $place_fee_channels
 */
class ServiceFeeSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'service_charge_percent' => 'decimal:2',
        'service_charge_channels' => 'array',
        'place_fee_amount' => 'decimal:2',
        'place_fee_mode' => PlaceFeeMode::class,
        'place_fee_channels' => 'array',
    ];

    protected $attributes = [
        'service_charge_percent' => '0.00',
        'place_fee_amount' => '0.00',
        'place_fee_mode' => 'per_person',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
