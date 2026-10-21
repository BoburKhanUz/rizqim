<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float $radius_km
 * @property \App\Enums\BranchStatus $status
 * P3-13 (POS settings): `business_day_starts_at` is a time column ("05:00:00"); pos_settings holds the validated keys
 * of App\Domain\Pos\Setup\PosSettings (read them through PosSettings::resolve())
 * @property array<string, mixed>|null $pos_settings
 */
class Branch extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_km' => 'float',
            'status' => \App\Enums\BranchStatus::class,
            // P3-13
            'pos_settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
