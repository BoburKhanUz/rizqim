<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\AuthTokenException;
use App\Services\Auth\TokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Refreshing a device-bound session and logging out (this device, or everywhere).
 */
#[Group('Auth')]
class TokenController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TokenService $tokens,
    ) {}

    /**
     * POST /auth/refresh {refresh_token, device_uuid}: spends the refresh token, returns a new access + refresh pair.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, token_expires_at: string, refresh_token: string, refresh_token_expires_at: string, device: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}}}')]
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string|max:200',
            'device_uuid' => 'required|uuid',
        ]);

        try {
            $issued = $this->tokens->refresh($request->string('refresh_token')->toString(), $request->string('device_uuid')->toString());
        } catch (AuthTokenException $e) {
            return $this->error($e->getMessage(), $e->data(), $e->status);
        }

        return $this->success($issued->toArray(), 'Sessiya yangilandi.');
    }

    /**
     * POST /auth/logout: ends the session this token belongs to. A device-bound token also ends the device's refresh
     * tokens; a Mini App token just stops working.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // The stored token behind the request (a TransientToken from a session has no row to delete)
        $tokenId = data_get($user->currentAccessToken(), 'id');
        $token = $tokenId ? PersonalAccessToken::find($tokenId) : null;

        if ($token) {
            $device = $token->getAttribute('device_id') ? Device::find($token->getAttribute('device_id')) : null;
            if ($device && $device->user_id !== null && (int) $device->user_id === (int) $user->id) {
                $this->tokens->revokeUserDevice($user, $device);
            }
            $token->delete();
        }

        return $this->success(null, 'Tizimdan chiqdingiz.');
    }

    /**
     * POST /auth/logout-all: ends every session of the user on every device (staff PIN sessions included).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->tokens->revokeAllForUser($user);

        return $this->success(null, 'Barcha qurilmalardan chiqdingiz.');
    }
}
