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
use App\Services\Auth\StaffPhoneLoginService;
use App\Services\Auth\TokenService;
use App\Services\Audit\AuditLogger;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signing in by phone and SMS code, for clients without Telegram (the mobile apps). The customer with that verified
 * phone signs in (the same account they use in the Mini App, once they verified the phone there); a new customer is
 * created for an unknown phone. The answer looks like the Telegram login's; with a `device` the refresh token fields
 * are added (DeviceRequest::rules).
 *
 * `account: staff` (P1-36; the partner panel and the staff apps) signs in to the store staff account holding that
 * verified phone instead (StaffPhoneLoginService): never a customer account, never a new one.
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
        protected StaffPhoneLoginService $staff,
        protected AuditLogger $audit,
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

    /**
     * Check the SMS code and sign in.
     *
     * Without `account` (or with `account: customer`) the customer holding the phone signs in (created when there is
     * none). With `account: staff` the store staff account holding it as its login phone does, and a `device` of type
     * `web`, `partner_mobile` or `courier` is required: 403 `staff_account_not_found` when there is none; 409
     * `account_selection_required` with `selection_token` and `accounts` when the phone holds several (the same person
     * in two stores), and the client then calls POST /auth/phone/select-account.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, user: User, store: Store|null, sms_verification_required: bool, is_new_user: bool, token_expires_at?: string, refresh_token?: string, refresh_token_expires_at?: string, device?: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}}}')]
    #[ApiDocResponse(403, 'No store staff account holds this phone (account: staff)', type: 'array{success: false, message: string, data: array{reason: string}}')]
    #[ApiDocResponse(409, 'Several accounts: customers sharing the phone, or (account: staff) several staff accounts to pick from with POST /auth/phone/select-account', type: 'array{success: false, message: string, data: array{reason: string, selection_token?: string, selection_expires_in?: int, accounts?: list<array{user_id: int, name: string, role: string, role_label: string, store: array{id: int, name: string}, branch: array{id: int, name: string}|null}>}}')]
    public function verify(Request $request): JsonResponse
    {
        $staff = $request->input('account') === 'staff';
        $request->validate(array_merge(DeviceRegistrationRules::rules(), [
            'phone' => 'required|string|regex:/^\+998\d{9}$/',
            'code' => 'required|string|size:6',
            // P1-36: which kind of account the code signs in to; staff sessions are always device-bound (refreshable,
            // listed and revocable under the store's devices)
            'account' => 'sometimes|string|in:customer,staff',
        ], $staff ? self::staffDeviceRules() : []));

        if ($error = $this->otp->verify($request->phone, $request->code)) {
            return $this->error($error, [], 422);
        }

        if ($staff) {
            return $this->staffSignIn($request);
        }

        try {
            ['user' => $user, 'created' => $created] = $this->linking->customerForVerifiedPhone($request->phone);
        } catch (AccountLinkConflictException $e) {
            return $this->error($e->getMessage(), ['reason' => 'phone_linked_to_several_accounts'], 409);
        }

        [$payload] = $this->signIn($user, $request, $created);

        return $this->success($payload, 'Authenticated successfully.');
    }

    /**
     * Finish a staff sign-in when the phone holds several staff accounts: the `selection_token` the verify call
     * answered with (single use, 5 minutes) and the chosen `user_id` from its `accounts`. A wrong, used or expired
     * token, or an account that was not offered, is 422 `selection_invalid` (sign in again with a new code).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, user: User, store: Store|null, sms_verification_required: bool, is_new_user: bool, token_expires_at: string, refresh_token: string, refresh_token_expires_at: string, device: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}}}')]
    #[ApiDocResponse(422, 'The selection token is unknown, expired or used, or the account was not offered', type: 'array{success: false, message: string, data: array{reason: string}}')]
    public function selectAccount(Request $request): JsonResponse
    {
        $request->validate(array_merge(DeviceRegistrationRules::rules(), [
            'selection_token' => 'required|string|max:100',
            'user_id' => 'required|integer|min:1',
            // As in verify with account: staff (staffDeviceRules)
            'device' => 'required|array',
            'device.type' => ['required', 'string', Rule::in(['web', 'partner_mobile', 'courier'])],
        ]));

        $user = $this->staff->redeemTicket((string) $request->input('selection_token'), (int) $request->input('user_id'));

        if (!$user) {
            return $this->error("Tanlov muddati tugagan yoki noto'g'ri. Iltimos, qaytadan kiring.", ['reason' => 'selection_invalid'], 422);
        }

        return $this->staffSignedIn($user, $request);
    }

    protected function staffSignIn(Request $request): JsonResponse
    {
        $accounts = $this->staff->accountsFor($request->phone);

        if ($accounts->isEmpty()) {
            return $this->error("Bu raqam hech qaysi do'kon xodimiga biriktirilmagan. Do'kon egasidan raqamingizni xodimlar ro'yxatiga kiritishni so'rang.", ['reason' => 'staff_account_not_found'], 403);
        }

        if ($accounts->count() > 1) {
            return $this->error("Bu raqam bir nechta hisobga biriktirilgan. Qaysi biriga kirishni tanlang.", [
                'reason' => 'account_selection_required',
                'selection_token' => $this->staff->issueTicket($request->phone, $accounts),
                'selection_expires_in' => 300,
                'accounts' => $this->staff->describe($accounts),
            ], 409);
        }

        return $this->staffSignedIn($accounts->first(), $request);
    }

    /**
     * A staff sign-in is written to the store's audit log (who, on which device, from where).
     */
    protected function staffSignedIn(User $user, Request $request): JsonResponse
    {
        // The answer's `user` is the plain account, as in every other login (not the store/branch the lookup loaded)
        $user = $user->withoutRelations();
        [$payload, $device] = $this->signIn($user, $request, false);

        $this->audit->record('staff.signed_in', $user, [], [], [
            'staff_name' => $user->name,
            'role' => $user->role->value,
            'via' => 'phone',
        ], ['actor' => $user, 'device' => $device, 'store_id' => $user->store_id, 'branch_id' => $user->branch_id]);

        return $this->success($payload, 'Authenticated successfully.');
    }

    /**
     * The login answer: the Telegram login's shape, plus the refresh token fields when a device came with it.
     *
     * @return array{0: array<string, mixed>, 1: Device|null}
     */
    protected function signIn(User $user, Request $request, bool $created): array
    {
        $payload = [
            'token' => null,
            'user' => $user,
            'store' => $user->store_id ? Store::with('branches')->find($user->store_id) : null,
            'sms_verification_required' => setting('sms_driver', 'log') === 'eskiz',
            'is_new_user' => $created,
        ];

        $device = null;
        if ($request->filled('device')) {
            $device = $this->devices->register($user, $request->input('device'));
            $payload = array_merge($payload, $this->tokens->issuePair($user, $device)->toArray());
        } else {
            $payload['token'] = $this->generateToken->execute($user);
        }

        return [$payload, $device];
    }

    /**
     * Staff sign in on the partner panel or a staff app only, always with a device.
     *
     * @return array<string, mixed>
     */
    private static function staffDeviceRules(): array
    {
        return [
            'device' => 'required|array',
            'device.type' => ['required', 'string', Rule::in(self::staffDeviceTypes())],
        ];
    }

    /**
     * @return list<string>
     */
    private static function staffDeviceTypes(): array
    {
        return [DeviceType::WEB->value, DeviceType::PARTNER_MOBILE->value, DeviceType::COURIER->value];
    }
}
