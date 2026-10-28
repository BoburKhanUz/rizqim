<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-22: a team note about a store or an application (CRM-lite).
 *
 * @property int $id
 * @property int|null $store_id
 * @property int|null $application_id
 * @property int|null $author_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read User|null $author
 */
class StoreNote extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
