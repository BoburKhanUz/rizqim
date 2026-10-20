<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Services\Auth\AccountLinkConflictException;
use App\Services\Auth\AccountLinkingService;
use App\Services\Auth\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proving a phone number for the signed-in user (the Mini App's profile). Signing in by phone is PhoneLoginController.
 */
#[Group('Auth')]
class PhoneVerificationController extends Controller
{
    public function __construct(
        protected PhoneOtpService $otp,
        protected AccountLinkingService $linking,
    ) {}

    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
        ]);

        $sent = $this->otp->send($request->phone);

        if (!$sent['sent']) {
            return response()->json([
                'success' => false,
                'message' => 'SMS sending failed.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent.',
            'code' => PhoneOtpService::exposeCode($sent['code']),
        ]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
            'code' => 'required|string|size:6',
        ]);

        $phone = $request->phone;

        if ($error = $this->otp->verify($phone, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 422);
        }

        // Store the verified phone; a customer whose phone already belongs to another customer is linked to that
        // account when nothing can be lost, else refused with 409 (AccountLinkingService)
        $user = $request->user();
        $linked = false;
        if ($user) {
            try {
                ['user' => $user, 'linked' => $linked] = $this->linking->attachVerifiedPhone($user, $phone);
            } catch (AccountLinkConflictException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => ['reason' => 'phone_linked_to_another_account'],
                ], 409);
            }
        }

        return response()->json(array_merge([
            'success' => true,
            'message' => $linked
                ? 'Telefon raqamingiz tasdiqlandi va hisoblaringiz birlashtirildi.'
                : 'Telefon raqamingiz muvaffaqiyatli tasdiqlandi.',
            'user' => $user,
        ], $linked ? ['account_linked' => true] : []));
    }
}
