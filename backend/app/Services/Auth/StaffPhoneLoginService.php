<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signing store staff (owner, manager, courier, cashier, waiter, cook) in by SMS code: the partner panel and the staff
 * apps (POST /auth/phone/verify with `account: staff`, docs/AUTH.md).
 *
 * Only a staff account whose phone is a login phone is found: users.phone_verified_at is set, i.e. the person proved
 * the number with an SMS code (Mini App / app phone verification, phone_verified_via = sms), or someone with authority
 * over the account set it (the super admin for an owner, the owner or a manager for the staff below them: vouch(),
 * phone_verified_via = vouched). A phone typed into one's
 * own profile without a code never signs anybody in, and a customer account never becomes staff here: customers are
 * not looked at, nothing is created.
 *
 * One phone may hold several staff accounts (the same person in two stores). The code is spent by then, so the answer
 * is a short-lived single-use selection ticket bound to that phone and those accounts; the client names the account
 * with it (POST /auth/phone/select-account).
 *
 * P1-34: users.phone_verified_via says which of the two it was (sms | vouched). Every set, change or clear of a login
 * phone is audited (staff.login_phone_set / _changed / _cleared, the phone masked) and a change or clear ends the
 * account's app sessions. A phone the person proved by SMS is never replaced through the employee form.
 */
class StaffPhoneLoginService
{
    // The only phone shape the SMS login accepts (and the shape a vouched phone is stored in)
    public const PHONE_PATTERN = '/^\+998\d{9}$/';

    private const TICKET_TTL_SECONDS = 300;

    public function __construct(
        protected AuditLogger $audit,
        protected TokenService $tokens,
    ) {}

    /**
     * The staff accounts the verified phone signs in to, oldest first.
     *
     * @return Collection<int, User>
     */
    public function accountsFor(string $phone): Collection
    {
        return User::query()
            ->where('phone', $phone)
            ->whereNotNull('phone_verified_at')
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::storeRoles()))
            ->whereNotNull('store_id')
            ->whereHas('store')
            ->with(['store:id,name', 'branch:id,name'])
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    /**
     * A selection ticket for $accounts (all holding $phone). Only its hash is kept, for TICKET_TTL_SECONDS.
     *
     * @param  Collection<int, User>  $accounts
     */
    public function issueTicket(string $phone, Collection $accounts): string
    {
        $ticket = 'sel_' . Str::random(48);

        Cache::put($this->ticketKey($ticket), [
            'phone' => $phone,
            'user_ids' => $accounts->modelKeys(),
        ], self::TICKET_TTL_SECONDS);

        return $ticket;
    }

    /**
     * Spend a ticket (whatever the outcome: a wrong guess does not leave it usable) and return the chosen account, null
     * when the ticket is unknown, expired or used, the account was not offered with it, or no longer holds the phone.
     */
    public function redeemTicket(string $ticket, int $userId): ?User
    {
        // Read and forget under a lock: Cache::pull is not atomic on every store, two concurrent redeems get one entry
        $key = $this->ticketKey($ticket);
        $entry = Cache::lock($key . ':redeem', 10)->get(fn () => Cache::pull($key));

        if (!is_array($entry) || !in_array($userId, array_map('intval', (array) ($entry['user_ids'] ?? [])), true)) {
            return null;
        }

        // Still a login phone of that account (the phone or the role may have changed meanwhile)
        return $this->accountsFor((string) $entry['phone'])->firstWhere('id', $userId);
    }

    /**
     * The accounts as the client lists them to choose from.
     *
     * @param  Collection<int, User>  $accounts
     * @return list<array{user_id: int, name: string, role: string, role_label: string, store: array{id: int, name: string}, branch: array{id: int, name: string}|null}>
     */
    public function describe(Collection $accounts): array
    {
        return $accounts->map(fn (User $user) => [
            'user_id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->role->value,
            'role_label' => $user->role->labelUz(),
            'store' => ['id' => (int) $user->store_id, 'name' => (string) $user->store?->name],
            'branch' => $user->branch ? ['id' => (int) $user->branch->id, 'name' => (string) $user->branch->name] : null,
        ])->values()->all();
    }

    /**
     * Set (or clear) a staff member's login phone on the authority of whoever manages the account: the super admin,
     * or the store's owner or a manager for the staff below them (the callers check that, and the employee form
     * refuses to replace an SMS-proven phone: refusalForStoreVouch()). The phone counts as verified (`vouched`), so
     * its holder can sign in by SMS code. Not for customers: their phone is proven by a code only. The same login
     * phone again is a no-op (an SMS-proven one stays SMS-proven).
     */
    public function vouch(User $staff, ?string $phone): void
    {
        if (!$staff->role->isStoreStaff()) {
            throw new \InvalidArgumentException('Only a store staff account gets a vouched login phone.');
        }

        $phone = $phone === null || trim($phone) === '' ? null : trim($phone);
        if ($phone !== null && !preg_match(self::PHONE_PATTERN, $phone)) {
            throw new \InvalidArgumentException('A login phone is +998 and nine digits.');
        }

        $previous = self::loginPhone($staff);
        $previousVia = $staff->phone_verified_via;
        if ($phone !== null && $phone === $previous) {
            return;
        }

        DB::transaction(function () use ($staff, $phone, $previous, $previousVia) {
            // Two saves: the new phone first drops the old verification (User::booted), then it is verified, even when
            // the old one was verified within the same second (an unchanged timestamp would not count as set)
            $staff->forceFill(['phone' => $phone, 'phone_verified_at' => null])->save();
            if ($phone !== null) {
                $staff->forceFill(['phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_VOUCHED])->save();
            }

            $this->loginPhoneChanged($staff, $previous, $previousVia);
        });
    }

    /**
     * Audit a staff account's login phone going from $previous to its current one (set, changed or cleared) and, when
     * it had one, end its app sessions: whoever signed in with the old number must sign in again. For the vouch above
     * and for the paths that drop a login phone otherwise (the super admin editing the phone or the role).
     */
    public function loginPhoneChanged(User $staff, ?string $previous, ?string $previousVia = null, ?int $storeId = null): void
    {
        $current = self::loginPhone($staff);
        if ($previous === $current) {
            return;
        }

        $action = match (true) {
            $previous === null => 'staff.login_phone_set',
            $current === null => 'staff.login_phone_cleared',
            default => 'staff.login_phone_changed',
        };

        // AuditRedactor masks the phone fields; `via` says how each was established
        $this->audit->record($action, $staff, ['phone' => $previous], ['phone' => $current], array_filter([
            'via' => $current !== null ? $staff->phone_verified_via : null,
            'previous_via' => $previous !== null ? $previousVia : null,
        ]), array_filter(['store_id' => $storeId ?? $staff->store_id]));

        if ($previous !== null) {
            $this->tokens->revokeAppSessions($staff);
        }
    }

    /**
     * Whether a store's owner or manager may put $phone on $staff (an existing account, or null for a new one) in the
     * employee form, null when yes, else the reason to show:
     * - a phone the employee proved by SMS is theirs: it is not replaced or cleared there;
     * - a phone the holder proved by SMS on a staff account of another store is not vouched by a store they do not
     *   belong to (it would put that store in their account picker); they prove it here themselves, or the platform
     *   vouches for it.
     */
    public function refusalForStoreVouch(?User $staff, ?string $phone, ?int $storeId): ?string
    {
        $phone = $phone === null || trim($phone) === '' ? null : trim($phone);

        if ($staff !== null && $staff->phone_verified_via === User::PHONE_VIA_SMS && $staff->phone_verified_at !== null
            && $phone !== $staff->phone) {
            return "Bu raqamni xodimning o'zi SMS kod bilan tasdiqlagan, uni bu yerda o'zgartirib yoki o'chirib bo'lmaydi. "
                . "Raqam almashsa, xodim yangi raqamni ilovada SMS kod bilan tasdiqlaydi.";
        }

        if ($phone === null || $phone === $staff?->phone) {
            return null;
        }

        $provenElsewhere = User::query()
            ->where('phone', $phone)
            ->whereNotNull('phone_verified_at')
            ->where('phone_verified_via', User::PHONE_VIA_SMS)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::storeRoles()))
            ->when($staff !== null, fn ($query) => $query->whereKeyNot($staff->id))
            ->where(fn ($query) => $query->whereNull('store_id')->orWhere('store_id', '!=', $storeId))
            ->exists();

        return $provenElsewhere
            ? "Bu raqam egasi uni boshqa do'kondagi xodim hisobida SMS kod bilan tasdiqlagan. Xodimni raqamsiz qo'shing: "
                . "u taklif havolasi orqali kirib, raqamini o'zi tasdiqlaydi, yoki qo'llab-quvvatlash xizmatiga murojaat qiling."
            : null;
    }

    /**
     * The phone an account signs in with by SMS, if any: a verified one.
     */
    public static function loginPhone(User $user): ?string
    {
        return $user->phone_verified_at !== null && is_string($user->phone) && $user->phone !== '' ? $user->phone : null;
    }

    private function ticketKey(string $ticket): string
    {
        return 'staff-login-selection:' . hash('sha256', $ticket);
    }
}
