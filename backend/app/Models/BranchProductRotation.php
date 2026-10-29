<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dish that rotates on one branch's "Bugungi menyu" (P6-15): sold there only while it is on the day's menu. The rotation
 * is per branch, like the menu itself (App\Domain\Pos\Menu\DailyMenuService).
 *
 * @property int $id
 * @property int $branch_id
 * @property int $product_id
 */
class BranchProductRotation extends Model
{
    protected $table = 'branch_product_rotation';

    protected $guarded = ['id'];

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
}
