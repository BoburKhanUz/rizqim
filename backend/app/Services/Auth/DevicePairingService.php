<?php

namespace App\Services\Auth;

use App\Enums\DeviceType;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pairing a POS/KDS terminal: an owner or manager makes a one-time 8-character code (valid AUTH_PAIRING_CODE_TTL
 * minutes), the terminal sends it to POST /devices/pair and gets its own long-lived token (ability "terminal"). Only an
 * HMAC of the code (keyed with APP_KEY) is stored; a wrong, used or expired code all get the same answer.
 *
 * Codes use Crockford's base32 alphabet (digits and capitals without I, L, O, U: nothing to confuse when typing) and
 * are shown grouped as ABCD-EFGH. A typed code may have the dash or not, any case, and O/I/L for 0/1/1.
 */
class DevicePairingService
{
    public const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    public const CODE_LENGTH = 8;

    public function __construct(
        protected TokenService $tokens,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array{code: string, expires_at: Carbon, pairing: DevicePairingCode}
     *
     * @throws AuthTokenException when the store already has too many live codes
     */
    public function createCode(Store $store, ?Branch $branch, DeviceType $type, ?string $name, User $creator, ?Device $followsTill = null): array
    {
        if (!$type->isTerminal()) {
            throw new \InvalidArgumentException('Only POS/KDS terminals are paired with a code.');
        }

        return DB::transaction(function () use ($store, $branch, $type, $name, $creator, $followsTill) {
            // Serialises code creation per store, so the live-code cap and uniqueness hold under parallel requests
            Store::whereKey($store->id)->lockForUpdate()->first();

            $live = DevicePairingCode::where('store_id', $store->id)->usable()->count();
            if ($live >= (int) config('auth.pairing.max_active', 5)) {
                throw new AuthTokenException(
                    "Faol ulash kodlari juda ko'p. Avvalgilari ishlatilishini yoki muddati o'tishini kuting.",
                    'pairing_limit',
                    429,
                );
            }

            // A code must not collide with another store's live code: the terminal sends nothing else
            do {
                $code = self::generateCode();
            } while (DevicePairingCode::where('code_hash', self::hash($code))->usable()->exists());

            $expiresAt = now()->addMinutes(max(1, (int) config('auth.pairing.ttl', 10)));
            $pairing = DevicePairingCode::create([
                'store_id' => $store->id,
                'branch_id' => $branch?->id,
                'type' => $type->value,
                'name' => $name,
                'code_hash' => self::hash($code),
                'created_by' => $creator->id,
                'expires_at' => $expiresAt,
                'display_for_device_id' => $type === DeviceType::DISPLAY ? $followsTill?->id : null,
            ]);

            $this->audit->record('device.pairing_code_created', $pairing, [], [], [
                'type' => $type->value,
                'name' => $name,
                'expires_at' => $expiresAt,
                'follows_till' => $followsTill?->uuid,
            ], ['actor' => $creator, 'store_id' => $store->id, 'branch_id' => $branch?->id]);

            return ['code' => self::format($code), 'expires_at' => $expiresAt, 'pairing' => $pairing];
        });
    }

    /**
     * Spend a code (as typed: ABCD-EFGH, abcdefgh, ...) and create the terminal with its token.
     *
     * @param  array{name?: ?string, platform?: ?string, app_version?: ?string}  $details
     * @return array{device: Device, token: string}
     *
     * @throws AuthTokenException
     */
    public function pair(string $code, array $details): array
    {
        return DB::transaction(function () use ($code, $details) {
            $code = self::normalize($code);
            $pairing = $code !== null
                ? DevicePairingCode::where('code_hash', self::hash($code))->usable()->lockForUpdate()->first()
                : null;

            if (!$pairing || !hash_equals($pairing->code_hash, self::hash($code))) {
                throw new AuthTokenException("Kod noto'g'ri yoki muddati o'tgan.", 'pairing_invalid', 422);
            }

            $device = Device::create([
                'store_id' => $pairing->store_id,
                'branch_id' => $pairing->branch_id,
                'type' => $pairing->type->value,
                'name' => $details['name'] ?? $pairing->name ?? $pairing->type->label(),
                'platform' => $details['platform'] ?? null,
                'app_version' => $details['app_version'] ?? null,
                'paired_by' => $pairing->created_by,
                'last_seen_at' => now(),
                'display_for_device_id' => $pairing->display_for_device_id,
            ]);

            $pairing->forceFill(['used_at' => now(), 'device_id' => $device->id])->save();

            $days = (int) config('auth.tokens.device_ttl_days', 365);
            $token = $this->tokens->createDeviceToken(
                $device,
                $device,
                TokenService::TERMINAL_TOKEN_NAME,
                [\App\Auth\DeviceTokenGuard::ABILITY],
                $days > 0 ? now()->addDays($days) : null,
            );

            $this->audit->record('device.paired', $device, [], [], [
                'type' => $device->type->value,
                'name' => $device->name,
                'platform' => $device->platform,
            ], ['actor' => $pairing->creator, 'device' => $device]);

            return ['device' => $device, 'token' => $token->plainTextToken];
        });
    }

    /**
     * The HMAC kept in the database, of the code in its canonical form (so ABCD-EFGH and abcdefgh hash alike).
     */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code) ?? $code, (string) config('app.key'));
    }

    public static function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * A typed code in canonical form (8 capitals/digits, no dash), or null when it cannot be a pairing code.
     */
    public static function normalize(string $input): ?string
    {
        $code = strtr(strtoupper((string) preg_replace('/[\s\-]+/', '', $input)), ['O' => '0', 'I' => '1', 'L' => '1']);

        return strlen($code) === self::CODE_LENGTH && strspn($code, self::CODE_ALPHABET) === self::CODE_LENGTH ? $code : null;
    }

    /**
     * ABCDEFGH -> ABCD-EFGH, as shown to people.
     */
    public static function format(string $code): string
    {
        return implode('-', str_split($code, intdiv(self::CODE_LENGTH, 2)));
    }
}
