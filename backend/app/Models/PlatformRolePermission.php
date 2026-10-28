<?php

namespace App\Models;

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Eloquent\Model;

/**
 * P4-11: the superadmin's change to a platform role's default admin permissions (allowed = grant or take away).
 *
 * @property string $platform_role
 * @property string $permission
 * @property bool $allowed
 * @property int|null $updated_by
 */
class PlatformRolePermission extends Model
{
    protected $fillable = [
        'platform_role',
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
        // AdminPermissionService caches the overrides
        static::saved(fn () => AdminPermissionService::forgetOverrides());
        static::deleted(fn () => AdminPermissionService::forgetOverrides());
    }
}
