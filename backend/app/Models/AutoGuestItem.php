<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "choy-non" rule (P3-12, R11): a hall order gets `quantity_per_guest` of this variant per guest, when it opens and
 * when guests join. Branch-wide (hall_id null) or a hall's own, which replaces the branch rule of the same variant in
 * that hall (inactive: none there).
 *
 * @property numeric-string $quantity_per_guest
 * @property bool $is_active
 * @property int $sort_order
 */
class AutoGuestItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity_per_guest' => 'decimal:3',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }
}
