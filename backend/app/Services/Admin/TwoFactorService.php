<?php

namespace App\Services\Admin;

use App\Models\User;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;

/**
 * P4-11: TOTP (RFC 6238, Google Authenticator) for the RIZQIM team. One secret per account (users.
 * app_authentication_secret, encrypted at rest), shared by the admin API and the old Filament panels
 * (App\Filament\Auth\PlatformAppAuthentication), so both ask for the same code and the same replay rule holds:
 * a code is accepted once, and never one from the same or an earlier 30 s step than the last accepted code.
 *
 * Recovery codes are kept as bcrypt hashes (the format Filament reads); a used one is burnt.
 *
 * Review F2: a member without 2FA may set it up only inside a set-up window (users.two_factor_setup_allowed_until,
 * 24 hours), opened by accepting an invite or by a 2FA reset and closed when 2FA is turned on. Outside it the right
 * password alone enrolls nothing (admin API: `setup_not_allowed`; the Filament set-up is refused the same way).
 */
class TwoFactorService
{
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** users column: until when the member may set 2FA up (null: not allowed) */
    public const SETUP_WINDOW = 'two_factor_setup_allowed_until';

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        // 32 base32 characters = 160 bits, the RFC 4226 recommendation
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUri(User $user, #[SensitiveParameter] string $secret): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('admin.two_factor.issuer', 'RIZQIM'), (string) $user->email, $secret);
    }

    /**
     * The otpauth URI as an inline SVG QR code (drawn on the server: the secret never goes to a third-party service).
     */
    public function qrSvg(#[SensitiveParameter] string $otpauthUri): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($otpauthUri);
    }

    /**
     * The time step a code matches for a secret not saved yet (2FA set-up), or null.
     */
    public function verifySecret(#[SensitiveParameter] string $secret, #[SensitiveParameter] string $code): ?int
    {
        return $this->match($secret, $code, null);
    }

    /**
     * Check a code against the account's secret, refusing a replay. Concurrent requests with the same code: only one
     * wins (the user row is locked while the last accepted step is compared and moved).
     */
    public function verifyForUser(User $user, #[SensitiveParameter] string $code): bool
    {
        if (!self::looksLikeCode($code)) {
            return false;
        }

        return DB::transaction(function () use ($user, $code) {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            $secret = $locked instanceof User ? $locked->getAppAuthenticationSecret() : null;
            if (!$locked instanceof User || blank($secret)) {
                return false;
            }

            $step = $this->match($secret, $code, $locked->two_factor_last_timestep);
            if ($step === null) {
                return false;
            }

            $locked->forceFill(['two_factor_last_timestep' => $step])->saveQuietly();
            $user->setAttribute('two_factor_last_timestep', $step);
            $user->syncOriginalAttribute('two_factor_last_timestep');

            return true;
        });
    }

    /**
     * Turn 2FA on with a confirmed secret; the confirming code's step counts as used.
     */
    public function enable(User $user, #[SensitiveParameter] string $secret, int $confirmedStep): void
    {
        $user->saveAppAuthenticationSecret($secret);
        $user->forceFill(['two_factor_last_timestep' => $confirmedStep, self::SETUP_WINDOW => null])->save();
    }

    /**
     * Turn 2FA off (a lost phone: `rizqim:admin:reset-2fa`, POST /admin/team/{id}/reset-2fa). The member sets it up
     * again at a sign-in within the set-up window this opens.
     */
    public function disable(User $user): void
    {
        $user->app_authentication_recovery_codes = null;
        $user->saveAppAuthenticationSecret(null);
        $this->openSetupWindow($user);
    }

    /**
     * Whether the member may set 2FA up now (after the right password): inside the window an invite or a reset opened.
     */
    public function setupAllowed(User $user): bool
    {
        $until = self::setupAllowedUntil($user);

        return $until !== null && $until->isFuture();
    }

    public static function setupAllowedUntil(User $user): ?Carbon
    {
        $value = $user->getAttribute(self::SETUP_WINDOW);
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
    }

    public function openSetupWindow(User $user): void
    {
        $hours = max(1, (int) config('admin.two_factor.setup_window_hours', 24));
        $user->forceFill([self::SETUP_WINDOW => now()->addHours($hours)])->save();
    }

    public function closeSetupWindow(User $user): void
    {
        if ($user->getAttribute(self::SETUP_WINDOW) !== null) {
            $user->forceFill([self::SETUP_WINDOW => null])->save();
        }
    }

    /**
     * New recovery codes replace the old ones. Returns the plain codes: shown once, only their hashes are kept.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();
        $this->storeRecoveryCodes($user, $codes);

        return $codes;
    }

    /**
     * @return list<string> XXXXX-XXXXX, 50 random bits each
     */
    public function generateRecoveryCodes(?int $count = null): array
    {
        $count ??= (int) config('admin.two_factor.recovery_codes', 10);
        $codes = [];
        $max = strlen(self::RECOVERY_ALPHABET) - 1;

        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 10; $j++) {
                $code .= self::RECOVERY_ALPHABET[random_int(0, $max)];
            }
            $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     */
    public function storeRecoveryCodes(User $user, #[SensitiveParameter] array $codes): void
    {
        $user->saveAppAuthenticationRecoveryCodes(array_map(fn (string $code) => Hash::make(self::normalizeRecoveryCode($code)), $codes));
    }

    /**
     * Spend a recovery code: true when it was one of the account's unused codes (it is burnt).
     */
    public function useRecoveryCode(User $user, #[SensitiveParameter] string $code): bool
    {
        $code = self::normalizeRecoveryCode($code);
        if (strlen($code) !== 11) {
            return false;
        }

        return DB::transaction(function () use ($user, $code) {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            if (!$locked instanceof User) {
                return false;
            }

            $hashes = $locked->getAppAuthenticationRecoveryCodes() ?? [];
            foreach ($hashes as $index => $hash) {
                if (Hash::check($code, $hash)) {
                    unset($hashes[$index]);
                    $locked->saveAppAuthenticationRecoveryCodes(array_values($hashes));
                    $user->setRawAttributes($locked->getAttributes(), true);

                    return true;
                }
            }

            return false;
        });
    }

    public function recoveryCodesLeft(User $user): int
    {
        return count($user->getAppAuthenticationRecoveryCodes() ?? []);
    }

    public static function looksLikeCode(?string $code): bool
    {
        return is_string($code) && preg_match('/^\d{6}$/', $code) === 1;
    }

    /**
     * Upper case, XXXXX-XXXXX, whatever spacing or dashes the person typed.
     */
    public static function normalizeRecoveryCode(string $code): string
    {
        $plain = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        return strlen($plain) === 10 ? substr($plain, 0, 5) . '-' . substr($plain, 5) : $plain;
    }

    /**
     * The 30 s step the code belongs to (within the allowed drift), newer than $lastStep; null when it matches none.
     */
    private function match(#[SensitiveParameter] string $secret, #[SensitiveParameter] string $code, ?int $lastStep): ?int
    {
        if (!self::looksLikeCode($code)) {
            return null;
        }

        $window = max(0, (int) config('admin.two_factor.window', 1));
        $now = $this->currentStep();

        try {
            $step = $this->google2fa->verifyKeyNewer($secret, $code, $lastStep ?? ($now - $window - 1), $window, $now);
        } catch (\Throwable) {
            return null; // a malformed secret
        }

        return is_int($step) ? $step : null;
    }

    /**
     * The current 30 s step, from the application clock (so tests can travel in time).
     */
    public function currentStep(): int
    {
        return intdiv(now()->getTimestamp(), 30);
    }

    /**
     * The code of a secret at the current step (tests and the local seeder only).
     */
    public function currentCode(#[SensitiveParameter] string $secret, int $offsetSteps = 0): string
    {
        return $this->google2fa->oathTotp($secret, $this->currentStep() + $offsetSteps);
    }
}
