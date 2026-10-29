<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unsold portions of a batch taken off with a reason (P6-15, BatchService::writeOff). Quantity is in the batch's unit.
 *
 * @property numeric-string $quantity
 * @property \Illuminate\Support\Carbon $business_date
 */
class MenuBatchWriteOff extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $table = 'menu_batch_writeoffs';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'business_date' => 'date:Y-m-d',
    ];

    /**
     * @return BelongsTo<MenuBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MenuBatch::class, 'menu_batch_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function writtenOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_off_by');
    }
}
