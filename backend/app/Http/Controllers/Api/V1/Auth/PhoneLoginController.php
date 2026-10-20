<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\GenerateAuthTokenAction;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\Store;
use App\Services\Auth\AccountLinkConflictException;
use App\Services\Auth\AccountLinkingService;
use App\Services\Auth\DeviceService;
use App\Services\Auth\PhoneOtpService;
use App\Services\Auth\TokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signing in by phone and SMS code, for clients without Telegram (the mobile apps). The customer with that verified
 * phone signs in (the same account they use in the Mini App, once they verified the phone there); a new customer is
 * created for an unknown phone. The answer looks like the Telegram login's; with a `device` the refresh token fields
 * are added (DeviceRequest::rules).
 */
#[Group('Auth')]
class PhoneLoginController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PhoneOtpService $otp,
        protected AccountLinkingService $linking,
        protected DeviceService $devices,
        protected TokenService $tokens,
        protected GenerateAuthTokenAction $generateToken,
    ) {}

    public function sendCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
        ]);

        // Sent whether or not an account exists: the answer must not tell who is registered
        $sent = $this->otp->send($request->phone);

        if (!$sent['sent']) {
            return $this->error("SMS yuborib bo'lmadi. Birozdan so'ng qayta urinib ko'ring.", [], 500);
        }

        return $this->success(['code' => PhoneOtpService::exposeCode($sent['code'])], 'Tasdiqlash kodi yuborildi.');
    }

    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, user: User, store: Store|null, sms_verification_required: bool, is_new_user: bool, token_expires_at?: string, refresh_token?: string, refresh_token_expires_at?: string, device?: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}}}')]
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
            'code' => 'required|string|size:6',
        ] + DeviceRegistrationRules::rules());

        if ($error = $this->otp->verify($request->phone, $request->code)) {
            return $this->error($error, [], 422);
        }

        try {
            ['user' => $user, 'created' => $created] = $this->linking->customerForVerifiedPhone($request->phone);
        } catch (AccountLinkConflictException $e) {
            return $this->error($e->getMessage(), ['reason' => 'phone_linked_to_several_accounts'], 409);
        }

        $payload = [
            'token' => null,
            'user' => $user,
            'store' => $user->store_id ? Store::with('branches')->find($user->store_id) : null,
            'sms_verification_required' => setting('sms_driver', 'log') === 'eskiz',
            'is_new_user' => $created,
        ];

        if ($request->filled('device')) {
            $payload = array_merge($payload, $this->tokens->issuePair($user, $this->devices->register($user, $request->input('device')))->toArray());
        } else {
            $payload['token'] = $this->generateToken->execute($user);
        }

        return $this->success($payload, 'Authenticated successfully.');
    }
}
