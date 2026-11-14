<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P6-53: what the cancellation of a banquet event computed and decided: the rule band (days before → retained %),
 * what was paid, the retained part (the manager's override with its reason) and the refunded part.
 *
 * @property int $id
 * @property int $store_id
 * @property int $branch_id
 * @property int $banquet_event_id
 * @property int $days_before
 * @property numeric-string $rule_percent
 * @property numeric-string $paid_amount
 * @property numeric-string $computed_retained
 * @property numeric-string $retained_amount
 * @property numeric-string $refund_amount
 * @property string|null $override_reason
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $business_date
 * @property int|null $cancelled_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class BanquetCancellation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'rule_percent' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'computed_retained' => 'decimal:2',
            'retained_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'business_date' => 'date',
        ];
    }
}
