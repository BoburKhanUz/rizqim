<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One option of a combo slot (P6-31): a dish variant, its pieces, an optional modifier preset and a price delta.
 *
 * @property int $quantity
 * @property numeric-string $price_delta
 * @property list<array{modifier_id: int, quantity?: int}>|null $modifiers
 * @property bool $is_default
 */
class ComboSlotOption extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'integer',
        'price_delta' => 'decimal:2',
        'modifiers' => 'array',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ComboSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ComboSlot::class, 'combo_slot_id');
    }
}
