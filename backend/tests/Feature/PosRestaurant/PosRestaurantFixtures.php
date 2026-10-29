<?php

namespace Tests\Feature\PosRestaurant;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\Order;
use App\Models\ProductVariant;
use Tests\Feature\PosHallFixtures;

/**
 * P6-21 / P6-25 helpers on top of the P3-12 hall fixtures: the branch's format profile (restoran, kabobxona, baliqxona,
 * kafe or choyxona) with optional module overrides, a fish sold by the kg and weighed later, and the order's version.
 */
trait PosRestaurantFixtures
{
    use PosHallFixtures;

    protected ProductVariant $fish;

    protected function setUpRestaurant(FormatProfile $profile = FormatProfile::RESTORAN, array $modules = []): void
    {
        $this->setUpPosHall();
        $this->profile($profile, $modules);

        $this->fish = $this->makeVariant($this->makeProduct('Sazan', ['weight_mode' => 'raw']), '245000', [
            'name' => 'Mangal', 'unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.5', 'stock' => 100,
        ]);
    }

    /**
     * @param  array<string, bool>  $modules
     */
    protected function profile(FormatProfile $profile, array $modules = []): void
    {
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->fresh()->pos_settings, $profile, $modules)])->save();
    }

    protected function versionOf(Order $order): int
    {
        return (int) $order->fresh()->version;
    }
}
