<?php

namespace App\Services\Auth;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Staff PINs and PIN sign-in on a paired terminal.
 *
 * - An owner (or a manager, for staff of their branch ranked below them) sets a 4-6 digit PIN; only a bcrypt hash is
 *   kept and trivially guessable PINs (0000, 1234, 2580, ...) are refused.
 * - On a terminal, POST /devices/{device}/staff-session with the staff id (or staff code) and PIN returns a short-lived
 *   staff session token bound to that terminal. A new session on the terminal ends the previous one; revoking the
 *   terminal or changing the PIN ends them all.
 * - Failures are counted per terminal and person; after AUTH_PIN_MAX_ATTEMPTS the pair is locked with a doubling
 *   delay. Wrong PIN, unknown person, another store's person and a person without a PIN all get the same answer and
 *   the same bcrypt work, so the terminal cannot be used to find out who exists.
 */
class StaffPinService
{
    // Keypad patterns and other PINs people pick first
    private const WEAK = ['2580', '0852', '1379', '1397', '2468', '1357', '1122', '1212', '6969', '1004', '2000',
        '2020', '2021', '2022', '2023', '2024', '2025', '2026', '7777', '121212', '112233', '123123', '159753',
        '147258', '258369', '654321', '696969', '102030', '111222'];

    // A hash with the configured cost, checked when there is no real one so every attempt costs the same
    private static ?string $dummyHash = null;

    public function __construct(
        protected PermissionService $permissions,
        protected TokenService $tokens,
        protected AuditLogger $audit,
    ) {}

    /**
     * Why a PIN is not acceptable (Uzbek message), or null when it is.
     */
    public static function weakness(string $pin): ?string
    {
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            return "PIN 4 tadan 6 tagacha raqamdan iborat bo'lishi kerak.";
        }

        $digits = array_map('intval', str_split($pin));
        $steps = array_unique(array_map(fn (int $i) => ($digits[$i + 1] - $digits[$i] + 10) % 10, range(0, count($digits) - 2)));

        // 0000, 1234, 9876, 7890 (constant step of 0, +1 or -1, wrapping)
        if (count($steps) === 1 && in_array($steps[0], [0, 1, 9], true)) {
            return "PIN juda oddiy (masalan 0000 yoki 1234). Boshqa PIN tanlang.";
        }

        // 1212, 123123, 4545: a short block repeated
        foreach ([1, 2, 3] as $size) {
            if (strlen($pin) % $size === 0 && strlen($pin) > $size && str_repeat(substr($pin, 0, $size), intdiv(strlen($pin), $size)) === $pin) {
                return "PIN juda oddiy (takrorlanuvchi raqamlar). Boshqa PIN tanlang.";
            }
        }

        if (in_array($pin, self::WEAK, true)) {
            return "PIN juda ko'p ishlatiladigan kodlardan. Boshqa PIN tanlang.";
        }

        return null;
    }

    /**
     * Whether $actor may set or reset $target's PIN: same store, a terminal role, ranked below the actor (or the
     * actor themselves; an owner manages everyone in the store), inside the actor's branch when they are tied to one.
     */
    public function canManagePin(User $actor, User $target): bool
    {
        if (!in_array($target->role, UserRole::terminalRoles(), true)) {
            return false;
        }

        if ($actor->role === UserRole::SUPER_ADMIN) {
            return $target->store_id !== null;
        }

        if (!$this->permissions->allows($actor, Permission::STAFF_PIN, $target)) {
            return false;
        }

        return $actor->is($target)
            || $actor->role === UserRole::OWNER
            || $target->role->rank() < $actor->role->rank();
    }

    /**
     * @throws \InvalidArgumentException with an Uzbek message for a weak PIN
     */
    public function setPin(User $target, string $pin, ?User $actor): void
    {
        if ($reason = self::weakness($pin)) {
            throw new \InvalidArgumentException($reason);
        }

        DB::transaction(function () use ($target, $pin, $actor) {
            $hadPin = $target->hasPin();
            $target->forceFill([
                'pin_hash' => Hash::make($pin),
                'pin_set_at' => now(),
                'staff_code' => $target->staff_code ?? $this->newStaffCode((int) $target->store_id),
            ])->saveQuietly();

            $this->endSessionsOf($target);

            $this->audit->record($hadPin ? 'staff.pin_changed' : 'staff.pin_set', $target, [], [], [
                'staff_name' => $target->name,
                'role' => $target->role->value,
            ], ['actor' => $actor, 'store_id' => $target->store_id, 'branch_id' => $target->branch_id]);
        });
    }

    public function resetPin(User $target, ?User $actor): void
    {
        DB::transaction(function () use ($target, $actor) {
            $target->forceFill(['pin_hash' => null, 'pin_set_at' => null])->saveQuietly();
            $this->endSessionsOf($target);

            $this->audit->record('staff.pin_reset', $target, [], [], [
                'staff_name' => $target->name,
                'role' => $target->role->value,
            ], ['actor' => $actor, 'store_id' => $target->store_id, 'branch_id' => $target->branch_id]);
        });
    }

    /**
     * Staff who can sign in on this terminal: its store, its branch (or store-wide staff), a terminal role, a PIN.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function eligibleStaff(Device $device): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->where('store_id', $device->store_id)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::terminalRoles()))
            ->whereNotNull('pin_hash')
            ->when($device->branch_id, fn ($query, $branchId) => $query->where(
                fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id')
            ));
    }

    /**
     * @return array{token: string, expires_at: Carbon, user: User}
     *
     * @throws AuthTokenException
     */
    public function startSession(Device $device, ?int $userId, ?string $staffCode, string $pin): array
    {
        $candidate = $device->store_id === null ? null : $this->eligibleStaff($device)
            ->when($userId !== null, fn ($query) => $query->whereKey($userId))
            ->when($userId === null, fn ($query) => $query->where('staff_code', (string) $staffCode))
            ->first();

        // Keyed on the person when they exist, else on what was typed, so both cases lock out alike
        $lockKey = 'pin-lock:' . $device->id . ':' . ($candidate ? 'u' . $candidate->id : 'x' . sha1(($userId ?? '') . '|' . ($staffCode ?? '')));
        $state = Cache::get($lockKey, ['failures' => 0, 'locked_until' => 0]);

        if ($state['locked_until'] > now()->getTimestamp()) {
            throw $this->lockedOut($state['locked_until'] - now()->getTimestamp());
        }

        // Always one bcrypt check, whether or not the person exists
        $valid = Hash::check($pin, $candidate->pin_hash ?? (self::$dummyHash ??= Hash::make(\Illuminate\Support\Str::random(32))))
            && $candidate !== null;

        if (!$valid) {
            $state = $this->recordFailure($lockKey, $state);
            if ($state['locked_until'] > now()->getTimestamp()) {
                throw $this->lockedOut($state['locked_until'] - now()->getTimestamp());
            }

            throw new AuthTokenException("Xodim yoki PIN noto'g'ri.", 'pin_invalid', 422);
        }

        Cache::forget($lockKey);

        return DB::transaction(function () use ($device, $candidate) {
            // Switching staff: whoever was signed in on this terminal is signed out
            PersonalAccessToken::where('device_id', $device->id)->where('name', TokenService::STAFF_SESSION_NAME)->delete();

            $expiresAt = now()->addMinutes(max(1, (int) config('auth.tokens.staff_session_ttl', 720)));
            $token = $this->tokens->createDeviceToken($candidate, $device, TokenService::STAFF_SESSION_NAME, ['*'], $expiresAt);

            $this->audit->record('staff.session_started', $candidate, [], [], [
                'staff_name' => $candidate->name,
                'role' => $candidate->role->value,
            ], ['actor' => $candidate, 'device' => $device, 'store_id' => $device->store_id, 'branch_id' => $device->branch_id]);

            return ['token' => $token->plainTextToken, 'expires_at' => $expiresAt, 'user' => $candidate];
        });
    }

    public function endSessionsOn(Device $device): int
    {
        return PersonalAccessToken::where('device_id', $device->id)->where('name', TokenService::STAFF_SESSION_NAME)->delete();
    }

    private function endSessionsOf(User $user): void
    {
        $user->tokens()->where('name', TokenService::STAFF_SESSION_NAME)->delete();
    }

    /**
     * @param  array{failures: int, locked_until: int}  $state
     * @return array{failures: int, locked_until: int}
     */
    private function recordFailure(string $key, array $state): array
    {
        $max = max(1, (int) config('auth.pin.max_attempts', 5));
        $failures = $state['failures'] + 1;
        $lockedUntil = 0;

        if ($failures >= $max) {
            // 1st lockout: the base delay, then doubling with every further failure, capped
            $seconds = min(
                (int) config('auth.pin.max_lockout_seconds', 3600),
                (int) config('auth.pin.lockout_seconds', 60) * (2 ** min(10, $failures - $max)),
            );
            $lockedUntil = now()->getTimestamp() + max(1, $seconds);
        }

        $state = ['failures' => $failures, 'locked_until' => $lockedUntil];
        Cache::put($key, $state, now()->addDay());

        return $state;
    }

    private function lockedOut(int $seconds): AuthTokenException
    {
        $wait = $seconds >= 60 ? (int) ceil($seconds / 60) . ' daqiqadan' : $seconds . ' soniyadan';

        return new AuthTokenException("Juda ko'p noto'g'ri urinish. Iltimos, {$wait} so'ng qayta urinib ko'ring.", 'pin_locked', 429, max(1, $seconds));
    }

    private function newStaffCode(int $storeId): string
    {
        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('store_id', $storeId)->where('staff_code', $code)->exists());

        return $code;
    }
}
