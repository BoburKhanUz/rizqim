<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;

/**
 * P4-11: brute-force guard of the admin sign-in. 5 bad passwords, or 5 bad 2FA / recovery / step-up codes, from one
 * address for one account lock that account at that address for 15 minutes (admin.login). Bad codes are also counted
 * for the account from every address together (a stolen password tried from many IPs). A success clears the
 * address's count. The keys hold hashes, never the email itself.
 */
class AdminLoginLockout
{
    public const PASSWORD = 'password';
    public const CODE = 'code';

    /**
     * Seconds until the lock ends, 0 when not locked.
     */
    public function lockedFor(string $kind, string $account, ?string $ip): int
    {
        $until = max(
            (int) Cache::get($this->key('lock', $kind, $account, $ip), 0),
            $kind === self::CODE ? (int) Cache::get($this->key('lock', $kind, $account, null), 0) : 0,
        );

        return max(0, $until - now()->getTimestamp());
    }

    /**
     * Count a failure; true when it locked the account (at the address, or for codes everywhere).
     */
    public function fail(string $kind, string $account, ?string $ip): bool
    {
        $decay = $this->lockSeconds();
        $locked = $this->count($kind, $account, $ip, (int) config('admin.login.max_attempts', 5), $decay);

        if ($kind === self::CODE) {
            $locked = $this->count($kind, $account, null, (int) config('admin.login.max_code_attempts_per_account', 15), $decay) || $locked;
        }

        return $locked;
    }

    public function clear(string $kind, string $account, ?string $ip): void
    {
        Cache::forget($this->key('fails', $kind, $account, $ip));
    }

    public static function accountForEmail(string $email): string
    {
        return 'email:' . hash('sha256', mb_strtolower(trim($email)));
    }

    public static function accountForUser(int $userId): string
    {
        return 'user:' . $userId;
    }

    private function count(string $kind, string $account, ?string $ip, int $max, int $decay): bool
    {
        $key = $this->key('fails', $kind, $account, $ip);
        Cache::add($key, 0, $decay);
        $fails = (int) Cache::increment($key);

        if ($fails < $max) {
            return false;
        }

        Cache::put($this->key('lock', $kind, $account, $ip), now()->getTimestamp() + $decay, $decay);
        Cache::forget($key);

        return true;
    }

    private function lockSeconds(): int
    {
        return max(60, (int) config('admin.login.lockout_minutes', 15) * 60);
    }

    private function key(string $type, string $kind, string $account, ?string $ip): string
    {
        return 'admin-login:' . $type . ':' . $kind . ':' . hash('sha256', $account . '|' . ($ip ?? '*'));
    }
}
