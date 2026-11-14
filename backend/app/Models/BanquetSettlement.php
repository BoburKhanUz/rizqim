<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-54: an event's final settlement (App\Domain\Pos\Banquet\Ops\BanquetSettlementService): the billed guests, the
 * parts of the total, the advances offset on the ONE sale receipt and an excess given back.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property int $banquet_event_id
 * @property int $order_id
 * @property int|null $bill_id
 * @property string|null $bill_uuid
 * @property int $guests_billed
 * @property numeric-string $package_total
 * @property numeric-string $hall_total
 * @property numeric-string $extras_total
 * @property numeric-string $orders_total
 * @property numeric-string $total
 * @property numeric-string $advances_total
 * @property numeric-string $applied_amount
 * @property list<array{bill_uuid: string, amount: string, at: string, reversed?: bool}>|null $applications
 * @property numeric-string $excess_amount
 * @property string $excess_status none | pending | refunded
 * @property string $status open | settled
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $settled_at
 */
class BanquetSettlement extends Model
{
    use \App\Traits\HasPublicUuid;

    public const OPEN = 'open';
    public const SETTLED = 'settled';

    protected $guarded = ['id'];

    protected $attributes = [
        'package_total' => '0.00',
        'hall_total' => '0.00',
        'extras_total' => '0.00',
        'orders_total' => '0.00',
        'total' => '0.00',
        'advances_total' => '0.00',
        'applied_amount' => '0.00',
        'excess_amount' => '0.00',
        'excess_status' => 'none',
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'guests_billed' => 'integer',
            'package_total' => 'decimal:2',
            'hall_total' => 'decimal:2',
            'extras_total' => 'decimal:2',
            'orders_total' => 'decimal:2',
            'total' => 'decimal:2',
            'advances_total' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'applications' => 'array',
            'excess_amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BanquetEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(BanquetEvent::class, 'banquet_event_id');
    }
}
