<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\Admin\AdminLoginLockout;
use App\Services\Admin\TwoFactorService;
use App\Services\Audit\AuditLogger;
use Closure;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * P4-11: the old Filament panels' 2FA, backed by the admin API's TwoFactorService: the same secret, the same
 * recovery codes, the same replay rule (a code is accepted once, nothing older than the last accepted one) and the
 * same ±30 s drift, so neither /superadmin nor /admin is a way around the Boshqaruv markazi's 2FA.
 *
 * Review F3: and the same brute-force guard and audit: a team member's bad codes count in AdminLoginLockout (CODE,
 * per account: the API and the panels together), a locked account is refused here too, and every check is recorded
 * (admin.auth.2fa_failed / 2fa_succeeded, channel `filament`). Review F2: a team member without 2FA sets it up here
 * only inside the set-up window an invite or a 2FA reset opened (TwoFactorService::setupAllowed), and the panels'
 * password step shares the admin API's password lockout (guardPasswordSignIn, from App\Filament\Pages\Auth\CustomLogin).
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

    /**
     * The Filament sign-in's password step (CustomLogin::authenticate), under the admin API's password lockout: 5 bad
     * passwords per account + address, 15 per account from every address, lock the sign-in for 15 minutes, on both
     * channels at once. $attempt is the panel's own sign-in; a refused password is the ValidationException it throws
     * for `data.email`.
     *
     * @template T
     *
     * @param  Closure(): T  $attempt
     * @return T
     *
     * @throws ValidationException
     */
    public static function guardPasswordSignIn(?string $login, Closure $attempt): mixed
    {
        $lockout = app(AdminLoginLockout::class);
        $account = AdminLoginLockout::accountForEmail((string) $login);
        $ip = request()->ip();

        $seconds = $lockout->lockedFor(AdminLoginLockout::PASSWORD, $account, $ip);
        if (filled($login) && $seconds > 0) {
            throw ValidationException::withMessages([
                'data.email' => "Juda ko'p noto'g'ri urinish. " . (int) ceil($seconds / 60) . " daqiqadan so'ng qayta urinib ko'ring.",
            ]);
        }

        try {
            $result = $attempt();
        } catch (ValidationException $e) {
            if (filled($login) && array_key_exists('data.email', $e->errors())) {
                $lockout->fail(AdminLoginLockout::PASSWORD, $account, $ip);
            }

            throw $e;
        }

        if ($result !== null && filled($login)) {
            $lockout->clear(AdminLoginLockout::PASSWORD, $account, $ip);
        }

        return $result;
    }

    public function getChallengeFormComponents(Authenticatable $user): array
    {
        $this->challenged = $user instanceof User ? $user : null;

        return parent::getChallengeFormComponents($user);
    }

    /**
     * A team member without 2FA outside the set-up window is told how to get one, instead of the set-up action.
     */
    public function getManagementSchemaComponents(): array
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && $this->setupRefused($user)) {
            return [
                Text::make("2FA o'rnatish muddati tugagan. Boshqa superadmindan Tizim → Jamoa'da 2FA tiklashni yoki serverda "
                    . "`php artisan rizqim:admin:invite {$user->email}` bilan yangi taklif havolasini so'rang.")
                    ->color('danger'),
            ];
        }

        return parent::getManagementSchemaComponents();
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

    /**
     * Turning 2FA on (the set-up action) is refused outside the set-up window; turning it off (the disable action, after
     * the password and a code) opens the window like a reset, so the member is not shut out of the required set-up.
     */
    public function saveSecret(HasAppAuthentication $user, #[SensitiveParameter] ?string $secret): void
    {
        if (!$user instanceof User || !$user->isPlatformStaff()) {
            parent::saveSecret($user, $secret);

            return;
        }

        $twoFactor = app(TwoFactorService::class);

        if ($secret === null) {
            $twoFactor->disable($user);
            $this->audit('admin.auth.2fa_disabled', $user, ['context' => 'self']);

            return;
        }

        if ($this->setupRefused($user)) {
            $this->audit('admin.auth.2fa_setup_refused', $user, ['failure' => 'setup_not_allowed']);

            throw new AuthorizationException("2FA o'rnatish muddati tugagan.");
        }

        parent::saveSecret($user, $secret);
        $twoFactor->closeSetupWindow($user);
        $this->audit('admin.auth.2fa_enabled', $user);
    }

    public function verifyCode(#[SensitiveParameter] string $code, #[SensitiveParameter] ?string $secret = null, bool $shouldPreventCodeReuse = false): bool
    {
        $signedIn = Filament::auth()->user();
        $user = $signedIn instanceof User ? $signedIn : $this->challenged;
        $twoFactor = app(TwoFactorService::class);
        $context = $signedIn instanceof User ? 'confirm' : 'sign_in';

        // The account's own secret (sign-in, confirming an action): the replay-protected check
        if ($user !== null && $user->hasTwoFactorEnabled() && ($secret === null || hash_equals((string) $user->getAppAuthenticationSecret(), $secret))) {
            return $this->guarded($user, $context, 'invalid_code', fn () => $twoFactor->verifyForUser($user, $code));
        }

        if ($secret === null) {
            return false;
        }

        // A secret being set up (not saved yet): a team member only inside the set-up window
        if ($user !== null && $user->isPlatformStaff()) {
            if ($this->setupRefused($user)) {
                $this->audit('admin.auth.2fa_setup_refused', $user, ['failure' => 'setup_not_allowed']);
                $this->notify("2FA o'rnatish muddati tugagan. Superadmindan 2FA tiklashni yoki yangi taklif havolasini so'rang.");

                return false;
            }

            return $this->guarded($user, 'setup', 'invalid_code', fn () => $twoFactor->verifySecret($secret, $code) !== null);
        }

        return $twoFactor->verifySecret($secret, $code) !== null;
    }

    public function verifyRecoveryCode(#[SensitiveParameter] string $recoveryCode, ?HasAppAuthenticationRecovery $user = null): bool
    {
        $user ??= Filament::auth()->user() ?? $this->challenged;
        if (!$user instanceof User) {
            return false;
        }

        $context = Filament::auth()->user() instanceof User ? 'confirm' : 'sign_in';

        return $this->guarded($user, $context, 'invalid_recovery_code', fn () => app(TwoFactorService::class)->useRecoveryCode($user, $recoveryCode), 'recovery_code');
    }

    /**
     * One code check of a team member under the shared lockout, audited either way. Accounts outside the team (store
     * owners, managers) keep Filament's own limits only.
     *
     * @param  Closure(): bool  $check
     */
    private function guarded(User $user, string $context, string $failure, Closure $check, string $method = 'totp'): bool
    {
        if (!$user->isPlatformStaff()) {
            return $check();
        }

        $lockout = app(AdminLoginLockout::class);
        $account = AdminLoginLockout::accountForUser((int) $user->id);
        $ip = request()->ip();

        $seconds = $lockout->lockedFor(AdminLoginLockout::CODE, $account, $ip);
        if ($seconds > 0) {
            $this->audit('admin.auth.locked_out', $user, ['kind' => AdminLoginLockout::CODE, 'retry_after' => $seconds, 'context' => $context]);
            $this->notify("Juda ko'p noto'g'ri urinish. " . (int) ceil($seconds / 60) . " daqiqadan so'ng qayta urinib ko'ring.");

            return false;
        }

        if (!$check()) {
            $locked = $lockout->fail(AdminLoginLockout::CODE, $account, $ip);
            $this->audit('admin.auth.2fa_failed', $user, ['failure' => $failure, 'locked' => $locked, 'context' => $context, 'method' => $method]);

            return false;
        }

        $lockout->clear(AdminLoginLockout::CODE, $account, $ip);
        $this->audit('admin.auth.2fa_succeeded', $user, ['context' => $context, 'method' => $method]);

        return true;
    }

    private function setupRefused(User $user): bool
    {
        return $user->isPlatformStaff() && !$user->hasTwoFactorEnabled() && !app(TwoFactorService::class)->setupAllowed($user);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function audit(string $action, User $user, array $meta = []): void
    {
        app(AuditLogger::class)->record($action, $user, [], [], [
            'channel' => 'filament',
            'panel' => Filament::getCurrentPanel()?->getId(),
            ...$meta,
        ], [
            'actor' => $user,
            'device' => null,
            'store_id' => null,
            'branch_id' => null,
            'ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }

    private function notify(string $message): void
    {
        Notification::make()->title($message)->danger()->send();
    }
}
