<?php

namespace App\Models;

use App\Domain\Pos\Tips\TipDistributionStatus;
use App\Domain\Pos\Tips\TipPoolRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-22: the split of a branch's tip pool for a business day or a shift (App\Domain\Pos\Tips\TipPoolDistributor).
 *
 * @property string $uuid
 * @property int $store_id
 * @property int $branch_id
 * @property \Illuminate\Support\Carbon $business_date
 * @property int|null $shift_id
 * @property TipPoolRule $rule
 * @property list<string>|null $roles
 * @property array<string, string>|null $role_weights
 * @property list<array{staff_id: int, minutes: int|null}>|null $participants
 * @property numeric-string $pool_amount
 * @property int $tips_count
 * @property TipDistributionStatus $status
 * @property \Illuminate\Support\Carbon|null $computed_at
 * @property int|null $computed_by
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $closed_by
 */
class PosTipDistribution extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'rule' => TipPoolRule::class,
            'roles' => 'array',
            'role_weights' => 'array',
            'participants' => 'array',
            'pool_amount' => 'decimal:2',
            'tips_count' => 'integer',
            'status' => TipDistributionStatus::class,
            'computed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PosTipDistributionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PosTipDistributionLine::class, 'distribution_id');
    }
}
