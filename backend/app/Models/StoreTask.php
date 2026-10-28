<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-22: a team task about a store or an application ("payshanba qo'ng'iroq qilish"), with a due date (Asia/Tashkent)
 * and an assignee (CRM-lite). Open tasks due today or earlier show in "Diqqat talab qiladi".
 *
 * @property int $id
 * @property int|null $store_id
 * @property int|null $application_id
 * @property string $title
 * @property \Illuminate\Support\Carbon $due_on
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $done_at
 * @property int|null $done_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read Store|null $store
 * @property-read StoreApplication|null $application
 */
class StoreTask extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'done_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<StoreTask>  $query
     * @return Builder<StoreTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('done_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<StoreApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(StoreApplication::class, 'application_id');
    }
}
