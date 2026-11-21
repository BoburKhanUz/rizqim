<?php

namespace App\Models;

use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6): one released build of a POS app for one platform. Platform-wide, not
 * a store's data (no BelongsToStore): managed by the RIZQIM team in the control centre
 * (App\Http\Controllers\Api\V1\Admin\AdminPosReleaseController), read by App\Domain\Pos\Releases\PosAppReleases.
 *
 * @property int $id
 * @property string $uuid
 * @property string $app App\Enums\DeviceApp value
 * @property string $platform android | windows
 * @property string $version e.g. 1.4.0
 * @property int $build
 * @property string $file_url
 * @property string $sha256
 * @property int $size_bytes
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $released_at
 * @property bool $is_minimum versions below this one are unsupported
 * @property \Illuminate\Support\Carbon|null $minimum_from the day the notice turns red
 * @property \Illuminate\Support\Carbon|null $withdrawn_at
 * @property int|null $created_by
 */
class PosAppRelease extends Model
{
    use HasPublicUuid;

    public const PLATFORMS = ['android', 'windows'];

    protected $table = 'pos_app_releases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'build' => 'integer',
            'size_bytes' => 'integer',
            'released_at' => 'datetime',
            'is_minimum' => 'boolean',
            'minimum_from' => 'datetime',
            'withdrawn_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  Builder<PosAppRelease>  $query
     * @return Builder<PosAppRelease>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('withdrawn_at');
    }

    /** "1.4.0+7": the form a terminal reports in X-Rizqim-App-Version. */
    public function label(): string
    {
        return $this->version . '+' . $this->build;
    }
}
