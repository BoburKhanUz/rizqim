<?php

namespace App\Models;

use App\Domain\Pos\Setup\KitchenStationRouter;
use App\Enums\KitchenStationKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kitchen station of a branch (osh/qozon, mangal, salat va bar, choy...): the printer ticket and the KDS screen a
 * line is sent to (P3-13, POS_SPEC 2.8).
 *
 * @property KitchenStationKind $kind
 * @property bool $is_active
 * @property bool $show_on_kds
 */
class KitchenStation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'kind' => KitchenStationKind::class,
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'show_on_kds' => 'boolean',
    ];

    protected $attributes = [
        'kind' => 'kitchen',
        'sort_order' => 0,
        'is_active' => true,
        'show_on_kds' => true,
    ];

    protected static function booted(): void
    {
        // Routes to an inactive or deleted station are dropped by the resolver, which keeps them for the request
        $forget = fn (self $station) => app(KitchenStationRouter::class)->forget((int) $station->branch_id);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<KitchenStationRoute, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(KitchenStationRoute::class, 'station_id');
    }

    /**
     * @return HasMany<Printer, $this>
     */
    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class, 'station_id');
    }
}
