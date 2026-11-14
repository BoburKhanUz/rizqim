<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Banquet\BanquetCatalog;
use App\Domain\Pos\Banquet\BanquetExtraPricing;
use App\Domain\Pos\Banquet\BanquetSettings;
use App\Enums\Permission;
use App\Models\BanquetExtra;
use App\Models\BanquetPackage;
use App\Models\Hall;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-51/P6-52: the owner's banquet setup (partner web "Tadbirlar" → paketlar, qo'shimchalar, zal narxlari,
 * sozlamalar; docs/API.md "Tadbirlar"): packages with what they include and their menu items per person, the extras
 * catalogue, the halls' rent per slot and the store's banquet settings (slot times, hold hours, guest and time
 * warnings, MXIK codes). Reading and writing with banquets.manage (a web sign-in, never a terminal PIN session); every
 * change audited. Another store's package, extra or hall: 404.
 */
#[Group('Owner: banquets', "Banket sozlamalari: paketlar, qo'shimchalar, zal ijarasi, vaqtlar va ogohlantirishlar (P6-51, P6-52).")]
class BanquetSetupController extends ManagementController
{
    /**
     * Banquet settings
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetSettings::VIEW . '}')]
    public function settings(Request $request): JsonResponse
    {
        return $this->success(app(BanquetSettings::class)->view((int) $this->currentStore($request)->id));
    }

    /**
     * Change the banquet settings
     *
     * Only the fields sent change. `slots`: {morning|day|evening: {start, end}} ("HH:MM", an end before the start is
     * the next day); `hold_hours` (0: a hold never expires by itself); `guest_warning_thresholds` and the time window
     * are warnings, never refusals; the MXIK codes of the advance receipt line and of the cancellation fee (the
     * accountant confirms them). Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetSettings::VIEW . '}')]
    public function updateSettings(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate([
            'slots' => 'sometimes|array:morning,day,evening',
            'slots.*.start' => 'required|date_format:H:i',
            'slots.*.end' => 'required|date_format:H:i',
            'hold_hours' => 'sometimes|integer|min:0|max:2160',
            'guest_warning_thresholds' => 'sometimes|nullable|array|max:5',
            'guest_warning_thresholds.*' => 'integer|min:1|max:100000',
            'window_start' => 'sometimes|date_format:H:i',
            'window_end' => 'sometimes|date_format:H:i',
            'advance_mxik' => 'sometimes|nullable|string|regex:/^\d{17}$/',
            'advance_package_code' => 'sometimes|nullable|string|max:32',
            'cancellation_mxik' => 'sometimes|nullable|string|regex:/^\d{17}$/',
            'cancellation_package_code' => 'sometimes|nullable|string|max:32',
            'quote_currency' => 'sometimes|nullable|string|size:3|alpha|uppercase',
        ]);
        $settings = app(BanquetSettings::class);
        $settings->update((int) $store->id, $data, $request->user());

        return $this->success($settings->view((int) $store->id), 'Sozlamalar saqlandi.');
    }

    /**
     * Banquet packages
     *
     * Every package of the store (archived ones too), with what they include and their menu items.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{packages: list<' . BanquetCatalog::PACKAGE . '>}}')]
    public function packages(Request $request): JsonResponse
    {
        $catalog = app(BanquetCatalog::class);

        return $this->success([
            'packages' => BanquetPackage::where('store_id', $this->currentStore($request)->id)->with('items')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (BanquetPackage $package) => $catalog->packageView($package))->values()->all(),
        ]);
    }

    /**
     * Create a banquet package
     *
     * `per_person_price` (so'm), `minimum_guests`, `included` (free text lines), `items`: menu items per person
     * (`product_variant_id` or `product_id` of the store, or a free `name`; `quantity_per_person`, `unit`).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . BanquetCatalog::PACKAGE . '}')]
    public function storePackage(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'per_person_price' => 'required|numeric|min:0|max:9999999999|decimal:0,2',
            'minimum_guests' => 'nullable|integer|min:0|max:10000',
            'included' => 'nullable|array|max:50',
            'included.*' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'items' => 'nullable|array|max:100',
            'items.*.product_id' => 'nullable|integer|min:1',
            'items.*.product_variant_id' => 'nullable|integer|min:1',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.quantity_per_person' => 'required|numeric|min:0.001|max:1000|decimal:0,3',
            'items.*.unit' => ['nullable', 'string', Rule::in(['portion', 'piece', 'kg', 'l'])],
        ]);
        $data = array_filter($data, fn ($value) => $value !== null);
        $package = app(BanquetCatalog::class)->savePackage((int) $store->id, null, $data, $request->user());

        return $this->success(app(BanquetCatalog::class)->packageView($package->refresh()), 'Paket saqlandi.', 201);
    }

    /**
     * Change a banquet package
     *
     * Only the fields sent change (`items` replaces the menu items). Events with frozen prices keep theirs.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetCatalog::PACKAGE . '}')]
    public function updatePackage(Request $request, string $package): JsonResponse
    {
        $store = $this->currentStore($request);
        $model = $this->findPackage((int) $store->id, $package);
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'per_person_price' => 'sometimes|numeric|min:0|max:9999999999|decimal:0,2',
            'minimum_guests' => 'sometimes|integer|min:0|max:10000',
            'included' => 'sometimes|nullable|array|max:50',
            'included.*' => 'nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
            'items' => 'sometimes|nullable|array|max:100',
            'items.*.product_id' => 'nullable|integer|min:1',
            'items.*.product_variant_id' => 'nullable|integer|min:1',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.quantity_per_person' => 'required|numeric|min:0.001|max:1000|decimal:0,3',
            'items.*.unit' => ['nullable', 'string', Rule::in(['portion', 'piece', 'kg', 'l'])],
        ]);
        $model = app(BanquetCatalog::class)->savePackage((int) $store->id, $model, $data, $request->user());

        return $this->success(app(BanquetCatalog::class)->packageView($model->refresh()), 'Paket saqlandi.');
    }

    /**
     * Delete a banquet package
     *
     * A package an event uses is archived (inactive) instead (`data.deleted` false).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{deleted: bool}}')]
    public function destroyPackage(Request $request, string $package): JsonResponse
    {
        $model = $this->findPackage((int) $this->currentStore($request)->id, $package);
        $deleted = app(BanquetCatalog::class)->deletePackage($model, $request->user());

        return $this->success(['deleted' => $deleted], $deleted ? "Paket o'chirildi." : 'Paket tadbirlarda ishlatilgan: arxivga olindi.');
    }

    /**
     * Banquet extras
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{extras: list<' . BanquetCatalog::EXTRA . '>}}')]
    public function extras(Request $request): JsonResponse
    {
        $catalog = app(BanquetCatalog::class);

        return $this->success([
            'extras' => BanquetExtra::where('store_id', $this->currentStore($request)->id)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (BanquetExtra $extra) => $catalog->extraView($extra))->values()->all(),
        ]);
    }

    /**
     * Create a banquet extra
     *
     * `pricing`: per_event (price × quantity) or per_person (price × quantity × the billed guests).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . BanquetCatalog::EXTRA . '}')]
    public function storeExtra(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0|max:9999999999|decimal:0,2',
            'pricing' => ['required', 'string', Rule::enum(BanquetExtraPricing::class)],
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);
        $extra = app(BanquetCatalog::class)->saveExtra((int) $store->id, null, array_filter($data, fn ($value) => $value !== null), $request->user());

        return $this->success(app(BanquetCatalog::class)->extraView($extra), "Qo'shimcha saqlandi.", 201);
    }

    /**
     * Change a banquet extra
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetCatalog::EXTRA . '}')]
    public function updateExtra(Request $request, string $extra): JsonResponse
    {
        $store = $this->currentStore($request);
        $model = $this->findExtra((int) $store->id, $extra);
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'price' => 'sometimes|numeric|min:0|max:9999999999|decimal:0,2',
            'pricing' => ['sometimes', 'string', Rule::enum(BanquetExtraPricing::class)],
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ]);
        $model = app(BanquetCatalog::class)->saveExtra((int) $store->id, $model, $data, $request->user());

        return $this->success(app(BanquetCatalog::class)->extraView($model), "Qo'shimcha saqlandi.");
    }

    /**
     * Delete a banquet extra
     *
     * An extra an event uses is archived (inactive) instead (`data.deleted` false).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{deleted: bool}}')]
    public function destroyExtra(Request $request, string $extra): JsonResponse
    {
        $model = $this->findExtra((int) $this->currentStore($request)->id, $extra);
        $deleted = app(BanquetCatalog::class)->deleteExtra($model, $request->user());

        return $this->success(['deleted' => $deleted], $deleted ? "Qo'shimcha o'chirildi." : "Qo'shimcha tadbirlarda ishlatilgan: arxivga olindi.");
    }

    /**
     * Halls and their rent per slot
     */
    #[QueryParameter('branch_id', 'One branch (default: every branch of the store).', type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{halls: list<' . BanquetCatalog::HALL . '>}}')]
    public function halls(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $branch = $this->branchFilter($request, Permission::BANQUETS_MANAGE);

        return $this->success(['halls' => app(BanquetCatalog::class)->halls((int) $store->id, $branch !== null ? (int) $branch->id : null)]);
    }

    /**
     * Set a hall's rent per slot
     *
     * `rates`: {morning|day|evening|custom: price or null (no rent)}. Only the slots sent change; audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetCatalog::HALL . '}')]
    public function updateHallRates(Request $request, string $hall): JsonResponse
    {
        $store = $this->currentStore($request);
        $hallId = RowId::parse($hall);
        $model = $hallId !== null ? Hall::where('store_id', $store->id)->find($hallId) : null;
        if ($model === null) {
            throw new HttpResponseException($this->error('Zal topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::BANQUETS_MANAGE, $model);
        $data = $request->validate([
            'rates' => 'required|array:morning,day,evening,custom',
            'rates.*' => 'nullable|numeric|min:0|max:9999999999|decimal:0,2',
        ]);
        $catalog = app(BanquetCatalog::class);
        $catalog->saveHallRates($model, array_map(fn ($price) => $price !== null ? (string) $price : null, $data['rates']), $request->user());
        $row = collect($catalog->halls((int) $store->id, (int) $model->branch_id))->firstWhere('hall_id', (int) $model->id);

        return $this->success($row, 'Zal narxlari saqlandi.');
    }

    private function findPackage(int $storeId, string $id): BanquetPackage
    {
        $packageId = RowId::parse($id);
        $package = $packageId !== null ? BanquetPackage::where('store_id', $storeId)->find($packageId) : null;
        if ($package === null) {
            throw new HttpResponseException($this->error('Paket topilmadi.', [], 404));
        }

        return $package;
    }

    private function findExtra(int $storeId, string $id): BanquetExtra
    {
        $extraId = RowId::parse($id);
        $extra = $extraId !== null ? BanquetExtra::where('store_id', $storeId)->find($extraId) : null;
        if ($extra === null) {
            throw new HttpResponseException($this->error("Qo'shimcha topilmadi.", [], 404));
        }

        return $extra;
    }
}
