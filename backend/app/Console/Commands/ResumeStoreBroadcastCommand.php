<?php

namespace App\Console\Commands;

use App\Models\StoreBroadcast;
use App\Services\Admin\Stores\StoreBroadcastService;
use Illuminate\Console\Command;

/**
 * Wave M1: continue an ommaviy e'lon whose sending job gave up half way (status failed). Owners already messaged are
 * not messaged again (StoreBroadcastService::resume).
 */
class ResumeStoreBroadcastCommand extends Command
{
    protected $signature = 'admin:store-broadcast-resume {uuid : the broadcast uuid}';

    protected $description = "To'xtab qolgan ommaviy e'lonni qolgan egalarga yuborishni davom ettiradi";

    public function handle(StoreBroadcastService $broadcasts): int
    {
        $broadcast = StoreBroadcast::query()->where('uuid', (string) $this->argument('uuid'))->first();
        if ($broadcast === null) {
            $this->error("E'lon topilmadi.");

            return self::FAILURE;
        }

        if (!$broadcasts->resume($broadcast)) {
            $this->warn("Davom ettiriladigan narsa yo'q (holati: {$broadcast->status}).");

            return self::FAILURE;
        }

        $this->info("E'lon davom ettirildi.");

        return self::SUCCESS;
    }
}
