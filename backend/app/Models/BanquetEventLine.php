<?php

namespace App\Models;

use App\Domain\Pos\Banquet\BanquetLineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-52: a line an event is billed for: amount = unit_price × quantity × (persons ?? 1). `frozen`: its price was
 * stamped on the contract day; later catalogue edits do not change it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $banquet_event_id
 * @property BanquetLineKind $kind
 * @property string $name
 * @property int|null $banquet_package_id
 * @property int|null $banquet_extra_id
 * @property int|null $hall_id
 * @property int|null $product_variant_id
 * @property int|null $order_item_id
 * @property numeric-string $quantity
 * @property int|null $persons
 * @property numeric-string $unit_price
 * @property numeric-string $amount
 * @property bool $frozen
 * @property string|null $note
 * @property int $sort_order
 */
class BanquetEventLine extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $attributes = [
        'quantity' => '1.000',
        'unit_price' => '0.00',
        'amount' => '0.00',
        'frozen' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => BanquetLineKind::class,
            'quantity' => 'decimal:3',
            'persons' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'frozen' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BanquetEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(BanquetEvent::class, 'banquet_event_id');
    }

    /**
     * @return BelongsTo<BanquetExtra, $this>
     */
    public function extra(): BelongsTo
    {
        return $this->belongsTo(BanquetExtra::class, 'banquet_extra_id');
    }
}
