<?php

namespace App\Services\Auth;

use App\Models\PhoneVerification;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SMS one-time codes: proving a phone for a signed-in user (POST /phone/*) and signing in by phone (POST /auth/phone/*).
 * Codes are bcrypt-hashed, live 5 minutes and allow auth.phone_verification.max_attempts guesses each; a phone gets
 * auth.phone_verification.max_attempts_per_day guesses in all, however many codes are requested.
 *
 * Asking for a new code keeps the previous one usable until it expires (the last two live codes are accepted, each
 * with its own attempt count), so somebody re-requesting codes for a victim's phone cannot cancel the code the victim
 * is typing. A success spends both.
 */
class PhoneOtpService
{
    public function __construct(
        protected SmsServiceInterface $sms,
    ) {}

    /**
     * @return array{sent: bool, code: string}
     */
    public function send(string $phone): array
    {
        $code = (string) random_int(100000, 999999);

        // Older codes go, except the newest still-live one: it stays usable next to the new code until it expires
        $previous = $this->liveCodes($phone)->value('id');
        PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->when($previous, fn ($query, $id) => $query->whereKeyNot($id))
            ->delete();

        PhoneVerification::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        return ['sent' => $this->sms->send($phone, "FastFood tasdiqlash kodi: {$code}"), 'code' => $code];
    }

    /**
     * Local development aid only: requires APP_ENV=local AND SMS_EXPOSE_CODE_IN_RESPONSE=true.
     */
    public static function exposeCode(string $code): ?string
    {
        return app()->environment('local') && config('auth.phone_verification.expose_code_in_response') === true ? $code : null;
    }

    /**
     * Check a code against the phone's live codes (the last two) and spend them. Returns null on success, else the
     * Uzbek reason.
     */
    public function verify(string $phone, string $code): ?string
    {
        $live = $this->liveCodes($phone)->limit(2)->get();

        if ($live->isEmpty()) {
            return 'Tasdiqlash kodi muddati tugagan yoki noto\'g\'ri.';
        }

        // Counted before any check (atomic increment), so parallel requests cannot go past the daily cap either
        $today = RateLimiter::hit('otp-verify-day:' . $phone, 86400);
        if ($today > max(1, (int) config('auth.phone_verification.max_attempts_per_day', 30))) {
            return 'Bugun urinishlar soni tugadi. Iltimos, keyinroq qayta urinib ko\'ring.';
        }

        $maxAttempts = (int) config('auth.phone_verification.max_attempts', 5);
        $claimedAny = false;

        foreach ($live as $verification) {
            // Atomic check-and-increment so parallel requests cannot exceed the per-code attempt limit
            $attemptClaimed = PhoneVerification::whereKey($verification->id)
                ->where('attempts', '<', $maxAttempts)
                ->increment('attempts');

            if (!$attemptClaimed) {
                continue;
            }
            $claimedAny = true;

            if (!Hash::check($code, $verification->code_hash)) {
                continue;
            }

            // Spent atomically: a code verified by two parallel requests counts once
            $spent = PhoneVerification::whereKey($verification->id)->whereNull('verified_at')->update(['verified_at' => now()]);
            if (!$spent) {
                return 'Tasdiqlash kodi muddati tugagan yoki noto\'g\'ri.';
            }

            // The other live code is spent with it
            PhoneVerification::where('phone', $phone)->whereNull('verified_at')->delete();

            return null;
        }

        return $claimedAny
            ? 'Tasdiqlash kodi noto\'g\'ri.'
            : 'Urinishlar soni tugadi. Iltimos, kodni qaytadan so\'rang.';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<PhoneVerification>
     */
    private function liveCodes(string $phone): \Illuminate\Database\Eloquent\Builder
    {
        return PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id');
    }
}
