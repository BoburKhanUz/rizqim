<?php

namespace App\Models;

use App\Services\Admin\Monitoring\AlertKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P4-15: the thresholds and switches of one built-in alert rule kind (App\Services\Admin\Monitoring\AlertKind).
 *
 * @property int $id
 * @property string $kind
 * @property int $threshold
 * @property int|null $threshold_secondary
 * @property int|null $window_minutes
 * @property bool $enabled
 * @property bool $telegram
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AlertRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'threshold_secondary' => 'integer',
            'window_minutes' => 'integer',
            'enabled' => 'boolean',
            'telegram' => 'boolean',
        ];
    }

    public function kindEnum(): AlertKind
    {
        return AlertKind::from($this->kind);
    }

    /**
     * Every built-in kind has a row; one that is missing (a fresh database) is created from the kind's defaults.
     *
     * @return \Illuminate\Support\Collection<string, AlertRule> kind value => rule, in AlertKind order
     */
    public static function ensureAll(): \Illuminate\Support\Collection
    {
        $existing = static::query()->get()->keyBy('kind');

        $rules = collect();
        foreach (AlertKind::cases() as $kind) {
            $rules[$kind->value] = $existing[$kind->value]
                ?? static::query()->firstOrCreate(['kind' => $kind->value], $kind->defaults() + ['enabled' => true, 'telegram' => true]);
        }

        return $rules;
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
