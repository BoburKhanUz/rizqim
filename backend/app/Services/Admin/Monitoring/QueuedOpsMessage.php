<?php

namespace App\Services\Admin\Monitoring;

use App\Jobs\SendTelegramRequest;
use App\Models\MonitoringMarker;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3 (RR-8): an ops alert message that could not be sent at once and went to the queue. Its monitoring_markers row
 * (`ops:queued:<uuid>`, written before the push) is removed once Telegram accepted it; while the row is there the
 * notifier sends the text directly after QUEUED_RESEND_MINUTES (the worker may be dead: often the very alert).
 */
class QueuedOpsMessage extends SendTelegramRequest
{
    public function __construct(string $chatId, string $text, public string $markerKey)
    {
        parent::__construct('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
    }

    protected function record(string $status, ?string $error = null): void
    {
        if ($status !== 'sent') {
            return;
        }
        try {
            MonitoringMarker::query()->whereKey($this->markerKey)->delete();
        } catch (Throwable $e) {
            // The notifier may send it once more directly: a duplicate is better than a lost alert
            Log::warning('Monitoring: queued ops message sent, its marker could not be removed', ['exception' => $e::class]);
        }
    }
}
