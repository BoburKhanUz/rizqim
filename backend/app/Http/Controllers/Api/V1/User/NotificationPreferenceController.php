<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in person's own message settings: which kinds of message reach them over Telegram and push.
 */
#[Group('Notifications', "Xabarnoma sozlamalari: qaysi xabar Telegram va push orqali kelishi.")]
class NotificationPreferenceController extends Controller
{
    use ApiResponse;

    private const RESPONSE = 'array{success: true, message: string, data: array{preferences: list<array{type: string, label: string, description: string, channels: list<array{channel: string, label: string, enabled: bool, available: bool}>}>}}';

    public function __construct(
        protected NotificationPreferenceService $preferences,
    ) {}

    /**
     * GET /me/notification-preferences: the message types that fit the user's role, each with its channels. `available`
     * says whether the channel can reach the user at all (Telegram linked, an app with a push token registered).
     */
    #[ApiDocResponse(200, type: self::RESPONSE)]
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(['preferences' => $this->preferences->forUser($user)]);
    }

    /**
     * PUT /me/notification-preferences: `{"preferences": {"order_updates": {"push": false}, "promotions": {"push": true}}}`.
     * Only what is sent changes. Order updates are on and promotions off until the user says otherwise.
     */
    #[ApiDocResponse(200, type: self::RESPONSE)]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate(['preferences' => 'required|array|min:1']);

        $allowed = NotificationType::forRole($user->role);
        $changes = [];

        foreach ((array) $request->input('preferences') as $typeName => $channels) {
            $type = NotificationType::tryFrom((string) $typeName);

            if ($type === null || !in_array($type, $allowed, true)) {
                throw ValidationException::withMessages(["preferences.{$typeName}" => "Noma'lum xabar turi."]);
            }

            if (!is_array($channels) || $channels === []) {
                throw ValidationException::withMessages(["preferences.{$typeName}" => "Kanallar ro'yxati kerak."]);
            }

            foreach ($channels as $channelName => $enabled) {
                $channel = NotificationChannel::tryFrom((string) $channelName);

                if ($channel === null || !in_array($channel, $type->channels(), true)) {
                    throw ValidationException::withMessages(["preferences.{$typeName}.{$channelName}" => "Noma'lum kanal."]);
                }

                $value = filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null || is_array($enabled)) {
                    throw ValidationException::withMessages(["preferences.{$typeName}.{$channelName}" => 'true yoki false bo\'lishi kerak.']);
                }

                $changes[$type->value][$channel->value] = $value;
            }
        }

        $this->preferences->update($user, $changes);

        return $this->success(['preferences' => $this->preferences->forUser($user)], 'Sozlamalar saqlandi.');
    }
}
