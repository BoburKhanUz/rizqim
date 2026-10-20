<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneVerification;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PhoneVerificationController extends Controller
{
    public function __construct(
        protected SmsServiceInterface $smsService
    ) {}

    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
        ]);

        $phone = $request->phone;
        $code = (string) random_int(100000, 999999);

        // Deactivate any previous verification codes for this phone
        PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->delete();

        // Create new verification record
        PhoneVerification::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        // Send SMS
        $message = "FastFood tasdiqlash kodi: {$code}";
        $sent = $this->smsService->send($phone, $message);

        if (!$sent) {
            return response()->json([
                'success' => false,
                'message' => 'SMS sending failed.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent.',
            // Local development aid only: requires APP_ENV=local AND SMS_EXPOSE_CODE_IN_RESPONSE=true
            'code' => app()->environment('local') && config('auth.phone_verification.expose_code_in_response') === true ? $code : null,
        ]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
            'code' => 'required|string|size:6',
        ]);

        $phone = $request->phone;
        $code = $request->code;

        $verification = PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Tasdiqlash kodi muddati tugagan yoki noto\'g\'ri.',
            ], 422);
        }

        // Atomic check-and-increment so parallel requests cannot exceed the per-code attempt limit
        $attemptClaimed = PhoneVerification::whereKey($verification->id)
            ->where('attempts', '<', (int) config('auth.phone_verification.max_attempts', 5))
            ->increment('attempts');

        if (!$attemptClaimed) {
            return response()->json([
                'success' => false,
                'message' => 'Urinishlar soni tugadi. Iltimos, kodni qaytadan so\'rang.',
            ], 422);
        }

        if (!Hash::check($code, $verification->code_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Tasdiqlash kodi noto\'g\'ri.',
            ], 422);
        }

        // Mark as verified
        $verification->update([
            'verified_at' => now()
        ]);

        // Link/update phone of current user
        $user = $request->user();
        if ($user) {
            $user->update([
                'phone' => $phone
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Telefon raqamingiz muvaffaqiyatli tasdiqlandi.',
            'user' => $user
        ]);
    }
}
