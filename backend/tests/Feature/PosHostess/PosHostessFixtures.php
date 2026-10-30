<?php

namespace Tests\Feature\PosHostess;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Tests\Feature\PosHallFixtures;

/**
 * P6-24 / P6-26 helpers on top of the P3-12 hall fixtures: the branch on the restoran profile (guest split, waiter
 * transfer, hostess and table booking on), a second waiter, a hostess, and the order's version.
 */
trait PosHostessFixtures
{
    use PosHallFixtures;

    protected User $waiter2;

    protected User $hostess;

    protected \App\Models\ProductVariant $steak;

    protected function setUpRestaurantFloor(FormatProfile $profile = FormatProfile::RESTORAN): void
    {
        $this->setUpPosHall();
        $this->profile($profile);

        $this->waiter2 = $this->makeStaff(UserRole::WAITER, $this->branch);
        $this->hostess = $this->makeStaff(UserRole::HOSTESS, $this->branch);
        $this->steak = $this->makeVariant($this->makeProduct('Steyk'), '120000', ['name' => 'Medium', 'stock' => 1000]);
    }

    /**
     * @param  array<string, bool>  $modules
     */
    protected function profile(FormatProfile $profile, array $modules = []): void
    {
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->fresh()->pos_settings, $profile, $modules)])->save();
        $this->branch->refresh();
    }

    protected function versionOf(Order $order): int
    {
        return (int) $order->fresh()->version;
    }

    /**
     * The cashier's PIN session on a paired till with an open shift (a bearer token for pos()).
     */
    protected function openTill(): string
    {
        $till = $this->pairTerminal($this->owner, $this->branch);
        $token = $this->pinSession($till, $this->cashier);
        $this->pos($token, 'POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated();

        return $token;
    }
}
