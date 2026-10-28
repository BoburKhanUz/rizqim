<?php

namespace App\Models;

use App\Enums\StoreHealthLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-22: a store's last computed health (App\Services\Admin\Stores\StoreHealthCalculator).
 *
 * @property int $store_id
 * @property StoreHealthLevel $level
 * @property int $score
 * @property list<array{key: string, level: string, points: int, label: string}> $reasons
 * @property array<string, mixed> $metrics
 * @property \Illuminate\Support\Carbon $computed_at
 * @property-read Store|null $store
 */
class StoreHealthScore extends Model
{
    protected $primaryKey = 'store_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'level' => StoreHealthLevel::class,
            'reasons' => 'array',
            'metrics' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
