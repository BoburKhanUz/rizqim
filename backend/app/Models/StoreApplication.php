<?php

namespace App\Models;

use App\Enums\StoreApplicationStage;
use App\Enums\StoreFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P4-22: an application to join RIZQIM and its place in the onboarding pipeline (App\Enums\StoreApplicationStage).
 * Changes only through App\Services\Admin\Stores\ApplicationPipeline (public form, team edits, stage moves,
 * conversion to a store).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property StoreFormat $format
 * @property int|null $region_id
 * @property string|null $city
 * @property string $owner_name
 * @property string $phone
 * @property string|null $tin
 * @property string|null $comment
 * @property string $source website | telegram | admin
 * @property StoreApplicationStage $stage
 * @property \Illuminate\Support\Carbon|null $stage_changed_at
 * @property int|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property array<string, array{done_at: string, by: int|null}>|null $checklist
 * @property int|null $store_id
 * @property \Illuminate\Support\Carbon|null $converted_at
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property string|null $rejected_reason
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property int|null $created_by
 * @property string|null $ip
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Region|null $region
 * @property-read User|null $assignee
 * @property-read Store|null $store
 */
class StoreApplication extends Model
{
    use \App\Traits\HasPublicUuid;

    public const SOURCES = ['website', 'telegram', 'admin'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'format' => StoreFormat::class,
            'stage' => StoreApplicationStage::class,
            'stage_changed_at' => 'datetime',
            'due_on' => 'date',
            'checklist' => 'array',
            'converted_at' => 'datetime',
            'activated_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<StoreNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(StoreNote::class, 'application_id');
    }

    /**
     * @return HasMany<StoreTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(StoreTask::class, 'application_id');
    }
}
