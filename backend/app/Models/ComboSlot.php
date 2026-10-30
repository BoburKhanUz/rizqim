<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One slot of a combo (P6-31): "Asosiy", "Ichimlik", "Sous"; min_picks 0 makes it optional.
 *
 * @property int $min_picks
 * @property int $max_picks
 * @property bool $allow_modifiers
 */
class ComboSlot extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'min_picks' => 'integer',
        'max_picks' => 'integer',
        'allow_modifiers' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return HasMany<ComboSlotOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ComboSlotOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Combo, $this>
     */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }
}
