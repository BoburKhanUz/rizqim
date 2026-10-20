<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Push\PushContent;
use App\Services\Push\PushSender;
use App\Services\Push\PushStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One push to one device, sent by a queue worker once the dispatching transaction has committed. The device row is read
 * when the job runs, so a token the app refreshed meanwhile is the one used. FCM or network trouble is retried with
 * backoff; a dead token is cleared by PushSender; anything else is logged and dropped. Nothing here ever reaches the
 * order flow that queued it.
 */
class SendPushNotification implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public function __construct(
        public int $deviceId,
        public PushContent $content,
        public string $type = '',
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 300];
    }

    public function handle(PushSender $sender): void
    {
        $device = Device::active()->find($this->deviceId);

        if (!$device || blank($device->push_token)) {
            return;
        }

        $outcome = $sender->send($device, $this->content);

        match ($outcome->status) {
            PushStatus::RETRY => $this->retryOrGiveUp((string) $outcome->detail),
            PushStatus::FAILED => $this->giveUp((string) $outcome->detail),
            default => null,
        };
    }

    public function failed(?Throwable $e): void
    {
        $this->giveUp((string) $e?->getMessage());
    }

    protected function retryOrGiveUp(string $error): void
    {
        // The sync driver (tests, local) cannot retry later, so it gives up at once like a last attempt
        if ($this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
            return;
        }

        $this->giveUp($error);
    }

    protected function giveUp(string $error): void
    {
        Log::warning('Push notification failed', [
            'device_id' => $this->deviceId,
            'type' => $this->type,
            'attempts' => $this->job ? $this->attempts() : 0,
            'error' => $error,
        ]);
    }
}
