<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Menu\DailyMenuService;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\DailyMenu;
use App\Models\DailySet;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Owner: daily menu', "Bugungi menyu (stolovaya): kunlik taomlar ro'yxati, kechagisini nusxalash va komplekslar (P6-15).")]
class DailyMenuController extends MenuStaffController
{
    /** The menu can be set this many days ahead. */
    private const MAX_AHEAD_DAYS = 30;

    /**
     * A branch's menu for a day
     *
     * P6-15, module `daily_menu`: the dishes on the branch's menu for `date` (YYYY-MM-DD, default today's business
     * day), `set` false when none was made for that day (then every dish is on sale), and every active dish of the store
     * with `daily_rotation` (on THIS branch it is on sale only while on the day's menu) and `on_menu`. `previous_date` is the latest
     * earlier day with a menu, for "kechagisini nusxalash". menu.view or menu.manage.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    #[QueryParameter('date', description: "Business day, YYYY-MM-DD (default: the branch's today).", type: 'string')]
    public function show(Request $request, DailyMenuService $menus): JsonResponse
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $permission = $this->permissions->has($request->user(), Permission::MENU_MANAGE) ? Permission::MENU_MANAGE : Permission::MENU_VIEW;
        $branch = $this->branch($request, $permission);

        return $this->success($this->present($menus, $branch, $data['date'] ?? $menus->today($branch)));
    }

    /**
     * Set a branch's menu for a day
     *
     * P6-15, module `daily_menu`: exactly these dishes (`product_ids`, in the order given; an empty list is a menu with
     * nothing on it). Every dish on a menu becomes a rotating one. `date` is today's business day up to 30 days ahead.
     * menu.manage, or stoplist.manage for the manager who runs the day. Audited (`menu.daily_menu_saved`); the menus are told when it is today's.
     */
    public function save(Request $request, DailyMenuService $menus): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'date' => 'nullable|date_format:Y-m-d',
            'product_ids' => 'present|array|max:500',
            'product_ids.*' => 'integer|distinct',
        ]);
        $branch = $this->branch($request, $this->writePermission($request));
        $date = $this->writableDate($menus, $branch, $data['date'] ?? null);

        $menus->save($branch, $date, array_map('intval', $data['product_ids']), $request->user());

        return $this->success($this->present($menus, $branch, $date), 'Menyu saqlandi.');
    }

    /**
     * Copy a day's menu
     *
     * P6-15, module `daily_menu`: the dishes of `from_date` (default: the latest earlier day with a menu) become the
     * menu of `date` (default today); a menu already there is replaced. Dishes deleted or switched off since are left
     * out. 422 when there is nothing to copy.
     */
    public function copy(Request $request, DailyMenuService $menus): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'date' => 'nullable|date_format:Y-m-d',
            'from_date' => 'nullable|date_format:Y-m-d',
        ]);
        $branch = $this->branch($request, $this->writePermission($request));
        $date = $this->writableDate($menus, $branch, $data['date'] ?? null);
        if (($data['from_date'] ?? null) === $date) {
            throw ValidationException::withMessages(['from_date' => "Menyuni o'zidan nusxalab bo'lmaydi."]);
        }

        $menus->copy($branch, $date, $data['from_date'] ?? null, $request->user());

        return $this->success($this->present($menus, $branch, $date), 'Menyu nusxalandi.');
    }

    /**
     * Which dishes rotate on a branch
     *
     * P6-15, module `daily_menu`, per branch like the menu: exactly these dishes (`product_ids`) are sold on the branch
     * only while on the day's menu; the others are on sale every day (bread, tea). Dishes put on a menu become rotating
     * on that branch by themselves; this is how an owner makes one permanent again. Other branches are never touched.
     * menu.manage (any branch), or stoplist.manage for the manager of that branch only. Audited (`menu.daily_rotation_saved`).
     */
    public function rotation(Request $request, DailyMenuService $menus): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'product_ids' => 'present|array|max:1000',
            'product_ids.*' => 'integer|distinct',
        ]);
        $branch = $this->branch($request, $this->writePermission($request));

        $ids = $menus->setRotation($branch, array_map('intval', $data['product_ids']), $request->user());

        return $this->success(['branch_id' => (int) $branch->id, 'product_ids' => $ids], 'Saqlandi.');
    }

    /**
     * The menu's owner (menu.manage) or the manager who runs the day (stoplist.manage): setting the day's dishes is
     * the stop-list's daily counterpart, not a change of the menu itself.
     */
    private function writePermission(Request $request): Permission
    {
        return $this->permissions->has($request->user(), Permission::MENU_MANAGE) ? Permission::MENU_MANAGE : Permission::STOPLIST_MANAGE;
    }

    private function writableDate(DailyMenuService $menus, Branch $branch, ?string $date): string
    {
        $today = $menus->today($branch);
        $date ??= $today;
        if ($date < $today || CarbonImmutable::parse($today)->diffInDays(CarbonImmutable::parse($date)) > self::MAX_AHEAD_DAYS) {
            throw ValidationException::withMessages(['date' => "Menyu bugundan boshlab " . self::MAX_AHEAD_DAYS . ' kun oldinga qo\'yiladi.']);
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DailyMenuService $menus, Branch $branch, string $date): array
    {
        $menu = $menus->find($branch, $date);
        $onMenu = $menu !== null ? array_flip($menus->productIds($menu)) : [];
        $sets = DailySet::where('store_id', $branch->store_id)->pluck('product_id')->flip();

        $rotating = array_flip($menus->rotatingIds($branch));
        $products = Product::query()
            ->with('category:id,name')
            ->where('store_id', $branch->store_id)
            ->where('status', 'active')
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'category_id', 'sort_order']);

        $previous = DailyMenu::where('branch_id', $branch->id)->whereDate('menu_date', '<', $date)->orderByDesc('menu_date')->first();

        return [
            'branch_id' => (int) $branch->id,
            'date' => $date,
            'business_date' => $menus->today($branch),
            'enforced' => $menus->enforced($branch),
            'set' => $menu !== null,
            'product_ids' => $menu !== null ? $menus->productIds($menu) : [],
            'previous_date' => $previous?->menu_date->format('Y-m-d'),
            'products' => $products->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'category_id' => (int) $product->category_id,
                'category_name' => $product->category?->name,
                'daily_rotation' => isset($rotating[(int) $product->id]),
                'on_menu' => isset($onMenu[(int) $product->id]),
                'is_set' => $sets->has($product->id),
            ])->values(),
        ];
    }
}
