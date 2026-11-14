<?php

namespace App\Domain\Pos\Banquet;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6-51: the `banquet` section of GET /pos/bootstrap: the store's slots (local times), hold hours and warning limits
 * for the till's banquet calendar; null while the branch does not have the banquet module.
 */
final class BanquetBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = 'array{slots: array{morning: array{start: string, end: string}, day: array{start: string, end: string}, evening: array{start: string, end: string}}, hold_hours: int, guest_warning_thresholds: list<int>, window_start: string, window_end: string, quote_currency: string|null}|null';

    public function __construct(
        private readonly BanquetSettings $settings,
        private readonly PosModules $modules,
    ) {}

    /**
     * @return array{banquet: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        if (!$this->modules->enabled($branch, PosModule::BANQUET)) {
            return ['banquet' => null];
        }
        $view = $this->settings->view((int) $branch->store_id);

        return ['banquet' => [
            'slots' => $view['slots'],
            'hold_hours' => $view['hold_hours'],
            'guest_warning_thresholds' => $view['guest_warning_thresholds'],
            'window_start' => $view['window_start'],
            'window_end' => $view['window_end'],
            'quote_currency' => $view['quote_currency'],
        ]];
    }
}
