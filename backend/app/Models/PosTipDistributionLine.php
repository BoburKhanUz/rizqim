<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-22: one person's share of a tip pool split (App\Domain\Pos\Tips\TipPoolDistributor): the basis it was worked out
 * from (1, minutes, sales in so'm or the role weight) and the amount in whole so'm.
 *
 * @property int $distribution_id
 * @property int|null $staff_id
 * @property string|null $role
 * @property numeric-string $basis
 * @property numeric-string $amount
 */
class PosTipDistributionLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'basis' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
