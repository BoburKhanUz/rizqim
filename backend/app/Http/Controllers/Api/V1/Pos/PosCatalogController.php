<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Setup\PosCatalog;
use App\Enums\OrderChannel;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Auth\PermissionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The POS channels of GET /catalog (`channel=pos_hall|pos_takeaway`, P3-13): the branch menu for the store's own
 * staff. CatalogController hands these channels over here, so a customer (or another store's staff) asking for a POS
 * channel gets 403/404 and never a POS price list, and the customer shapes of telegram/mobile stay as they were.
 */
#[Group('POS: bootstrap')]
class PosCatalogController extends MenuStaffController
{
    private const MODIFIER = 'Modifier&array{is_available: bool, unavailable_reason: string|null}';

    private const VARIANT = 'ProductVariant&array{price: string, base_price: string, is_available: bool, unavailable_reason: \'stopped\'|\'sold_out\'|null}';

    private const PRODUCT = 'Product&array{batch_tracked: bool, quick_add: bool, quick_multipliers: list<int>|null, wait_minutes: int|null, weight_mode: string|null, is_available: bool, unavailable_reason: \'stopped\'|\'sold_out\'|null, station_id: int|null, variants: list<' . self::VARIANT . '>, modifier_groups: list<ModifierGroup&array{min_select: int, max_select: int|null, is_required: bool, sort_order: int, modifiers: list<' . self::MODIFIER . '>}>}';

    private const AVAILABILITY = 'array{branch_id: int, stopped: array{products: list<int>, variants: list<int>, modifiers: list<int>}, sold_out: array{variants: list<int>}, stop_list: list<array{id: int|null, type: string, subject_id: int, reason: string|null, until: string|null}>, batches: list<array{id: int, uuid: string, product_id: int, product_variant_id: int|null, label: string|null, quantity_produced: int|float|null, quantity_left: int|float|null, started_at: string}>}';

    /** The POS menu of one branch (GET /pos/catalog, and GET /catalog?channel=pos_*). */
    public const CATALOG = 'array{channel: \'pos_hall\'|\'pos_takeaway\', menu_version: string, branch: array{id: int, name: string}, categories: list<Category>, products: list<' . self::PRODUCT . '>, availability: ' . self::AVAILABILITY . '}';

    public function __construct(PermissionService $permissions, private readonly PosCatalog $catalog)
    {
        parent::__construct($permissions);
    }

    /**
     * Whether `channel` names a POS channel (the only ones this controller answers).
     */
    public static function handles(mixed $channel): bool
    {
        return in_array($channel, [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value], true);
    }

    /**
     * POS menu of a branch
     *
     * P3-26: the typed form of GET /catalog?channel=pos_hall|pos_takeaway (same answer). The branch's store menu on
     * the POS `channel` (default pos_hall) with its prices and hidden dishes applied, each variant's unit, step and
     * minimum, the modifiers, and what is unavailable on the branch now (`is_available`, `unavailable_reason`:
     * stopped | sold_out; `availability`: stop-list and kazan batches); each product's `station_id` (kitchen routing),
     * `batch_tracked` (sold from kazan batches) and `quick_add` (a one-tap button); P6-25: `quick_multipliers` (the ×N
     * buttons of a skewer dish), `wait_minutes` (the grill's time), `weight_mode` (raw|cooked: weighed after it is
     * chosen; null: sold by the quantity typed). Refetch when the bootstrap's
     * `menu_version` changes or on menu.availability_changed. Needs menu.view; `branch_id` for an owner.
     */
    #[QueryParameter('store_id', description: "Optional: the caller's own store (id or uuid); another one is 404.", type: 'string', required: false)]
    #[QueryParameter('channel', description: 'pos_hall (default) or pos_takeaway.', type: "'pos_hall'|'pos_takeaway'", required: false)]
    #[QueryParameter('branch_id', description: 'The branch, for a caller who is not tied to one (the owner, a store-wide terminal).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::CATALOG . '}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'channel' => ['nullable', 'string', 'in:pos_hall,pos_takeaway'],
        ], ['channel.in' => "Kanal noto'g'ri: pos_hall yoki pos_takeaway."]);

        $branch = $this->branch($request, Permission::MENU_VIEW);

        // `store_id` is optional here: the caller's own store is the only one they may read
        $requested = $request->query('store_id');
        if ($requested !== null && $requested !== '') {
            $store = Store::find($branch->store_id);
            if (!$store instanceof Store || !in_array((string) $requested, [(string) $store->id, (string) $store->uuid], true)) {
                throw new HttpResponseException($this->error("Do'kon topilmadi.", [], 404));
            }
        }

        return $this->success($this->catalog->build($branch, OrderChannel::from((string) ($request->query('channel') ?: OrderChannel::POS_HALL->value))));
    }
}
