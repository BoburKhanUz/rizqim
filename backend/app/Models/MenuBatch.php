<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A batch cooked on a branch (a kazan of plov, a tandir of somsa): quantity_produced and what is left, in the
 * variant's unit (portions, kg). Orders take from it atomically (CreateOrderAction), cancellations give it back
 * (OrderStatusService); while left is 0 or no batch is open, a batch-tracked product is "tugadi" on that branch.
 *
 * @property numeric-string $quantity_produced
 * @property numeric-string $quantity_left
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $sold_out_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 */
class MenuBatch extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity_produced' => 'decimal:3',
        'quantity_left' => 'decimal:3',
        // P3-31: sold offline beyond what was left (quantity_left stays at zero)
        'oversold_quantity' => 'decimal:3',
        // P6-15: portions taken off with a reason (menu_batch_writeoffs), the running sum
        'written_off_quantity' => 'decimal:3',
        'started_at' => 'datetime',
        'sold_out_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = ['is_open'];

    public function getIsOpenAttribute(): bool
    {
        return $this->closed_at === null;
    }

    public function left(): Quantity
    {
        return Quantity::of($this->quantity_left);
    }

    /**
     * @param  Builder<MenuBatch>  $query
     * @return Builder<MenuBatch>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

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
