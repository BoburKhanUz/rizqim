<?php

namespace App\Models;

use App\Services\Auth\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store's change to a role's default permissions (allowed = grant or take away).
 *
 * @property bool $allowed
 */
class RolePermissionOverride extends Model
{
    protected $fillable = [
        'store_id',
        'role',
        'permission',
        'allowed',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // PermissionService caches each store's overrides
        static::saved(fn (RolePermissionOverride $override) => PermissionService::forgetStore((int) $override->store_id));
        static::deleted(fn (RolePermissionOverride $override) => PermissionService::forgetStore((int) $override->store_id));
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
