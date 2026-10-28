<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;

/**
 * P4-11: brute-force guard of the admin sign-in. 5 bad passwords, or 5 bad 2FA / recovery / step-up codes, from one
 * address for one account lock that account at that address for 15 minutes (admin.login). A success clears the
 * address's count. The keys hold hashes, never the email itself.
 *
 * Review F2: bad passwords and bad codes are also counted for the account from every address together (15 in the
 * window lock it everywhere: guessing from rotating addresses), and an IPv6 address counts by its /64 (one customer
 * line hands out a whole /64, so per-address counting there would be per-guess).
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
            (int) Cache::get($this->key('lock', $kind, $account, self::addressKey($ip)), 0),
            (int) Cache::get($this->key('lock', $kind, $account, null), 0),
        );

        return max(0, $until - now()->getTimestamp());
    }

    /**
     * Count a failure; true when it locked the account (at the address, or everywhere).
     */
    public function fail(string $kind, string $account, ?string $ip): bool
    {
        $decay = $this->lockSeconds();
        $locked = $this->count($kind, $account, self::addressKey($ip), (int) config('admin.login.max_attempts', 5), $decay);

        $perAccount = $kind === self::CODE
            ? (int) config('admin.login.max_code_attempts_per_account', 15)
            : (int) config('admin.login.max_password_attempts_per_account', 15);

        return $this->count($kind, $account, null, max(1, $perAccount), $decay) || $locked;
    }

    public function clear(string $kind, string $account, ?string $ip): void
    {
        Cache::forget($this->key('fails', $kind, $account, self::addressKey($ip)));
    }

    public static function accountForEmail(string $email): string
    {
        return 'email:' . hash('sha256', mb_strtolower(trim($email)));
    }

    public static function accountForUser(int $userId): string
    {
        return 'user:' . $userId;
    }

    /**
     * What an address is counted by: an IPv4 address (also one written as IPv4-mapped IPv6) as it is, an IPv6 address
     * by its /64 network. No or a malformed address is counted together with every other such request.
     */
    public static function addressKey(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'none';
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return 'invalid';
        }
        if (strlen($packed) === 4) {
            return (string) inet_ntop($packed);
        }

        // ::ffff:a.b.c.d is the IPv4 address a.b.c.d
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    private function count(string $kind, string $account, ?string $address, int $max, int $decay): bool
    {
        $key = $this->key('fails', $kind, $account, $address);
        Cache::add($key, 0, $decay);
        $fails = (int) Cache::increment($key);

        if ($fails < $max) {
            return false;
        }

        Cache::put($this->key('lock', $kind, $account, $address), now()->getTimestamp() + $decay, $decay);
        Cache::forget($key);

        return true;
    }

    private function lockSeconds(): int
    {
        return max(60, (int) config('admin.login.lockout_minutes', 15) * 60);
    }

    /**
     * @param  string|null  $address  addressKey(), or null for the account's count over every address (never an address
     *   key: those are never null)
     */
    private function key(string $type, string $kind, string $account, ?string $address): string
    {
        return 'admin-login:' . $type . ':' . $kind . ':' . hash('sha256', $account . '|' . ($address ?? '*'));
    }
}
