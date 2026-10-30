<?php

namespace App\Models;

use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolPeriod;
use App\Domain\Pos\Tips\TipPoolRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-22: how a branch shares its tips (App\Domain\Pos\Tips\TipSettings reads it with the defaults; a branch without a
 * row reads as them).
 *
 * @property int $store_id
 * @property int $branch_id
 * @property TipMode $mode
 * @property TipPoolRule $pool_rule
 * @property TipPoolPeriod $pool_period
 * @property list<string>|null $roles
 * @property array<string, string>|null $role_weights
 * @property int|null $updated_by
 */
class PosTipSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mode' => TipMode::class,
            'pool_rule' => TipPoolRule::class,
            'pool_period' => TipPoolPeriod::class,
            'roles' => 'array',
            'role_weights' => 'array',
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
