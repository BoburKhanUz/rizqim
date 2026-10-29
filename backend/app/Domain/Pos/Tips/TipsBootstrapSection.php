<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6-22: the `tips` section of GET /pos/bootstrap: whether the payment screen offers a tip (the branch's `tips`
 * module), the branch's mode (personal: the cashier may pick the waiter the tip goes to, the order's waiter by
 * default; pool: no choice) and the pool's rule and period (shown, never computed on the till).
 */
final class TipsBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = 'array{enabled: bool, mode: string, pool_rule: string, pool_period: string}';

    public function __construct(private readonly TipSettings $settings) {}

    /**
     * @return array{tips: array{enabled: bool, mode: string, pool_rule: string, pool_period: string}}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $view = $this->settings->present($branch);

        return ['tips' => [
            'enabled' => $view['enabled'],
            'mode' => $view['mode'],
            'pool_rule' => $view['pool_rule'],
            'pool_period' => $view['pool_period'],
        ]];
    }
}
