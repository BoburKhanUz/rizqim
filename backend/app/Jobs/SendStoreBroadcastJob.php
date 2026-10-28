<?php

namespace App\Jobs;

use App\Models\StoreBroadcast;
use App\Services\Admin\Stores\StoreBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * P4-22: hands an announcement to the Telegram queue, one SendTelegramRequest per store owner
 * (StoreBroadcastService::deliver). Runs once per broadcast: a broadcast already being sent or sent is skipped.
 */
class SendStoreBroadcastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     */
    public function __construct(
        public readonly int $broadcastId,
        public readonly array $audience,
    ) {}

    public function handle(StoreBroadcastService $broadcasts): void
    {
        $broadcast = StoreBroadcast::find($this->broadcastId);
        if ($broadcast !== null) {
            $broadcasts->deliver($broadcast, $this->audience);
        }
    }

    public function failed(?\Throwable $e): void
    {
        StoreBroadcast::query()->whereKey($this->broadcastId)->where('status', '!=', 'sent')
            ->update(['status' => 'failed', 'finished_at' => now()]);
    }
}
