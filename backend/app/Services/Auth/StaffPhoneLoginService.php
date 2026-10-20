<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Signing store staff (owner, manager, courier, cashier, waiter, cook) in by SMS code: the partner panel and the staff
 * apps (POST /auth/phone/verify with `account: staff`, docs/AUTH.md).
 *
 * Only a staff account whose phone is a login phone is found: users.phone_verified_at is set, i.e. the person proved
 * the number with an SMS code (Mini App / app phone verification), or someone with authority over the account set it
 * (the super admin for an owner, the owner or a manager for the staff below them: vouch()). A phone typed into one's
 * own profile without a code never signs anybody in, and a customer account never becomes staff here: customers are
 * not looked at, nothing is created.
 *
 * One phone may hold several staff accounts (the same person in two stores). The code is spent by then, so the answer
 * is a short-lived single-use selection ticket bound to that phone and those accounts; the client names the account
 * with it (POST /auth/phone/select-account).
 */
class StaffPhoneLoginService
{
    // The only phone shape the SMS login accepts (and the shape a vouched phone is stored in)
    public const PHONE_PATTERN = '/^\+998\d{9}$/';

    private const TICKET_TTL_SECONDS = 300;

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
        $entry = Cache::pull($this->ticketKey($ticket));

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
     * or the store's owner or a manager for the staff below them (the callers check that). The phone counts as
     * verified, so its holder can sign in by SMS code. Not for customers: their phone is proven by a code only.
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

        $staff->forceFill(['phone' => $phone, 'phone_verified_at' => $phone === null ? null : now()])->save();
    }

    private function ticketKey(string $ticket): string
    {
        return 'staff-login-selection:' . hash('sha256', $ticket);
    }
}
