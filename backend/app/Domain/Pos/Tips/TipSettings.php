<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\PosTipSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;

/**
 * P6-22 (owner decision 29.10.2026): a branch's tip settings (pos_tip_settings) read with the defaults — `personal`
 * mode, an `equal` split per business `day` among waiters, every role weighing 1. Changes are the owner's
 * (settings.manage, PUT /owner/pos/tips/settings) and audited (settings.tips_changed).
 *
 *  - personal: a tip goes to the waiter the cashier picked at payment, else the order's waiter (orders.waiter_id);
 *    with neither it waits unassigned until a manager gives it to someone (a correction);
 *  - pool: tips go to the branch's pot for the period; TipPoolDistributor splits it among the staff of the chosen
 *    `roles` who worked in it, by `pool_rule`.
 */
final class TipSettings
{
    /** The shape of the settings (owner API, bootstrap). */
    public const VIEW = 'array{branch_id: int, enabled: bool, mode: string, pool_rule: string, pool_period: string, roles: list<string>, role_weights: array<string, string>, updated_at: string|null}';

    public const DEFAULT_ROLES = [UserRole::WAITER->value];

    public const MAX_WEIGHT = '100';

    public function __construct(
        private readonly PosModules $modules,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The roles that may take part in a pool: the store's floor staff (the owner never takes a share).
     *
     * @return list<string>
     */
    public static function poolRoles(): array
    {
        return array_values(array_map(
            fn (UserRole $role) => $role->value,
            array_filter(UserRole::terminalRoles(), fn (UserRole $role) => $role !== UserRole::OWNER),
        ));
    }

    public function row(Branch $branch): ?PosTipSetting
    {
        return PosTipSetting::where('branch_id', $branch->id)->first();
    }

    public function mode(Branch $branch): TipMode
    {
        return $this->row($branch)->mode ?? TipMode::PERSONAL;
    }

    /**
     * @return array{branch_id: int, enabled: bool, mode: string, pool_rule: string, pool_period: string, roles: list<string>, role_weights: array<string, string>, updated_at: string|null}
     */
    public function present(Branch $branch, ?PosTipSetting $row = null): array
    {
        $row ??= $this->row($branch);

        return [
            'branch_id' => (int) $branch->id,
            'enabled' => $this->modules->enabled($branch, PosModule::TIPS),
            'mode' => ($row->mode ?? TipMode::PERSONAL)->value,
            'pool_rule' => ($row->pool_rule ?? TipPoolRule::EQUAL)->value,
            'pool_period' => ($row->pool_period ?? TipPoolPeriod::DAY)->value,
            'roles' => $this->roles($row),
            'role_weights' => $this->weights($row),
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    public function roles(?PosTipSetting $row): array
    {
        $roles = is_array($row?->roles) ? array_values(array_intersect(self::poolRoles(), $row->roles)) : [];

        return $roles !== [] ? $roles : self::DEFAULT_ROLES;
    }

    /**
     * Every pool role with its weight ("1.00" unless the owner set one).
     *
     * @return array<string, string>
     */
    public function weights(?PosTipSetting $row): array
    {
        $stored = is_array($row?->role_weights) ? $row->role_weights : [];
        $weights = [];
        foreach (self::poolRoles() as $role) {
            $value = $stored[$role] ?? null;
            $weights[$role] = is_numeric($value) && Money::of((string) $value)->isPositive() ? Money::of((string) $value)->toString() : '1.00';
        }

        return $weights;
    }

    /**
     * Saves the settings (only the keys given change) and audits the change.
     *
     * @param  array{mode?: string, pool_rule?: string, pool_period?: string, roles?: list<string>, role_weights?: array<string, string|int|float>}  $data
     */
    public function update(Branch $branch, array $data, User $actor): PosTipSetting
    {
        $row = $this->row($branch) ?? new PosTipSetting(['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
        $before = $row->exists ? $this->present($branch, $row) : [];

        if (isset($data['mode'])) {
            $row->mode = TipMode::from($data['mode']);
        }
        if (isset($data['pool_rule'])) {
            $row->pool_rule = TipPoolRule::from($data['pool_rule']);
        }
        if (isset($data['pool_period'])) {
            $row->pool_period = TipPoolPeriod::from($data['pool_period']);
        }
        if (array_key_exists('roles', $data)) {
            $row->roles = array_values(array_unique(array_intersect(self::poolRoles(), $data['roles'])));
        }
        if (array_key_exists('role_weights', $data)) {
            $weights = [];
            foreach ($data['role_weights'] as $role => $weight) {
                if (in_array($role, self::poolRoles(), true)) {
                    $weights[$role] = Money::of((string) $weight)->toString();
                }
            }
            $row->role_weights = $weights;
        }
        $row->mode ??= TipMode::PERSONAL;
        $row->pool_rule ??= TipPoolRule::EQUAL;
        $row->pool_period ??= TipPoolPeriod::DAY;
        $row->updated_by = $actor->id;
        $row->save();

        $this->audit->record('settings.tips_changed', $row, $before, $this->present($branch, $row), [], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $row;
    }
}
