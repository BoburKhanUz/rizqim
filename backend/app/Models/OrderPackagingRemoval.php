<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-34 (B8): a packaging line taken off one order with a reason (the guest brought their own bag). The order's
 * packaging columns already reflect it (App\Domain\Pos\Takeaway\PackagingRules).
 *
 * @property int $order_id
 * @property int $rule_id
 * @property string $reason
 * @property int|null $removed_by
 */
class OrderPackagingRemoval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'rule_id' => 'integer',
            'removed_by' => 'integer',
        ];
    }
}
