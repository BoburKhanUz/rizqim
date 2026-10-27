<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\Admin\TwoFactorService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * P4-11: the old Filament panels' 2FA, backed by the admin API's TwoFactorService: the same secret, the same
 * recovery codes, the same replay rule (a code is accepted once, nothing older than the last accepted one) and the
 * same ±30 s drift, so neither /superadmin nor /admin is a way around the Boshqaruv markazi's 2FA.
 */
class PlatformAppAuthentication extends AppAuthentication
{
    /** The account being challenged at sign-in (not signed in yet, so Filament::auth()->user() is null). */
    private ?User $challenged = null;

    public static function make(): static
    {
        return parent::make()
            ->brandName((string) config('admin.two_factor.issuer', 'RIZQIM'))
            ->recoverable()
            ->recoveryCodeCount((int) config('admin.two_factor.recovery_codes', 10))
            ->codeWindow(max(0, (int) config('admin.two_factor.window', 1)));
    }

    public function getChallengeFormComponents(Authenticatable $user): array
    {
        $this->challenged = $user instanceof User ? $user : null;

        return parent::getChallengeFormComponents($user);
    }

    public function generateSecret(): string
    {
        return app(TwoFactorService::class)->generateSecret();
    }

    /**
     * @return array<string>
     */
    public function generateRecoveryCodes(): array
    {
        return app(TwoFactorService::class)->generateRecoveryCodes($this->getRecoveryCodeCount());
    }

    public function verifyCode(#[SensitiveParameter] string $code, #[SensitiveParameter] ?string $secret = null, bool $shouldPreventCodeReuse = false): bool
    {
        $signedIn = Filament::auth()->user();
        $user = $signedIn instanceof User ? $signedIn : $this->challenged;
        $twoFactor = app(TwoFactorService::class);

        // The account's own secret (sign-in, confirming an action): the replay-protected check
        if ($user !== null && $user->hasTwoFactorEnabled() && ($secret === null || hash_equals((string) $user->getAppAuthenticationSecret(), $secret))) {
            return $twoFactor->verifyForUser($user, $code);
        }

        // A secret being set up (not saved yet)
        return $secret !== null && $twoFactor->verifySecret($secret, $code) !== null;
    }

    public function verifyRecoveryCode(#[SensitiveParameter] string $recoveryCode, ?HasAppAuthenticationRecovery $user = null): bool
    {
        $user ??= Filament::auth()->user() ?? $this->challenged;

        return $user instanceof User && app(TwoFactorService::class)->useRecoveryCode($user, $recoveryCode);
    }
}
