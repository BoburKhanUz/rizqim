<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Contracts\CourseTracker;
use App\Domain\Pos\Contracts\PickupTracker;
use App\Domain\Pos\Setup\PosBootstrap;
use App\Domain\Pos\Shifts\QueueBootstrapSection;
use Illuminate\Support\ServiceProvider;

/**
 * P6-13 (A2): order numbers and calling.
 *
 *  - PickupTracker (what OrderStatusService and KitchenService tell the pickup queue) is PickupQueue;
 *  - the `queue` section of GET /pos/bootstrap (the till's number blocks of today) is QueueBootstrapSection;
 *  - P6-21: CourseTracker (KitchenService: a course's last line ready) is PosCourses.
 */
class PosQueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PickupTracker::class, PickupQueue::class);
        $this->app->bind(CourseTracker::class, PosCourses::class);
        $this->app->tag([QueueBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }
}
