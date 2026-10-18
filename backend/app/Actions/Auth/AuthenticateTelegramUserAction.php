<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Store;
use App\Enums\UserRole;

class AuthenticateTelegramUserAction
{
    public function execute(array $telegramUser, Store $store, ?string $startParam = null): User
    {
        $telegramId = (string) $telegramUser['id'];

        // 1. Check if user is clicking an Invite Link (start_param = telegram_invite_token)
        if ($startParam) {
            $tokenWithoutPrefix = str_replace('invite_', '', $startParam);
            $invitedEmployee = User::where(function ($q) use ($startParam, $tokenWithoutPrefix) {
                $q->where('telegram_invite_token', $startParam)
                  ->orWhere('telegram_invite_token', $tokenWithoutPrefix);
            })->first();

            if ($invitedEmployee) {
                // Unlink this telegram_id from any other user in other stores globally first to prevent conflicts
                User::where('telegram_id', $telegramId)
                    ->where('id', '!=', $invitedEmployee->id)
                    ->where('store_id', '!=', $invitedEmployee->store_id)
                    ->update(['telegram_id' => null]);

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

        // 2. Fallback to normal login
        // Auto-assign roles for developer mock/real test accounts
        $role = UserRole::CUSTOMER;
        if ($telegramId === '1328662483') {
            $role = UserRole::OWNER;
        } elseif ($telegramId === '999999999') {
            $role = UserRole::MANAGER;
        } elseif ($telegramId === '777777777') {
            $role = UserRole::COURIER;
        }

        // Fetch the user globally by telegram_id, prioritizing staff roles
        $user = User::where('telegram_id', $telegramId)
            ->orderByRaw("CASE 
                WHEN role = 'super_admin' THEN 1 
                WHEN role = 'owner' THEN 2 
                WHEN role = 'manager' THEN 3 
                WHEN role = 'courier' THEN 4 
                ELSE 5 END")
            ->first();

        // Parse referrer from startParam if it is a referral code
        $referredBy = null;
        if ($startParam && (
            str_starts_with(strtolower($startParam), 'ref_') || 
            str_starts_with(strtolower($startParam), 'referrer_') ||
            str_starts_with(strtolower($startParam), 'rizqim_')
        )) {
            $parts = explode('_', $startParam);
            $referrerId = end($parts);
            if (is_numeric($referrerId)) {
                if (User::where('id', $referrerId)->exists()) {
                    $referredBy = (int) $referrerId;
                }
            }
        }

        if ($user) {
            $user->update([
                'name' => ($telegramUser['first_name'] ?? 'Mijoz') . (isset($telegramUser['last_name']) ? ' ' . $telegramUser['last_name'] : ''),
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                // Only sync role if it's a mock dev account
                'role' => in_array($telegramId, ['1328662483', '999999999', '777777777']) ? $role : $user->role,
            ]);
        } else {
            $user = User::create([
                'telegram_id' => $telegramId,
                'store_id' => null, // Customers are global, they don't belong to a specific store
                'name' => ($telegramUser['first_name'] ?? 'Mijoz') . (isset($telegramUser['last_name']) ? ' ' . $telegramUser['last_name'] : ''),
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                'role' => $role,
                'referred_by' => $referredBy,
            ]);
        }

        return $user;
    }
}
