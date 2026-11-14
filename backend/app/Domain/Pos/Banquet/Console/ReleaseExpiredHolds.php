<?php

namespace App\Domain\Pos\Banquet\Console;

use App\Domain\Pos\Banquet\BanquetEventService;
use Illuminate\Console\Command;

/**
 * P6-51: every minute, a banquet hold whose `hold_expires_at` passed without a paid schedule row is cancelled and its
 * halls released (BanquetEventService::releaseExpiredHolds; audited as banquet.hold_expired).
 */
class ReleaseExpiredHolds extends Command
{
    protected $signature = 'pos:banquet-release-holds';

    protected $description = 'Release banquet holds whose time ran out without a deposit';

    public function handle(BanquetEventService $events): int
    {
        $released = $events->releaseExpiredHolds();
        if ($released > 0) {
            $this->info("Released {$released} banquet hold(s).");
        }

        return self::SUCCESS;
    }
}
