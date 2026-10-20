<?php

namespace App\Services\Push;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/**
 * Sends a push to one registered device and keeps its token honest: a token FCM calls dead is cleared, so nobody keeps
 * paying for pushes to an uninstalled app.
 */
class PushSender
{
    public function __construct(
        protected FcmClient $fcm
    ) {}

    public function isConfigured(): bool
    {
        return $this->fcm->isConfigured();
    }

    public function send(Device $device, PushContent $content): PushOutcome
    {
        if (!$this->fcm->isConfigured()) {
            return new PushOutcome(PushStatus::DISABLED);
        }

        $token = (string) $device->push_token;

        if ($token === '' || $device->isRevoked()) {
            return new PushOutcome(PushStatus::FAILED, 'The device has no push token.');
        }

        $outcome = $this->fcm->send($content, $token);

        if ($outcome->status === PushStatus::INVALID_TOKEN) {
            // Only the token that failed: the app may have registered a fresh one in the meantime
            Device::whereKey($device->id)->where('push_token', $token)->update(['push_token' => null]);
            Log::info('Push token cleared after FCM rejected it', ['device_id' => $device->id, 'detail' => $outcome->detail]);
        }

        return $outcome;
    }
}
