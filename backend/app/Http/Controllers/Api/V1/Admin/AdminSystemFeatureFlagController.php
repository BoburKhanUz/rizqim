<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Store;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Pos\PosPage;
use App\Services\Admin\System\FeatureFlagAdmin;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-31 Tizim → Funksiyalar (admin.system.manage): the feature flags declared in App\Features\Features, switched per
 * store as the old superadmin page did. Switching and resetting need a step-up and a `reason`.
 */
#[Group('Admin')]
class AdminSystemFeatureFlagController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly FeatureFlagAdmin $flags,
    ) {}

    /**
     * Feature flags
     *
     * Every flag with its description, the default (for a store without its own value) and how many stores have it on
     * and off.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{flags: list<' . FeatureFlagAdmin::FLAG . '>}}')]
    public function index(): JsonResponse
    {
        return $this->success(['flags' => $this->flags->flags()]);
    }

    /**
     * Stores and their flags
     *
     * Pages (`page`, `per_page` 1-100, default 30) of stores by name (`q`: part of the name). For each flag: `active`
     * and `stored` (the store has its own value; false = it follows the default).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . FeatureFlagAdmin::STORE_ROW . '>, ' . PosPage::SHAPE . '}}')]
    public function stores(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->flags->storePage($filters['q'] ?? null, PosPage::perPage($filters['per_page'] ?? null));

        return $this->success(PosPage::of($page, $this->flags->storeRows($page->items())));
    }

    /**
     * Switch a flag for a store
     *
     * `active` (true | false), `reason`. Answers the store's row.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . FeatureFlagAdmin::STORE_ROW . '}')]
    #[ApiDocResponse(404, 'No such flag or store (not_found)', type: self::ERROR)]
    public function set(Request $request, string $flag, int $storeId): JsonResponse
    {
        $data = $request->validate(['active' => 'required|boolean', 'reason' => AdminRules::reason()], UzValidation::messages());
        if (!$this->flags->exists($flag)) {
            return $this->refuse('Bunday funksiya yo\'q.', 'not_found', 404);
        }
        $store = Store::query()->find($storeId);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $active = (bool) $data['active'];
        $before = $this->flags->set($store, $flag, $active);

        // The flag's name goes to the meta, not as a key: a future name could look secret to the redactor
        $audit = AdminAudit::for($request)->subject($store)->store((int) $store->id)->changes(['active' => $before], ['active' => $active])->meta(['flag' => $flag]);
        if ($before === $active) {
            // Already so (a default that is now the store's own value is no change anyone would look for)
            $audit->skip();
        }

        return $this->success($this->flags->storeRows([$store])[0], $active ? 'Funksiya yoqildi.' : "Funksiya o'chirildi.");
    }

    /**
     * Back to the defaults
     *
     * `reason`. Forgets every own value of the store (the old panel's "Standartga qaytarish"). Answers the store's row.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . FeatureFlagAdmin::STORE_ROW . '}')]
    #[ApiDocResponse(404, 'No such store (not_found)', type: self::ERROR)]
    public function reset(Request $request, int $storeId): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $store = Store::query()->find($storeId);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $had = $this->flags->reset($store);
        $audit = AdminAudit::for($request)->subject($store)->store((int) $store->id)->meta(['flags' => $had]);
        if ($had === []) {
            $audit->skip();
        }

        return $this->success($this->flags->storeRows([$store])[0], 'Ovqatxona funksiyalari standartga qaytarildi.');
    }
}
