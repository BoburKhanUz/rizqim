<?php

namespace App\Domain\Pos\Menu;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * The daily menu, "keyingi partiya" and kompleks sections of GET /pos/bootstrap (P6-15). Each is empty/false while its
 * module is off for the branch (`format.modules`), so the till shows nothing of it:
 *  - `daily_menu`: {business_date, enforced, set, product_ids}; the dishes off the menu are already stopped in the POS
 *    catalog's availability, this only says whether a menu was set for the day (the cashier is warned when not);
 *  - `next_batches`: the branch's planned batches still ahead, earliest first ({id, uuid, product_id, product_variant_id,
 *    planned_at, time, quantity, label});
 *  - `meal_sets`: the kompleks of the store with their parts ({product_id, parts: [...]}).
 */
final class MenuBootstrapSection implements PosBootstrapContributor
{
    public function __construct(
        private readonly PosModules $modules,
        private readonly DailyMenuService $dailyMenu,
        private readonly BatchPlans $plans,
        private readonly MealSets $sets,
    ) {}

    /**
     * @return array{daily_menu: array{business_date: string, enforced: bool, set: bool, product_ids: list<int>}|null, next_batches: list<array<string, mixed>>, meal_sets: list<array{product_id: int, parts: list<array<string, mixed>>}>}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $dailyMenu = $this->modules->enabled($branch, PosModule::DAILY_MENU) ? $this->dailyMenu->summary($branch) : null;
        $next = $this->modules->enabled($branch, PosModule::BATCHES) ? ($this->plans->upcoming([(int) $branch->id])[(int) $branch->id] ?? []) : [];

        $sets = [];
        if ($this->modules->enabled($branch, PosModule::COMBO_MEAL)) {
            foreach ($this->sets->partsByProduct((int) $branch->store_id) as $productId => $parts) {
                $sets[] = ['product_id' => $productId, 'parts' => $parts];
            }
        }

        return ['daily_menu' => $dailyMenu, 'next_batches' => $next, 'meal_sets' => $sets];
    }
}
