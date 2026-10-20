<?php

namespace App\Services\Auth;

use App\Models\PhoneVerification;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Support\Facades\Hash;

/**
 * SMS one-time codes: proving a phone for a signed-in user (POST /phone/*) and signing in by phone (POST /auth/phone/*).
 * Codes are bcrypt-hashed, live 5 minutes and allow auth.phone_verification.max_attempts guesses each.
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

        // Deactivate any previous verification codes for this phone
        PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
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
     * Check a code and spend it. Returns null on success, else the Uzbek reason.
     */
    public function verify(string $phone, string $code): ?string
    {
        $verification = PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$verification) {
            return 'Tasdiqlash kodi muddati tugagan yoki noto\'g\'ri.';
        }

        // Atomic check-and-increment so parallel requests cannot exceed the per-code attempt limit
        $attemptClaimed = PhoneVerification::whereKey($verification->id)
            ->where('attempts', '<', (int) config('auth.phone_verification.max_attempts', 5))
            ->increment('attempts');

        if (!$attemptClaimed) {
            return 'Urinishlar soni tugadi. Iltimos, kodni qaytadan so\'rang.';
        }

        if (!Hash::check($code, $verification->code_hash)) {
            return 'Tasdiqlash kodi noto\'g\'ri.';
        }

        // Spent atomically: a code verified by two parallel requests counts once
        $spent = PhoneVerification::whereKey($verification->id)->whereNull('verified_at')->update(['verified_at' => now()]);

        return $spent ? null : 'Tasdiqlash kodi muddati tugagan yoki noto\'g\'ri.';
    }
}
