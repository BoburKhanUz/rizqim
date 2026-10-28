<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-22: the team's CRM fields of a store (one row per store): the next contact date.
 *
 * @property int $store_id
 * @property \Illuminate\Support\Carbon|null $next_contact_on
 * @property int|null $updated_by
 * @property-read Store|null $store
 */
class StoreCrmProfile extends Model
{
    protected $primaryKey = 'store_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_contact_on' => 'date',
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
