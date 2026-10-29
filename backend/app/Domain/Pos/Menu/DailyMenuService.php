<?php

namespace App\Domain\Pos\Menu;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\Branch;
use App\Models\DailyMenu;
use App\Models\DailyMenuItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Bugungi menyu" (P6-15, stolovaya): the dishes a branch cooks on a business day. A dish marked `daily_rotation` is on
 * sale only while it is on the day's menu; the dishes that do not rotate (bread, tea) are always on sale. A business day
 * with no menu sells everything (the owner is warned in the panel and the bootstrap says `set: false`): a forgotten menu
 * must never stop the till. The rule is in force only while the branch's format has the `daily_menu` module.
 *
 * It reaches checkout and the menus as a stop-list source (offMenuProductIds -> OrderLineBuilder::stopList and
 * MenuAvailability), so every channel refuses an off-menu dish the same way as a stopped one.
 */
class DailyMenuService
{
    /** Shown beside the dish where the stop-list reason is shown. */
    public const OFF_MENU_REASON = "Bugungi menyuda yo'q";

    public function __construct(
        protected AuditLogger $audit,
        protected PosModules $modules,
    ) {}

    /**
     * The branch's current business day (YYYY-MM-DD).
     */
    public function today(Branch $branch): string
    {
        return BusinessDay::of($branch);
    }

    public function find(Branch $branch, string $date): ?DailyMenu
    {
        return DailyMenu::query()->where('branch_id', $branch->id)->whereDate('menu_date', $date)->first();
    }

    /**
     * Whether the daily menu rule applies on the branch (the module is on for it).
     */
    public function enforced(Branch $branch): bool
    {
        return $this->modules->enabled($branch, PosModule::DAILY_MENU);
    }

    /**
     * The product ids of the day's menu, in its order.
     *
     * @return list<int>
     */
    public function productIds(DailyMenu $menu): array
    {
        return $menu->items()->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Rotating dishes of the branch's store that are NOT on today's menu: what checkout and the menus treat as stopped.
     * Empty without the module or without a menu for the day.
     *
     * @return list<int>
     */
    public function offMenuProductIds(Branch $branch, ?string $date = null): array
    {
        $date ??= $this->today($branch);
        if (!$this->enforced($branch)) {
            return [];
        }
        $menu = $this->find($branch, $date);
        if ($menu === null) {
            return [];
        }

        return Product::query()
            ->where('store_id', $branch->store_id)
            ->where('daily_rotation', true)
            ->whereNotIn('id', DailyMenuItem::query()->where('daily_menu_id', $menu->id)->select('product_id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The section of GET /pos/bootstrap.
     *
     * @return array{business_date: string, enforced: bool, set: bool, product_ids: list<int>}
     */
    public function summary(Branch $branch): array
    {
        $date = $this->today($branch);
        $menu = $this->find($branch, $date);

        return [
            'business_date' => $date,
            'enforced' => $this->enforced($branch),
            'set' => $menu !== null,
            'product_ids' => $menu !== null ? $this->productIds($menu) : [],
        ];
    }

    /**
     * Saves the day's menu: exactly these dishes (an empty list is a menu with nothing on it). Every dish on it becomes a
     * rotating one. Audited; the menus are told when the change is for the current business day.
     *
     * @param  list<int>  $productIds
     */
    public function save(Branch $branch, string $date, array $productIds, ?User $actor): DailyMenu
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $this->assertProducts($branch, $productIds);

        $changed = [];
        $menu = DB::transaction(function () use ($branch, $date, $productIds, $actor, &$changed) {
            $menu = DailyMenu::query()->where('branch_id', $branch->id)->whereDate('menu_date', $date)->lockForUpdate()->first();
            $created = $menu === null;
            $before = $created ? [] : $this->productIds($menu);

            if ($created) {
                $menu = DailyMenu::create(['store_id' => $branch->store_id, 'branch_id' => $branch->id, 'menu_date' => $date, 'created_by' => $actor?->id, 'updated_by' => $actor?->id]);
            } else {
                $menu->forceFill(['updated_by' => $actor?->id])->save();
            }

            DailyMenuItem::query()->where('daily_menu_id', $menu->id)->whereNotIn('product_id', $productIds)->delete();
            $existing = DailyMenuItem::query()->where('daily_menu_id', $menu->id)->pluck('id', 'product_id');
            foreach ($productIds as $position => $productId) {
                if ($existing->has($productId)) {
                    DailyMenuItem::query()->whereKey($existing[$productId])->update(['sort_order' => $position]);
                } else {
                    DailyMenuItem::create(['daily_menu_id' => $menu->id, 'product_id' => $productId, 'sort_order' => $position]);
                }
            }

            // A dish on a daily menu is a rotating one from now on
            Product::query()->whereIn('id', $productIds)->where('daily_rotation', false)->update(['daily_rotation' => true]);

            $changed = array_values(array_unique(array_merge(array_diff($before, $productIds), array_diff($productIds, $before))));
            $this->audit->record('menu.daily_menu_saved', $menu, ['product_ids' => $before], ['product_ids' => $productIds], [
                'branch' => $branch->name,
                'date' => $date,
                'created' => $created,
            ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

            return $menu;
        });

        $this->announce($branch, $date, $changed);

        return $menu;
    }

    /**
     * "Kechagisini nusxalash": the dishes of $from become the menu of $to (the whole menu is replaced). Without $from the
     * latest menu before $to is used; 422 when the branch has none.
     */
    public function copy(Branch $branch, string $to, ?string $from, ?User $actor): DailyMenu
    {
        $source = $from !== null
            ? $this->find($branch, $from)
            : DailyMenu::query()->where('branch_id', $branch->id)->whereDate('menu_date', '<', $to)->orderByDesc('menu_date')->first();
        if ($source === null) {
            throw ValidationException::withMessages(['from_date' => $from !== null
                ? "Bu sanada menyu topilmadi: {$from}."
                : "Nusxalash uchun oldingi menyu yo'q."]);
        }

        // Dishes deleted or switched off since then are left out
        $ids = Product::query()->where('store_id', $branch->store_id)->where('status', 'active')->whereIn('id', $this->productIds($source))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ordered = array_values(array_filter($this->productIds($source), fn (int $id) => in_array($id, $ids, true)));

        return $this->save($branch, $to, $ordered, $actor);
    }

    /**
     * Which dishes rotate (the store's choice, whatever day it is): exactly these. A dish taken off keeps its place on
     * old menus but is on sale every day again.
     *
     * @param  list<int>  $productIds
     * @return list<int>
     */
    public function setRotation(int $storeId, array $productIds, ?User $actor): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $valid = Product::query()->where('store_id', $storeId)->whereIn('id', $productIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($valid) !== count($productIds)) {
            throw ValidationException::withMessages(['product_ids' => 'Mahsulot bu do\'konga tegishli emas.']);
        }

        $before = Product::query()->where('store_id', $storeId)->where('daily_rotation', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        DB::transaction(function () use ($storeId, $productIds) {
            Product::query()->where('store_id', $storeId)->where('daily_rotation', true)->whereNotIn('id', $productIds)->update(['daily_rotation' => false]);
            Product::query()->where('store_id', $storeId)->whereIn('id', $productIds)->where('daily_rotation', false)->update(['daily_rotation' => true]);
        });

        $after = collect($productIds)->sort()->values()->all();
        $this->audit->record('menu.daily_rotation_saved', null, ['product_ids' => $before], ['product_ids' => $after], [], ['actor' => $actor, 'store_id' => $storeId]);

        foreach (Branch::query()->where('store_id', $storeId)->get(['id', 'store_id', 'business_day_starts_at', 'pos_settings']) as $branch) {
            $this->announce($branch, $this->today($branch), array_values(array_unique(array_merge(array_diff($before, $after), array_diff($after, $before)))));
        }

        return $after;
    }

    /**
     * @param  list<int>  $productIds
     */
    private function assertProducts(Branch $branch, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }
        $valid = Product::query()->where('store_id', $branch->store_id)->whereIn('id', $productIds)->count();
        if ($valid !== count($productIds)) {
            throw ValidationException::withMessages(['product_ids' => "Mahsulot bu do'konga tegishli emas."]);
        }
    }

    /**
     * Tells the menus that dishes came on or went off today's menu (menu.availability_changed, reason `daily_menu`).
     *
     * @param  list<int>  $productIds  the dishes whose state changed
     */
    private function announce(Branch $branch, string $date, array $productIds): void
    {
        if ($productIds === [] || $date !== $this->today($branch) || !$this->enforced($branch)) {
            return;
        }

        $off = array_flip($this->offMenuProductIds($branch, $date));
        event(new MenuAvailabilityChangedBroadcast((int) $branch->store_id, (int) $branch->id, array_map(fn (int $id) => [
            'type' => 'product',
            'id' => $id,
            'available' => !isset($off[$id]),
        ], $productIds), 'daily_menu'));
    }
}
