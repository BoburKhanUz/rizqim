<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

use App\Traits\Auditable;
use RuntimeException;
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
 * @property \App\Enums\PlatformRole|null $platform_role Wave P: the primary role (the first of platform_roles), kept for SQL filters and old clients
 * @property array<int, string>|null $platform_roles Wave P: every platform role the member holds, PlatformRole::cases() order
 * @property \Illuminate\Support\Carbon|null $platform_disabled_at
 * @property \Illuminate\Support\Carbon|null $platform_last_login_at
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_timestep
 * @property string|null $app_authentication_secret TOTP secret (encrypted cast)
 * @property array<int, string>|null $app_authentication_recovery_codes bcrypt hashes (encrypted:array cast)
 * @property \Illuminate\Support\Carbon|null $blocked_at P4-31: blocked from Mijozlar va yordam (no sign-in, no token works)
 * @property string|null $blocked_reason
 * @property int|null $blocked_by
 */
class User extends Authenticatable
{
    // users.phone_verified_via (P1-34): the holder proved the phone with an SMS code, or a store owner/manager or the
    // super admin wrote it as a staff login phone (StaffPhoneLoginService::vouch)
    public const PHONE_VIA_SMS = 'sms';
    public const PHONE_VIA_VOUCHED = 'vouched';

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
        // Terminal PIN (bcrypt); never leaves the server. pin_hash, staff_code, phone_verified_at and phone_verified_via
        // are deliberately not mass assignable: StaffPinService, AccountLinkingService and StaffPhoneLoginService set them
        'pin_hash',
        // Internal: decides what the phone may do (P1-34), the apps only need phone_verified_at
        'phone_verified_via',
        // An unclaimed staff invite: whoever holds it becomes that employee. Only the owner's employee list shows it
        // (OwnerEmployeeController::index makes it visible there)
        'telegram_invite_token',
        // P4-11: team and 2FA bookkeeping, never in the store/customer APIs (the admin API shows members itself)
        'platform_role',
        'platform_roles',
        'platform_disabled_at',
        'platform_last_login_at',
        'two_factor_confirmed_at',
        'two_factor_last_timestep',
        // P4-11: the TOTP secret and the hashed recovery codes (TwoFactorService)
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        // P4-31: who blocked the account and why is the platform's business (App\Services\Admin\Customers\UserBlocking
        // sets these; a blocked account gets no token anyway)
        'blocked_at',
        'blocked_reason',
        'blocked_by',
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
            // Wave P: read it through platformRoles(); the saving hook below keeps it and platform_role together
            'platform_roles' => 'array',
            'platform_disabled_at' => 'datetime',
            'platform_last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_timestep' => 'integer',
            // P4-11: encrypted at rest with APP_KEY. P4-33: the same casts the old Filament app-authentication traits
            // declared (`encrypted` = Crypt::encryptString of the secret, `encrypted:array` = of the JSON list of bcrypt
            // hashes), so the secrets and codes of members enrolled before still read (UserTwoFactorStorageTest)
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            // P4-31: never mass assignable, App\Services\Admin\Customers\UserBlocking sets it
            'blocked_at' => 'datetime',
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

            // P4-11: users.role super_admin and the platform role superadmin go together, whichever path sets one of
            // them (the seeder, user:assign-role, the old panel's user form, PlatformTeamService). Wave P: the set
            // (platform_roles) is what counts; a write of the legacy single platform_role replaces the set with it
            $roles = null; // the new set, or null: unchanged
            if ($user->isDirty('platform_roles')) {
                $roles = self::normalizePlatformRoles($user->getAttribute('platform_roles') ?? []);
            } elseif ($user->isDirty('platform_role')) {
                $roles = $user->platform_role !== null ? [$user->platform_role] : [];
            }

            if ($roles !== null) {
                if (in_array(\App\Enums\PlatformRole::SUPERADMIN, $roles, true)) {
                    $user->role = \App\Enums\UserRole::SUPER_ADMIN;
                } elseif ($roles !== []) {
                    $user->role = \App\Enums\UserRole::PLATFORM;
                } elseif (in_array($user->role, [\App\Enums\UserRole::SUPER_ADMIN, \App\Enums\UserRole::PLATFORM], true)) {
                    $user->role = \App\Enums\UserRole::CUSTOMER;
                }
            } elseif ($user->isDirty('role')) {
                $current = $user->platformRoles();
                if ($user->role === \App\Enums\UserRole::SUPER_ADMIN) {
                    $roles = self::normalizePlatformRoles([...$current, \App\Enums\PlatformRole::SUPERADMIN]);
                } elseif ($user->role !== \App\Enums\UserRole::PLATFORM && $current !== []) {
                    $roles = [];
                } elseif ($user->role === \App\Enums\UserRole::PLATFORM && in_array(\App\Enums\PlatformRole::SUPERADMIN, $current, true)) {
                    // Review 16: superadmin goes with users.role super_admin (the database CHECK ties them)
                    $roles = array_values(array_filter($current, fn (\App\Enums\PlatformRole $role) => $role !== \App\Enums\PlatformRole::SUPERADMIN));
                }
            } elseif ($user->role === \App\Enums\UserRole::SUPER_ADMIN && $user->platformRoles() === []) {
                $roles = [\App\Enums\PlatformRole::SUPERADMIN];
            }

            if ($roles !== null) {
                $user->setAttribute('platform_roles', $roles !== [] ? array_map(fn (\App\Enums\PlatformRole $role) => $role->value, $roles) : null);
                $user->platform_role = $roles[0] ?? null;
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
     * Wave P: every platform role the member holds, in PlatformRole::cases() order (superadmin first); [] outside the
     * team. A row read without platform_roles (a narrow select for a name list) falls back to its primary role.
     *
     * @return list<\App\Enums\PlatformRole>
     */
    public function platformRoles(): array
    {
        if (!array_key_exists('platform_roles', $this->getAttributes())) {
            return $this->platform_role !== null ? [$this->platform_role] : [];
        }
        $stored = $this->getAttribute('platform_roles');

        return is_array($stored) ? self::normalizePlatformRoles($stored) : [];
    }

    public function hasPlatformRole(\App\Enums\PlatformRole $role): bool
    {
        return in_array($role, $this->platformRoles(), true);
    }

    /**
     * Wave P: a set of platform roles (enums or their values) without repeats, in PlatformRole::cases() order.
     *
     * @param  iterable<\App\Enums\PlatformRole|string>  $roles
     * @return list<\App\Enums\PlatformRole>
     *
     * @throws \InvalidArgumentException for a value that is no platform role
     */
    public static function normalizePlatformRoles(iterable $roles): array
    {
        $held = [];
        foreach ($roles as $role) {
            $enum = $role instanceof \App\Enums\PlatformRole ? $role : \App\Enums\PlatformRole::tryFrom((string) $role);
            if ($enum === null) {
                throw new \InvalidArgumentException('Not a platform role: ' . json_encode($role));
            }
            $held[$enum->value] = true;
        }

        return array_values(array_filter(\App\Enums\PlatformRole::cases(), fn (\App\Enums\PlatformRole $role) => isset($held[$role->value])));
    }

    /**
     * A RIZQIM team member (at least one platform role), disabled or not.
     */
    public function isPlatformStaff(): bool
    {
        return $this->platformRoles() !== [];
    }

    /**
     * A team member who may use the admin API (not disabled; an invited member without a password cannot sign in).
     */
    public function isActivePlatformStaff(): bool
    {
        return $this->isPlatformStaff() && $this->platform_disabled_at === null;
    }

    /**
     * P4-25: a superadmin or RIZQIM team account (also one whose role and platform role are out of step). Such an account
     * only ever holds an admin session token: Telegram / phone sign-in, store and customer tokens are refused for it.
     */
    public function isPlatformAccount(): bool
    {
        return $this->isPlatformStaff()
            || $this->platform_role !== null
            || in_array($this->role, [\App\Enums\UserRole::SUPER_ADMIN, \App\Enums\UserRole::PLATFORM], true);
    }

    /**
     * Keshbek review H1: whether this users row carries keshbek that a delete would drop without a record: a non-zero
     * balance (keshbek held, the platform's liability, or owed after a clawback) or friends it referred (users.referred_by
     * is ON DELETE SET NULL: they would lose their referrer). Any user can be a referrer, staff included
     * (ReferralService refuses only unclaimed invite rows, which older data may still name). Read from the database,
     * not from the loaded model.
     */
    public function holdsKeshbek(): bool
    {
        return self::query()->whereKey($this->getKey())->where('balance', '<>', 0)->exists()
            || self::query()->where('referred_by', $this->getKey())->exists();
    }

    /**
     * Takes the person out of their store and keeps the users row (OwnerEmployeeController::destroy for staff with
     * history or keshbek, mergeInviteInto for an invite row with keshbek): the customer role; no store, branch, staff
     * code, invite token or PIN; and no session: Sanctum tokens (device-access ones included), refresh tokens and
     * push tokens end (TokenService::revokeAllForUser). Balance and referred friends are kept. Call it on a row read
     * under its lock (lockForUpdate) when the decision depends on what the row holds.
     */
    public function retireFromStore(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->forceFill([
                'role' => \App\Enums\UserRole::CUSTOMER, 'store_id' => null, 'branch_id' => null, 'staff_code' => null,
                'telegram_invite_token' => null, 'pin_hash' => null, 'pin_set_at' => null,
            ])->save();
            app(\App\Services\Auth\TokenService::class)->revokeAllForUser($this);
        });
    }

    /**
     * What deleting this users row would take away (OwnerEmployeeController::destroy, mergeInviteInto). As a CUSTOMER at
     * ANY store (ON DELETE CASCADE on user_id): orders (and, from them, the money tables), complaints, ratings. As STAFF
     * (ON DELETE SET NULL, or CASCADE for work sessions): the name on what they did. The cheap, indexed questions first:
     * the audit journal names the actor of nearly everything done at a till or in the panel (audit_logs.user_id), then
     * deliveries (orders.courier_id), work sessions and tips; then the money columns that have no index of their own,
     * within the person's store: orders.waiter_id, pos_shifts.opened_by / closed_by, cash_movements.user_id,
     * payments.received_by, pos_refunds.user_id. Keshbek (review H1): holdsKeshbek. Only "exists" comes back.
     *
     * Reads other stores' rows: a caller inside a tenant transaction asks it as the login role (TenantRls::asApp, the
     * owner's request in OwnerEmployeeController); mergeInviteInto runs outside any tenant (the bot's webhook and the
     * Mini App's sign-in), as the login role already.
     */
    public function hasHistory(): bool
    {
        $id = $this->getKey();
        $db = \Illuminate\Support\Facades\DB::connection($this->getConnectionName());
        $own = fn (string $table) => $db->table($table)->where('store_id', $this->store_id);

        return $db->table('orders')->where('user_id', $id)->exists()
            || $db->table('complaints')->where('user_id', $id)->exists()
            || $db->table('store_ratings')->where('user_id', $id)->exists()
            || $db->table('audit_logs')->where('user_id', $id)->exists()
            || $db->table('orders')->where('courier_id', $id)->exists()
            || $db->table('staff_work_sessions')->where('user_id', $id)->exists()
            || $db->table('pos_tips')->where('staff_id', $id)->exists()
            || $own('orders')->where('waiter_id', $id)->exists()
            || $own('pos_shifts')->where(fn ($q) => $q->where('opened_by', $id)->orWhere('closed_by', $id))->exists()
            || $own('cash_movements')->where('user_id', $id)->exists()
            || $own('payments')->where('received_by', $id)->exists()
            || $own('pos_refunds')->where('user_id', $id)->exists()
            || $this->holdsKeshbek();
    }

    /**
     * An unclaimed employee invite row (this) accepted by someone who already has an account in the store ($account:
     * the bot's /start and the Mini App's start_param): $account takes $attributes (the invite's role, branch, name).
     * Owner decision A: the invite row's keshbek goes to $account too (handKeshbekTo): its balance, a debt included,
     * with a KeshbekTransfer record, and its referred friends (its id can be a referral link's from before
     * ReferralService refused invite rows), who name $account as referrer from now on. Review M2: first, when $account
     * was itself referred by the invite row and one of its own orders earned the row a bonus that still stands, that
     * bonus is taken back (takeBackOwnBonus): $account never keeps a bonus for its own order, which no refund could
     * take back any more once its referrer is gone. Holding nothing any more, the invite row is then deleted, unless it
     * has history (hasHistory: an order of its own, ON DELETE CASCADE, or anything else the employee delete keeps a
     * row for; or keshbek still, a friend who named it after the lock, which no current path does): then it is
     * retired instead (retireFromStore).
     *
     * One transaction. Review M2: the stores of $account's orders whose bonus the invite row holds are locked first
     * (Store::ledgerLock, in id order), then every users row it changes, in one statement in id order: both rows and
     * the invite row's friends (read just before; the order the referral grant and clawback take a store, then a
     * customer and its referrer, LedgerService::lockCustomerAndReferrer, so neither deadlocks with it). Everything is
     * read again under the locks: a grant to the invite row or a referral naming it either committed before (and is
     * taken back or moved) or waits; a grant waiting on a friend then finds the friend's referrer changed and grants
     * nothing (a later order of the friend earns it for the new referrer).
     */
    public function mergeInviteInto(self $account, array $attributes): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($account, $attributes) {
            $stores = self::query()->whereKey($account->getKey())->where('referred_by', $this->getKey())->exists()
                ? $this->ownBonusOrders((int) $account->getKey())->pluck('store_id')->unique()->sort()->values()->all() : [];
            if ($stores !== []) {
                Store::query()->whereKey($stores)->orderBy('id')->lock(Store::ledgerLock())->get();
            }
            $friends = self::query()->where('referred_by', $this->getKey())->pluck('id')->all();
            self::query()->whereKey(array_values(array_unique([$account->getKey(), $this->getKey(), ...$friends])))->orderBy('id')->lockForUpdate()->get();
            $this->refresh();
            $account->refresh();
            $account->update($attributes);

            $this->takeBackOwnBonus($account);
            $this->handKeshbekTo($account);

            if (!$this->hasHistory()) {
                $this->delete();

                return;
            }

            $this->retireFromStore();
        });
    }

    /**
     * Review M2: $customerId's orders (every store, soft-deleted ones too) whose platform-paid referral bonus rows add up
     * to more than 0 (a bonus that stands; LedgerService::referralRewardedNet's rows), the oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    private function ownBonusOrders(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()->withoutGlobalScope(\App\Support\Tenancy\StoreScope::class)->withTrashed()
            ->where('user_id', $customerId)
            ->whereIn('id', StoreTransaction::query()->withoutGlobalScope(\App\Support\Tenancy\StoreScope::class)
                ->select('order_id')->where('type', \App\Enums\StoreTransactionType::REFERRAL_BONUS->value)
                ->whereNotNull('order_id')->groupBy('order_id')->havingRaw('SUM(amount) > 0'))
            ->orderBy('id')->get();
    }

    /**
     * Review M2, inside mergeInviteInto (the rows locked): $account was referred by this row, so a bonus one of
     * $account's own orders earned this row is taken back now, through the clawback (LedgerService::
     * clawBackReferralReward: −5 000 on this row, a −5 000 referral_bonus row on that order with
     * referralMergeClawbackNote), which the keshbek report (`clawed_back`), the monthly report and the monthly cap
     * count as any clawback. A later full refund of the order then finds nothing standing and takes nothing again. A
     * legacy bonus the store paid is not taken back (RIZQIM never paid it), as on a refund.
     */
    private function takeBackOwnBonus(self $account): void
    {
        if ((int) $account->referred_by !== (int) $this->getKey()) {
            return;
        }
        $ledger = app(\App\Services\Finance\LedgerService::class);
        foreach ($this->ownBonusOrders((int) $account->getKey()) as $order) {
            $ledger->clawBackReferralReward($order, null, \App\Services\Finance\LedgerService::referralMergeClawbackNote((int) $account->getKey(), (string) $order->short_code));
        }
        $this->refresh();
    }

    /**
     * Owner decision A, inside mergeInviteInto (the rows locked): this row's friends name $account as referrer, a
     * non-zero balance moves to $account — this row at 0, $account plus the balance (minus, for a debt), a
     * KeshbekTransfer saying so. Platform-wide the sum of the balances does not change (KeshbekLiabilityReport:
     * `transferred`, outside the reconciliation's movement). Never a self-referral: the friend that is $account itself
     * gets no referrer. Review L5: never a cycle of two either: a friend $account names as its own referrer gets no
     * referrer (not $account).
     */
    private function handKeshbekTo(self $account): void
    {
        $accountId = (int) $account->getKey();
        $accountReferrer = $account->referred_by !== null ? (int) $account->referred_by : null;
        $friends = self::query()->where('referred_by', $this->getKey())->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $moved = array_values(array_filter($friends, fn (int $id) => $id !== $accountId && $id !== $accountReferrer));
        $cleared = array_values(array_diff($friends, $moved));
        if ($moved !== []) {
            self::query()->whereKey($moved)->update(['referred_by' => $accountId]);
        }
        if ($cleared !== []) {
            self::query()->whereKey($cleared)->update(['referred_by' => null]);
        }

        $balance = \App\Support\Money::of((string) $this->balance);
        if (!$balance->isZero()) {
            self::query()->whereKey($this->getKey())->update(['balance' => '0.00']);
            // A numeric string keeps the amount exact (a debt is negative); Laravel accepts it although the stub says float|int
            self::query()->whereKey($accountId)->increment('balance', $balance->toString()); // @phpstan-ignore argument.type
            KeshbekTransfer::create([
                'from_user_id' => (int) $this->getKey(),
                'to_user_id' => $accountId,
                'amount' => $balance->toString(),
                'reason' => KeshbekTransfer::REASON_INVITE_MERGE,
                'moved_friend_ids' => $moved === [] ? null : $moved,
            ]);
        }

        $this->refresh();
        $account->refresh();
    }

    /**
     * P4-31: blocked in the Boshqaruv markazi (Mijozlar va yordam): signs in nowhere and no token of it works.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Wave P: superadmin is one of the member's roles (whatever else they hold).
     */
    public function isPlatformSuperadmin(): bool
    {
        return $this->hasPlatformRole(\App\Enums\PlatformRole::SUPERADMIN);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    // --- P4-11: 2FA storage (TwoFactorService is the only writer) ---------------------------------------------------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    /**
     * Saved only once a code confirmed it (TwoFactorService::enable); null turns 2FA off.
     */
    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->two_factor_confirmed_at = $secret !== null ? now() : null;
        $this->two_factor_last_timestep = null;
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /**
     * @return array<int, string>|null bcrypt hashes of the unused recovery codes
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<int, string>|null  $codes  bcrypt hashes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;

        if (!$this->save()) {
            throw new RuntimeException('Failed to save app authentication recovery codes.');
        }
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
}
