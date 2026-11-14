<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-55: a brand switched on or off on one branch (no row: on).
 *
 * @property int $branch_id
 * @property int $brand_id
 * @property bool $is_active
 */
class BranchBrand extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'branch_id' => 'integer',
        'brand_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
