<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Promo extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'store_id' => 'integer',
        'product_id' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * What the Mini App shows right now: switched on and inside its schedule (P4-31; no dates = always).
     *
     * @param  Builder<Promo>  $query
     * @return Builder<Promo>
     */
    public function scopeLive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    /**
     * The banner as the catalog (GET /catalog without a store) lists it, so the admin preview shows the same thing.
     *
     * @return array{id: int, title: string, image_url: string|null, store_uuid: string|null, product_id: int|null}
     */
    public function toCatalogEntry(): array
    {
        $storeUuid = $this->store?->uuid;
        if ($this->product && $this->product->store) {
            $storeUuid = $this->product->store->uuid;
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->image_url,
            'store_uuid' => $storeUuid,
            'product_id' => $this->product_id,
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
