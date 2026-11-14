<?php

namespace App\Models;

use App\Domain\Pos\Banquet\BanquetSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-52: the rent of a hall for one slot (custom: the rent of a custom time).
 *
 * @property int $id
 * @property int $store_id
 * @property int $hall_id
 * @property BanquetSlot $slot
 * @property numeric-string $price
 */
class BanquetHallRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'slot' => BanquetSlot::class,
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }
}
