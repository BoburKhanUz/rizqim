<?php

namespace App\Models;

use App\Enums\StopListSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product, variant or modifier on a branch's stop-list, until removed or until `until` passes.
 *
 * @property StopListSubject $subject_type
 * @property \Illuminate\Support\Carbon|null $until
 */
class StopListEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'subject_type' => StopListSubject::class,
        'subject_id' => 'integer',
        'until' => 'datetime',
    ];

    /**
     * Entries in force now (no `until`, or one still ahead).
     *
     * @param  Builder<StopListEntry>  $query
     * @return Builder<StopListEntry>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('until')->orWhere('until', '>', now()));
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The product, variant or modifier the entry is about (soft-deleted ones included).
     */
    public function subject(): ?Model
    {
        $class = match ($this->subject_type) {
            StopListSubject::PRODUCT => Product::class,
            StopListSubject::VARIANT => ProductVariant::class,
            StopListSubject::MODIFIER => Modifier::class,
        };

        return $class::withTrashed()->find($this->subject_id);
    }
}
