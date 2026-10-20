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
 * @property \Illuminate\Support\Carbon|null $pin_set_at
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property string|null $pin_hash
 * @property string|null $staff_code
 * @property \App\Enums\CourierType $courier_type
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
        // P1-24: only meaningful for couriers (own / fleet)
        'courier_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Terminal PIN (bcrypt); never leaves the server. pin_hash, staff_code and phone_verified_at are deliberately
        // not mass assignable: StaffPinService and AccountLinkingService set them
        'pin_hash',
        // An unclaimed staff invite: whoever holds it becomes that employee. Only the owner's employee list shows it
        // (OwnerEmployeeController::index makes it visible there)
        'telegram_invite_token',
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
            'pin_set_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'courier_type' => \App\Enums\CourierType::class,
        ];
    }

    protected static function booted(): void
    {
        // A phone changed without an SMS code (profile form, admin panel) is no longer a verified phone
        static::saving(function (User $user) {
            if ($user->isDirty('phone') && !$user->isDirty('phone_verified_at')) {
                $user->phone_verified_at = null;
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Device, $this>
     */
    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    /**
     * A real Telegram account to message: not empty and not the 'temp_…' placeholder of an unclaimed staff invite
     * (OwnerEmployeeController). App-only staff (P1-36) have none and are reached by push.
     */
    public function hasTelegram(): bool
    {
        return filled($this->telegram_id) && !str_starts_with((string) $this->telegram_id, 'temp_');
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
