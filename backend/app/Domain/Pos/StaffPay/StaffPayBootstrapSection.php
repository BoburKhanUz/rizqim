<?php

namespace App\Domain\Pos\StaffPay;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * `staff_pay` of GET /pos/bootstrap (P6-23), while the branch has the `staff_pay` module (null otherwise): whether the
 * till offers the clock-in/out ("Ishga keldim" / "Ishdan ketdim") and the signed-in person's open work session. Never
 * a rule or an amount: pay stays in the owner's panel.
 */
final class StaffPayBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = 'array{clock: bool, session: array{uuid: string, business_date: string, clock_in_at: string}|null}';

    public function __construct(
        private readonly PosModules $modules,
        private readonly WorkTime $workTime,
    ) {}

    /**
     * @return array{staff_pay: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        if (!$this->modules->enabled($branch, PosModule::STAFF_PAY)) {
            return ['staff_pay' => null];
        }

        $session = $this->workTime->current($user);

        return ['staff_pay' => [
            'clock' => true,
            'session' => $session !== null ? [
                'uuid' => (string) $session->uuid,
                'business_date' => $session->business_date->toDateString(),
                'clock_in_at' => $session->clock_in_at->toIso8601String(),
            ] : null,
        ]];
    }
}
