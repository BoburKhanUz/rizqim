<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6-26: the `booking_rules` section of GET /pos/bootstrap: the branch's table booking rules (BookingRules::view: slot
 * by place kind, lead time, deposit) for the till and the hostess' screen to prefill a booking and show the deposit
 * asked; null while the branch does not have the table_booking module (a choyxona books as before).
 */
final class BookingRulesBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = BookingRules::VIEW . '|null';

    public function __construct(
        private readonly BookingRules $rules,
        private readonly PosModules $modules,
    ) {}

    /**
     * @return array{booking_rules: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        return ['booking_rules' => $this->modules->enabled($branch, PosModule::TABLE_BOOKING) ? $this->rules->view($branch) : null];
    }
}
