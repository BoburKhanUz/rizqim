<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The dishes a branch cooks on one business day (P6-15, "Bugungi menyu"). See App\Domain\Pos\Menu\DailyMenuService.
 *
 * @property \Illuminate\Support\Carbon $menu_date
 */
class DailyMenu extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'menu_date' => 'date:Y-m-d',
    ];

    /**
     * @return HasMany<DailyMenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DailyMenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
