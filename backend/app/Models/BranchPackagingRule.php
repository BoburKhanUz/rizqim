<?php

namespace App\Models;

use App\Domain\Pos\Takeaway\PackagingBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-34 (B8): one packaging rule of a branch (App\Domain\Pos\Takeaway\PackagingRules works it out and changes it).
 *
 * @property int $store_id
 * @property int $branch_id
 * @property string $name
 * @property PackagingBasis $basis
 * @property int|null $category_id
 * @property int|null $product_id
 * @property int $quantity
 * @property numeric-string $unit_price
 * @property string|null $mxik
 * @property string|null $package_code
 * @property string $unit
 * @property array<int, string> $order_types App\Domain\Pos\Takeaway\PackagingOrderType values
 * @property bool $is_active
 * @property int $sort_order
 */
class BranchPackagingRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'branch_id' => 'integer',
            'basis' => PackagingBasis::class,
            'category_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'order_types' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
