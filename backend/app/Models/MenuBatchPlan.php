<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Keyingi partiya HH:MM" (P6-15): when a branch plans to cook a product next. Shown beside "tugadi" in the POS
 * bootstrap, the POS catalog's availability and the Mini App; it moves no stock. `status`: planned, started (the batch
 * was opened: BatchService::open) or canceled.
 *
 * @property \Illuminate\Support\Carbon $planned_at
 * @property \Illuminate\Support\Carbon $business_date
 * @property numeric-string|null $planned_quantity
 */
class MenuBatchPlan extends Model
{
    use \App\Traits\HasPublicUuid;

    public const PLANNED = 'planned';

    public const STARTED = 'started';

    public const CANCELED = 'canceled';

    protected $guarded = ['id'];

    protected $casts = [
        'planned_at' => 'datetime',
        'business_date' => 'date:Y-m-d',
        'planned_quantity' => 'decimal:3',
    ];

    /**
     * @param  Builder<MenuBatchPlan>  $query
     * @return Builder<MenuBatchPlan>
     */
    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::PLANNED);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
}
