<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One customer identity across Telegram and the mobile app, keyed by an SMS-verified phone (users.phone_verified_at).
 * A phone typed into a profile without a code is never used to link: otherwise anyone could type a stranger's
 * number and have that stranger's mobile login land in their account.
 *
 * - Phone sign-in (mobile app): the one customer with that verified phone signs in; none: a new customer is created;
 *   two or more: 409, nothing changes.
 * - Verifying a phone while signed in (Mini App): stored on the account. When another customer already holds it, the
 *   current account is merged into that one only if it is a fresh Telegram account with nothing in it and the other
 *   has no Telegram yet (the Telegram id moves over; the Mini App's next request gets a 401 and re-authenticates
 *   into the linked account). Anything else is a 409 with no data touched.
 */
class AccountLinkingService
{
    public function __construct(
        protected TokenService $tokens,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array{user: User, created: bool}
     *
     * @throws AccountLinkConflictException
     * @throws PlatformAccountSignInException a superadmin / platform account holds the phone and no customer does
     */
    public function customerForVerifiedPhone(string $phone): array
    {
        return $this->locked($phone, function () use ($phone) {
            $matches = $this->verifiedCustomers($phone)->limit(2)->get();

            if ($matches->count() > 1) {
                throw new AccountLinkConflictException();
            }

            if ($matches->count() === 1) {
                return ['user' => $matches->first(), 'created' => false];
            }

            // P4-25: no customer account for a number that belongs to the control center's team
            if ($this->platformAccountHoldsPhone($phone)) {
                throw new PlatformAccountSignInException();
            }

            $user = new User(['name' => 'Mijoz', 'role' => UserRole::CUSTOMER]);
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();

            return ['user' => $user, 'created' => true];
        });
    }

    /**
     * Whether a superadmin / platform account holds $phone (verified or not): such a phone never signs anybody in by
     * SMS code and never gets a new customer account.
     */
    public function platformAccountHoldsPhone(string $phone): bool
    {
        return User::where('phone', $phone)
            ->where(fn ($query) => $query->whereNotNull('platform_role')
                ->orWhereIn('role', [UserRole::SUPER_ADMIN->value, UserRole::PLATFORM->value]))
            ->exists();
    }

    /**
     * @return array{user: User, linked: bool} the account to continue with, and whether it is another one now
     *
     * @throws AccountLinkConflictException
     */
    public function attachVerifiedPhone(User $user, string $phone): array
    {
        return $this->locked($phone, function () use ($user, $phone) {
            // Staff accounts keep the old behaviour: a phone is just a contact detail there
            if ($user->role !== UserRole::CUSTOMER) {
                $user->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();

                return ['user' => $user, 'linked' => false];
            }

            $others = $this->verifiedCustomers($phone)->whereKeyNot($user->id)->limit(2)->get();

            if ($others->isEmpty()) {
                $user->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();

                return ['user' => $user, 'linked' => false];
            }

            $target = $others->first();
            if ($others->count() > 1 || !$this->canMergeInto($user, $target)) {
                throw new AccountLinkConflictException();
            }

            DB::transaction(function () use ($user, $target) {
                $telegram = [
                    'telegram_id' => $user->telegram_id,
                    'telegram_username' => $user->telegram_username,
                    'telegram_photo_url' => $user->telegram_photo_url,
                ];

                $user->forceFill(['telegram_id' => null])->save();
                $target->forceFill($telegram)->save();
                // The Mini App's next call gets a 401 and signs in again with initData: into $target now
                $this->tokens->revokeAllForUser($user);

                $this->audit->record('account.linked', $target, [], [], [
                    'merged_user_id' => $user->id,
                    'via' => 'phone_verification',
                ], ['actor' => $user, 'store_id' => null, 'branch_id' => null]);
            });

            return ['user' => $target->refresh(), 'linked' => true];
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function verifiedCustomers(string $phone): \Illuminate\Database\Eloquent\Builder
    {
        // Proven by an SMS code only: a phone vouched for a staff account that later became a customer one is no proof
        return User::where('role', UserRole::CUSTOMER->value)
            ->where('phone', $phone)
            ->whereNotNull('phone_verified_at')
            ->where('phone_verified_via', User::PHONE_VIA_SMS)
            ->orderBy('id');
    }

    /**
     * $from may be folded into $into only when nothing is lost: $from is a Telegram account with no orders, addresses,
     * keshbek, complaints, ratings or referrals, and $into has no Telegram account of its own yet.
     */
    private function canMergeInto(User $from, User $into): bool
    {
        if (blank($from->telegram_id) || (filled($into->telegram_id) && !str_starts_with((string) $into->telegram_id, 'temp_'))) {
            return false;
        }

        return !Order::withTrashed()->where('user_id', $from->id)->exists()
            && !$from->addresses()->exists()
            && (float) ($from->getAttribute('balance') ?? 0) === 0.0
            && !DB::table('complaints')->where('user_id', $from->id)->exists()
            && !DB::table('store_ratings')->where('user_id', $from->id)->exists()
            && !User::where('referred_by', $from->id)->exists();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(string $phone, callable $callback): mixed
    {
        return Cache::lock('phone-link:' . sha1($phone), 10)->block(5, $callback);
    }
}
