<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Device;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which message types a person wants on which channel. Explicit choices are rows; the rest follow
 * NotificationType::defaultEnabled().
 */
class NotificationPreferenceService
{
    public function allows(User $user, NotificationType $type, NotificationChannel $channel): bool
    {
        $row = NotificationPreference::where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('channel', $channel->value)
            ->first();

        return $row ? $row->enabled : $type->defaultEnabled($channel);
    }

    /**
     * The settings screen: the types that fit the user's role, each channel with its current value and whether the user
     * can receive it at all (a Telegram account linked, a device with a push token). Tokens are never part of it.
     *
     * @return list<array{type: string, label: string, description: string, channels: list<array{channel: string, label: string, enabled: bool, available: bool}>}>
     */
    public function forUser(User $user): array
    {
        $stored = NotificationPreference::where('user_id', $user->id)->get()
            ->mapWithKeys(fn (NotificationPreference $row) => ["{$row->type}|{$row->channel}" => $row->enabled]);

        $hasPushDevice = Device::where('user_id', $user->id)->active()->whereNotNull('push_token')->exists();

        return array_map(fn (NotificationType $type) => [
            'type' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'channels' => array_map(fn (NotificationChannel $channel) => [
                'channel' => $channel->value,
                'label' => $channel->label(),
                'enabled' => (bool) $stored->get("{$type->value}|{$channel->value}", $type->defaultEnabled($channel)),
                'available' => match ($channel) {
                    NotificationChannel::TELEGRAM => $user->hasTelegram(),
                    NotificationChannel::PUSH => $hasPushDevice,
                    default => false,
                },
            ], $type->channels()),
        ], NotificationType::forRole($user->role));
    }

    /**
     * @param array<string, array<string, bool>> $changes type => channel => enabled, already validated against the role
     */
    public function update(User $user, array $changes): void
    {
        DB::transaction(function () use ($user, $changes) {
            foreach ($changes as $type => $channels) {
                foreach ($channels as $channel => $enabled) {
                    NotificationPreference::updateOrCreate(
                        ['user_id' => $user->id, 'type' => $type, 'channel' => $channel],
                        ['enabled' => (bool) $enabled],
                    );
                }
            }
        });
    }
}
