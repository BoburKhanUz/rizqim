<?php

namespace App\Console\Commands;

use App\Models\StoreBroadcast;
use App\Services\Admin\Stores\StoreBroadcastService;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * Wave M1: continue an ommaviy e'lon whose sending job gave up half way (status failed). Owners already messaged are
 * not messaged again (StoreBroadcastService::resume). Review R1-4: audited as `admin.cli.store_broadcast_resumed`, with no
 * actor (the console acted), like the other admin console commands.
 */
class ResumeStoreBroadcastCommand extends Command
{
    protected $signature = 'admin:store-broadcast-resume {uuid : the broadcast uuid}';

    protected $description = "To'xtab qolgan ommaviy e'lonni qolgan egalarga yuborishni davom ettiradi";

    public function handle(StoreBroadcastService $broadcasts, AuditLogger $audit): int
    {
        $broadcast = StoreBroadcast::query()->where('uuid', (string) $this->argument('uuid'))->first();
        if ($broadcast === null) {
            $this->error("E'lon topilmadi.");

            return self::FAILURE;
        }

        $before = $broadcast->status;
        if (!$broadcasts->resume($broadcast)) {
            $this->warn("Davom ettiriladigan narsa yo'q (holati: {$broadcast->status}).");

            return self::FAILURE;
        }

        $after = (string) StoreBroadcast::query()->whereKey($broadcast->id)->value('status');
        $audit->record('admin.cli.store_broadcast_resumed', $broadcast, ['status' => $before], ['status' => $after], [
            'recipients' => (int) $broadcast->recipients,
            'queued' => (int) $broadcast->queued,
        ], ['actor' => null, 'device' => null, 'ip' => null]);

        $this->info("E'lon davom ettirildi.");

        return self::SUCCESS;
    }
}
