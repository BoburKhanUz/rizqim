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
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use SensitiveParameter;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property \App\Enums\UserRole $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $pin_set_at
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property string|null $phone_verified_via
 * @property string|null $pin_hash
 * @property string|null $staff_code
 * @property \App\Enums\CourierType $courier_type
 * @property \App\Enums\PlatformRole|null $platform_role
 * @property \Illuminate\Support\Carbon|null $platform_disabled_at
 * @property \Illuminate\Support\Carbon|null $platform_last_login_at
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_timestep
 * @property string|null $app_authentication_secret TOTP secret (encrypted cast from InteractsWithAppAuthentication)
 * @property array<int, string>|null $app_authentication_recovery_codes bcrypt hashes (encrypted:array cast)
 */
class User extends Authenticatable implements FilamentUser, HasTenants, HasAppAuthentication, HasAppAuthenticationRecovery
{
    // users.phone_verified_via (P1-34): the holder proved the phone with an SMS code, or a store owner/manager or the
    // super admin wrote it as a staff login phone (StaffPhoneLoginService::vouch)
    public const PHONE_VIA_SMS = 'sms';
    public const PHONE_VIA_VOUCHED = 'vouched';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, \App\Traits\HasPublicUuid, Auditable;
    // P4-11: the TOTP secret and hashed recovery codes (encrypted at rest, hidden), shared by the admin API
    // (App\Services\Admin\TwoFactorService) and the old Filament panels' app authentication
    use InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery {
        InteractsWithAppAuthentication::saveAppAuthenticationSecret as private saveFilamentAppAuthenticationSecret;
    }

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
        // Terminal PIN (bcrypt); never leaves the server. pin_hash, staff_code, phone_verified_at and phone_verified_via
        // are deliberately not mass assignable: StaffPinService, AccountLinkingService and StaffPhoneLoginService set them
        'pin_hash',
        // Internal: decides what the phone may do (P1-34), the apps only need phone_verified_at
        'phone_verified_via',
        // An unclaimed staff invite: whoever holds it becomes that employee. Only the owner's employee list shows it
        // (OwnerEmployeeController::index makes it visible there)
        'telegram_invite_token',
        // P4-11: team and 2FA bookkeeping, never in the store/customer APIs (the admin API shows members itself; the
        // secret and recovery codes are hidden by their Filament traits)
        'platform_role',
        'platform_disabled_at',
        'platform_last_login_at',
        'two_factor_confirmed_at',
        'two_factor_last_timestep',
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
            // P4-11: platform_role, platform_disabled_at and the 2FA columns are never mass assignable:
            // App\Services\Admin\PlatformTeamService and TwoFactorService set them
            'platform_role' => \App\Enums\PlatformRole::class,
            'platform_disabled_at' => 'datetime',
            'platform_last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_timestep' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A phone changed without an SMS code (profile form, admin panel) is no longer a verified phone
        static::saving(function (User $user) {
            if ($user->isDirty('phone') && !$user->isDirty('phone_verified_at')) {
                $user->phone_verified_at = null;
            }

            // A vouched phone is a store's word for its own staff member, not a proof of the number: it ends when the
            // account leaves the store roles (as a customer it would sign the number's real holder in to this account)
            if ($user->isDirty('role') && !in_array($user->role, \App\Enums\UserRole::storeRoles(), true)
                && $user->phone_verified_via === self::PHONE_VIA_VOUCHED) {
                $user->phone_verified_at = null;
            }

            // P4-11: users.role super_admin and platform_role superadmin go together, whichever path sets one of them
            // (the seeder, user:assign-role, the old panel's user form, PlatformTeamService)
            if ($user->isDirty('platform_role')) {
                if ($user->platform_role === \App\Enums\PlatformRole::SUPERADMIN) {
                    $user->role = \App\Enums\UserRole::SUPER_ADMIN;
                } elseif ($user->platform_role !== null) {
                    $user->role = \App\Enums\UserRole::PLATFORM;
                } elseif (in_array($user->role, [\App\Enums\UserRole::SUPER_ADMIN, \App\Enums\UserRole::PLATFORM], true)) {
                    $user->role = \App\Enums\UserRole::CUSTOMER;
                }
            } elseif ($user->isDirty('role')) {
                if ($user->role === \App\Enums\UserRole::SUPER_ADMIN) {
                    $user->platform_role = \App\Enums\PlatformRole::SUPERADMIN;
                } elseif ($user->role !== \App\Enums\UserRole::PLATFORM && $user->platform_role !== null) {
                    $user->platform_role = null;
                }
            } elseif ($user->role === \App\Enums\UserRole::SUPER_ADMIN && $user->platform_role === null) {
                $user->platform_role = \App\Enums\PlatformRole::SUPERADMIN;
            }

            // How it was verified goes with the verification; one set without saying how came from an SMS code
            if ($user->phone_verified_at === null) {
                $user->phone_verified_via = null;
            } elseif ($user->phone_verified_via === null) {
                $user->phone_verified_via = self::PHONE_VIA_SMS;
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

    // --- P4-11: the RIZQIM team -----------------------------------------------------------------------------------

    /**
     * A RIZQIM team member (users.platform_role set), disabled or not.
     */
    public function isPlatformStaff(): bool
    {
        return $this->platform_role !== null;
    }

    /**
     * A team member who may use the admin API (not disabled; an invited member without a password cannot sign in).
     */
    public function isActivePlatformStaff(): bool
    {
        return $this->platform_role !== null && $this->platform_disabled_at === null;
    }

    public function isPlatformSuperadmin(): bool
    {
        return $this->platform_role === \App\Enums\PlatformRole::SUPERADMIN;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    /**
     * Filament's set-up (and TwoFactorService) save the secret only once a code confirmed it.
     */
    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->two_factor_confirmed_at = $secret !== null ? now() : null;
        $this->two_factor_last_timestep = null;
        $this->saveFilamentAppAuthenticationSecret($secret);
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
        // P4-11: a disabled superadmin is out of the old panels too; 2FA is required there (SuperadminPanelProvider)
        if ($this->role === \App\Enums\UserRole::SUPER_ADMIN && $this->platform_disabled_at !== null) {
            return false;
        }

        if ($panel->getId() === 'superadmin') {
            return $this->role === \App\Enums\UserRole::SUPER_ADMIN;
        }

        // The store panel shares the session with /superadmin: a super admin enters it only with 2FA set up, so it is
        // no way around the code (AdminPanelProvider asks for it at sign-in)
        if ($this->role === \App\Enums\UserRole::SUPER_ADMIN && !$this->hasTwoFactorEnabled()) {
            return false;
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
