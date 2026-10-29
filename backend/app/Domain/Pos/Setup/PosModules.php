<?php

namespace App\Domain\Pos\Setup;

use App\Models\Branch;
use App\Services\Finance\Billing\BillingRefusal;
use App\Services\Finance\Billing\Entitlements;

/**
 * P6-11: the effective POS modules of a branch — the one check every module gate goes through (docs/POS_SPEC.md
 * "Format profili"): GET /pos/bootstrap's `format`, GET /owner/pos/format, the `pos.module:<key>` route middleware and
 * the checks in the controllers.
 *
 *   effective = (profile defaults + owner overrides) − law locks (stolovaya: service_charge) − modules whose plan feature
 *               the store's plan does not have (Entitlements, P4-21)
 *
 * Gates SETUP and new flows only: taking payments, closing bills, receipts, shifts and refunds never ask this class.
 */
class PosModules
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function profile(Branch $branch): FormatProfile
    {
        return PosSettings::format($branch->pos_settings)['profile'];
    }

    /**
     * The owner's overrides as stored ({module: bool}).
     *
     * @return array<string, bool>
     */
    public function overrides(Branch $branch): array
    {
        return PosSettings::format($branch->pos_settings)['modules'];
    }

    /**
     * Whether the branch's format switches the module on (profile + overrides − locks), whatever the plan.
     */
    public function configured(Branch $branch, PosModule $module): bool
    {
        $format = PosSettings::format($branch->pos_settings);
        if (in_array($module, $format['profile']->lockedOff(), true)) {
            return false;
        }

        return $format['modules'][$module->value] ?? in_array($module, $format['profile']->modules(), true);
    }

    /**
     * Whether the store's plan has what the module needs (true for a module every plan has).
     */
    public function planAllows(Branch $branch, PosModule $module): bool
    {
        $feature = $module->planFeature();

        return $feature === null || $this->entitlements->allows((int) $branch->store_id, $feature);
    }

    public function enabled(Branch $branch, PosModule $module): bool
    {
        return $this->configured($branch, $module) && $this->planAllows($branch, $module);
    }

    /**
     * The effective modules, in PosModule order.
     *
     * @return list<PosModule>
     */
    public function effective(Branch $branch): array
    {
        return array_values(array_filter(PosModule::cases(), fn (PosModule $module) => $this->enabled($branch, $module)));
    }

    /**
     * The module is on for the branch, else the refusal: the plan's first (403 plan_feature_missing, the plan that has
     * it), then the format's (403 module_disabled).
     *
     * @throws BillingRefusal plan_feature_missing
     * @throws PosModuleDisabled module_disabled
     */
    public function assertEnabled(Branch $branch, PosModule $module): void
    {
        $feature = $module->planFeature();
        if ($feature !== null) {
            $this->entitlements->assertFeature((int) $branch->store_id, $feature);
        }
        if (!$this->configured($branch, $module)) {
            throw PosModuleDisabled::for($module, $this->profile($branch));
        }
    }

    /**
     * For a request that names no branch (an owner's store-wide setting, a record addressed by id): the module is on
     * when it is on for at least one active branch of the store.
     */
    public function enabledInStore(int $storeId, PosModule $module): bool
    {
        return $this->firstBranchWith($storeId, $module) !== null;
    }

    /**
     * @throws BillingRefusal plan_feature_missing
     * @throws PosModuleDisabled module_disabled
     */
    public function assertEnabledInStore(int $storeId, PosModule $module): void
    {
        $feature = $module->planFeature();
        if ($feature !== null) {
            $this->entitlements->assertFeature($storeId, $feature);
        }

        $branches = Branch::query()->where('store_id', $storeId)->orderBy('id')->get(['id', 'store_id', 'pos_settings']);
        if ($branches->isEmpty() || $branches->contains(fn (Branch $branch) => $this->configured($branch, $module))) {
            return;
        }

        throw PosModuleDisabled::for($module, $branches->count() === 1 ? $this->profile($branches->first()) : null);
    }

    /**
     * The law's service charge lock (stolovaya) for a branch by id: the fee calculator reads it on every order.
     */
    public function serviceChargeLocked(?int $branchId): bool
    {
        if ($branchId === null) {
            return false;
        }
        $branch = Branch::query()->whereKey($branchId)->first(['id', 'pos_settings']);

        return $branch !== null && $this->profile($branch)->locksServiceCharge();
    }

    /**
     * The till's first screen for the branch's profile and mode: a table-only branch opens on its hall map (or the
     * booking calendar), a counter branch on the quick counter.
     */
    public function firstScreen(Branch $branch, string $mode): string
    {
        $screen = $this->profile($branch)->firstScreen();

        return match (true) {
            $mode === 'table' && in_array($screen, ['quick_counter', 'online_orders'], true) => 'hall_map',
            $mode === 'counter' && $screen === 'hall_map' => 'quick_counter',
            $screen === 'quick_counter' && $mode !== 'counter' && !$this->enabled($branch, PosModule::QUICK_COUNTER) => 'hall_map',
            default => $screen,
        };
    }

    /**
     * GET /pos/bootstrap `format` (and the top of GET /owner/pos/format).
     *
     * @return array{profile: string, profile_label: string, mode: string, first_screen: string, modules: list<string>, locks: array{service_charge_zero: bool}}
     */
    public function format(Branch $branch): array
    {
        $profile = $this->profile($branch);
        $mode = PosSettings::resolve($branch->pos_settings)['mode'];

        return [
            'profile' => $profile->value,
            'profile_label' => $profile->label(),
            'mode' => $mode,
            'first_screen' => $this->firstScreen($branch, $mode),
            'modules' => array_map(fn (PosModule $module) => $module->value, $this->effective($branch)),
            'locks' => ['service_charge_zero' => $profile->locksServiceCharge()],
        ];
    }

    /**
     * One row per module for the owner's "Format" page: what the profile gives, the owner's override, the plan and the
     * law's locks, and the result.
     *
     * @return list<array{key: string, label: string, description: string, default: bool, override: bool|null, configured: bool, plan_allows: bool, required_plan: string|null, required_plan_name: string|null, locked: string|null, enabled: bool}>
     */
    public function rows(Branch $branch): array
    {
        $profile = $this->profile($branch);
        $overrides = $this->overrides($branch);
        $rows = [];

        foreach (PosModule::cases() as $module) {
            $planAllows = $this->planAllows($branch, $module);
            $required = !$planAllows && $module->planFeature() !== null ? $this->entitlements->requiredPlan($module->planFeature()) : null;
            $configured = $this->configured($branch, $module);
            $rows[] = [
                'key' => $module->value,
                'label' => $module->label(),
                'description' => $module->description(),
                'default' => in_array($module, $profile->modules(), true),
                'override' => $overrides[$module->value] ?? null,
                'configured' => $configured,
                'plan_allows' => $planAllows,
                'required_plan' => $required?->code,
                'required_plan_name' => $required?->name,
                // Why it cannot be switched on: the law (stolovaya) or the plan ("Asosiy tarifda bor")
                'locked' => in_array($module, $profile->lockedOff(), true) ? 'law' : (!$planAllows ? 'plan' : null),
                'enabled' => $configured && $planAllows,
            ];
        }

        return $rows;
    }

    private function firstBranchWith(int $storeId, PosModule $module): ?Branch
    {
        return Branch::query()->where('store_id', $storeId)->orderBy('id')->get(['id', 'store_id', 'pos_settings'])
            ->first(fn (Branch $branch) => $this->enabled($branch, $module));
    }
}
