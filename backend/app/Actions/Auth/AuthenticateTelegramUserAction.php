<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Store;
use App\Enums\UserRole;
use App\Services\Auth\PlatformAccountSignInException;
use App\Services\Auth\ReferralService;

class AuthenticateTelegramUserAction
{
    public function __construct(
        protected ReferralService $referrals
    ) {}

    /**
     * $botStore: the store whose OWN bot token signed the initData. Its owner holds that token and can sign any
     * Telegram id, so such logins are limited to customers and that store's staff (null = refused).
     *
     * P4-25: a superadmin / platform account never signs in here. When the Telegram id also holds a customer or store
     * account, that one signs in; when only the platform account holds it, nothing is created and the call throws.
     *
     * @throws PlatformAccountSignInException
     * @throws \App\Services\Auth\AccountBlockedException an invite claimed by someone blocked in that store
     */
    public function execute(array $telegramUser, Store $store, ?string $startParam = null, ?Store $botStore = null): ?User
    {
        $telegramId = (string) $telegramUser['id'];

        // 1. Check if user is clicking an Invite Link (start_param = telegram_invite_token)
        if ($startParam) {
            $tokenWithoutPrefix = str_replace('invite_', '', $startParam);
            // Storeless invites would match the person's global account via "store_id IS NULL" below
            $invitedEmployee = User::where(function ($q) use ($startParam, $tokenWithoutPrefix) {
                $q->where('telegram_invite_token', $startParam)
                  ->orWhere('telegram_invite_token', $tokenWithoutPrefix);
            })
                ->whereNotNull('store_id')
                // Review W3 S#2: a blocked placeholder's invite is no invite
                ->whereNull('blocked_at')
                ->when($botStore, fn ($query) => $query->where('store_id', $botStore->id))
                ->first();

            if ($invitedEmployee) {
                // Someone blocked in this store gets neither a fresh account nor a new role through an invite; the
                // invite stays for the person it was meant for (403 account_blocked)
                $blockedHere = User::where('store_id', $invitedEmployee->store_id)
                    ->where('telegram_id', $telegramId)
                    ->where('id', '!=', $invitedEmployee->id)
                    ->whereNotNull('blocked_at')
                    ->exists();
                if ($blockedHere) {
                    throw new \App\Services\Auth\AccountBlockedException();
                }

                // Unlink this telegram_id from any other user in other stores globally first to prevent conflicts
                // (never through a store's own bot: it must not touch other stores' accounts)
                if (!$botStore) {
                    User::where('telegram_id', $telegramId)
                        ->where('id', '!=', $invitedEmployee->id)
                        ->where('store_id', '!=', $invitedEmployee->store_id)
                        ->update(['telegram_id' => null]);
                }

                // Check if this telegram_id is already bound to this store
                $alreadyExists = User::where('store_id', $invitedEmployee->store_id)
                    ->where('telegram_id', $telegramId)
                    ->where('id', '!=', $invitedEmployee->id)
                    ->first();

                if ($alreadyExists) {
                    // Update existing user with new role, branch, and name
                    $alreadyExists->update([
                        'role' => $invitedEmployee->role,
                        'branch_id' => $invitedEmployee->branch_id,
                        'name' => $invitedEmployee->name,
                        'telegram_username' => $telegramUser['username'] ?? null,
                        'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                    ]);

                    // Clean up/delete the dummy/temp employee row we just created
                    $invitedEmployee->delete();
                    return $alreadyExists;
                }

                // Link this telegram account to the invited employee
                $invitedEmployee->update([
                    'telegram_id' => $telegramId,
                    'telegram_username' => $telegramUser['username'] ?? null,
                    'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                    'telegram_invite_token' => null, // Invalidate token after single use
                ]);
                return $invitedEmployee;
            }
        }

        // 2. Fallback to normal login (never changes a stored role; staff roles come from invites or `user:assign-role`)
        // Fetch the user globally by telegram_id, prioritizing staff roles. P4-25: platform accounts are not candidates
        $user = User::where('telegram_id', $telegramId)
            ->whereNull('platform_role')
            ->whereNotIn('role', [UserRole::SUPER_ADMIN->value, UserRole::PLATFORM->value])
            ->when($botStore, fn ($query) => $query->where(function ($q) use ($botStore) {
                $q->where('store_id', $botStore->id)
                    ->orWhere(fn ($q) => $q->whereNull('store_id')->where('role', UserRole::CUSTOMER->value));
            }))
            ->orderByRaw("CASE
                WHEN role = 'owner' THEN 2
                WHEN role = 'manager' THEN 3
                WHEN role = 'courier' THEN 4
                WHEN role IN ('cashier', 'waiter', 'cook') THEN 4
                ELSE 5 END")
            ->first();

        if (!$user) {
            $holders = User::where('telegram_id', $telegramId)->get(['id', 'role', 'platform_role']);

            // Other stores' staff exist for this id (a store's own bot): refuse instead of creating a shadow account
            if ($botStore && $holders->contains(fn (User $holder) => !$holder->isPlatformAccount())) {
                return null;
            }

            // Only a superadmin / platform account holds this id: they use the control center, no token and no new account
            if ($holders->isNotEmpty() && $holders->every(fn (User $holder) => $holder->isPlatformAccount())) {
                throw new PlatformAccountSignInException();
            }
        }

        if ($user) {
            $user->update([
                'name' => ($telegramUser['first_name'] ?? 'Mijoz') . (isset($telegramUser['last_name']) ? ' ' . $telegramUser['last_name'] : ''),
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
            ]);
        } else {
            $user = new User([
                'telegram_id' => $telegramId,
                'store_id' => null, // Customers are global, they don't belong to a specific store
                'name' => ($telegramUser['first_name'] ?? 'Mijoz') . (isset($telegramUser['last_name']) ? ' ' . $telegramUser['last_name'] : ''),
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                'role' => UserRole::CUSTOMER,
            ]);
            // Referral is set once, at registration: start_param first, then the referrer parked by the bot's /start
            // (always consumed here, so a stale entry can never be applied later). Not mass assignable on purpose.
            $user->referred_by = $this->referrals->referrerIdFromParam($startParam, $telegramId);
            $user->save();
            $this->referrals->applyPendingReferral($user, $telegramId);
        }

        return $user;
    }
}
