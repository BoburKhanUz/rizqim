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
        $code = (string) rand(100000, 999999);

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
            // Return code in debug mode so front-end developers can bypass real SMS easily
            'code' => config('app.debug') ? $code : null,
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
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Tasdiqlash kodi muddati tugagan yoki noto\'g\'ri.',
            ], 422);
        }

        if ($verification->attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Urinishlar soni tugadi. Iltimos, kodni qaytadan so\'rang.',
            ], 422);
        }

        $verification->increment('attempts');

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
