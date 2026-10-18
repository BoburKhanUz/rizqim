<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Filament\Panel;

use App\Traits\Auditable;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property \App\Enums\UserRole $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, \App\Traits\HasPublicUuid, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'store_id',
        'branch_id',
        'telegram_id',
        'telegram_username',
        'telegram_photo_url',
        'role',
        'phone',
        'address',
        'telegram_invite_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => \App\Enums\UserRole::class,
        ];
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Store, $this>
     */
    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Branch, $this>
     */
    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Order, $this>
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Address, $this>
     */
    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->role === \App\Enums\UserRole::SUPER_ADMIN;
        }

        // Store staff must belong to a store (tenant); storeless staff accounts are invalid
        if ($this->role !== \App\Enums\UserRole::SUPER_ADMIN && $this->store_id === null) {
            return false;
        }

        return in_array($this->role, [
            \App\Enums\UserRole::SUPER_ADMIN, 
            \App\Enums\UserRole::OWNER, 
            \App\Enums\UserRole::MANAGER
        ]);
    }

    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->role === \App\Enums\UserRole::SUPER_ADMIN) {
            return Store::all();
        }

        return $this->store ? [$this->store] : [];
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->role === \App\Enums\UserRole::SUPER_ADMIN) {
            return true;
        }

        return $this->store_id === $tenant->getKey();
    }
}
