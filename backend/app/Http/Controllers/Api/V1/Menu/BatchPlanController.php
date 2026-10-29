<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Menu\BatchPlans;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Models\MenuBatchPlan;
use App\Models\ProductVariant;
use App\Support\Quantity;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Owner: batches', "Qozon / partiya qoldig'i: ochish, to'ldirish, tuzatish, yopish (P1-21); keyingi partiya vaqti va hisobdan chiqarish (P6-15). Oshpaz, menejer, ega.")]
class BatchPlanController extends MenuStaffController
{
    /**
     * A branch's planned batches
     *
     * P6-15, module `batches`: the "keyingi partiya HH:MM" plans still `planned` (a plan whose time has passed is
     * `overdue` and no longer shown to guests), earliest first, and `modules` (`batches`, `batch_writeoff`: which of the
     * batch features the branch's format has, so a screen shows the write-off button only where it works). Branch-bound
     * staff always get their own branch.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    public function index(Request $request, BatchPlans $plans, PosModules $modules): JsonResponse
    {
        $permission = $this->permissions->has($request->user(), Permission::BATCHES_MANAGE) ? Permission::BATCHES_MANAGE : Permission::MENU_VIEW;
        $branch = $this->branch($request, $permission);
        $zone = $plans->zones([(int) $branch->store_id])[(int) $branch->store_id];

        $rows = MenuBatchPlan::where('branch_id', $branch->id)
            ->planned()
            ->where('planned_at', '>=', now()->subHours(12))
            ->with(['product:id,name', 'variant:id,name'])
            ->orderBy('planned_at')
            ->orderBy('id')
            ->get();

        return $this->success([
            'branch_id' => (int) $branch->id,
            'modules' => [
                'batches' => $modules->enabled($branch, PosModule::BATCHES),
                'batch_writeoff' => $modules->enabled($branch, PosModule::BATCH_WRITEOFF),
            ],
            'plans' => $rows->map(fn (MenuBatchPlan $plan) => $this->present($plans, $plan, $zone))->values(),
        ]);
    }

    /**
     * Plan the next batch
     *
     * `time` is "HH:MM" today in the store's time zone (it must not have passed), or `planned_at` an ISO moment (at most
     * 36 hours ahead). `product_variant_id` null: the plan serves every variant. Where the dish is "tugadi", the till and
     * the Mini App then show "keyingi partiya 12:30". Opening a batch of that dish finishes the plan.
     */
    public function store(Request $request, BatchPlans $plans): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'product_id' => 'required|integer',
            'product_variant_id' => 'nullable|integer',
            'time' => 'nullable|required_without:planned_at|string|date_format:H:i',
            'planned_at' => 'nullable|required_without:time|date',
            'quantity' => 'nullable|numeric|gt:0|max:999999',
            'label' => 'nullable|string|max:100',
        ]);

        $branch = $this->branch($request, Permission::BATCHES_MANAGE);
        $product = $this->product($request, (int) $data['product_id']);
        $variant = isset($data['product_variant_id']) ? ProductVariant::where('product_id', $product->id)->find((int) $data['product_variant_id']) : null;
        if (isset($data['product_variant_id']) && !$variant) {
            throw ValidationException::withMessages(['product_variant_id' => 'Variant bu mahsulotga tegishli emas.']);
        }

        $at = isset($data['planned_at']) ? \Illuminate\Support\Carbon::parse($data['planned_at']) : $plans->momentOf($branch, (string) $data['time']);
        $quantity = isset($data['quantity']) ? Quantity::tryOf($data['quantity']) : null;
        if (isset($data['quantity']) && $quantity === null) {
            throw ValidationException::withMessages(['quantity' => "Miqdor ko'pi bilan 3 ta kasr xonali son bo'lishi kerak."]);
        }

        $plan = $plans->plan($branch, $product, $variant, $at, $quantity, $data['label'] ?? null, $request->user());
        $zone = $plans->zones([(int) $branch->store_id])[(int) $branch->store_id];

        return $this->success($this->present($plans, $plan->load(['product:id,name', 'variant:id,name']), $zone), 'Keyingi partiya rejalashtirildi.', 201);
    }

    /**
     * Cancel a planned batch
     *
     * The time is no longer shown to guests. A plan already started (its batch opened) stays as it is.
     */
    public function destroy(Request $request, int $id, BatchPlans $plans): JsonResponse
    {
        $plan = MenuBatchPlan::where('store_id', $this->storeId($request))->find($id);
        if (!$plan) {
            throw new HttpResponseException($this->error('Reja topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::BATCHES_MANAGE, $plan);

        $plans->cancel($plan, $request->user());

        return $this->success(null, 'Reja bekor qilindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BatchPlans $plans, MenuBatchPlan $plan, string $zone): array
    {
        return $plans->present($plan, $zone) + [
            'branch_id' => (int) $plan->branch_id,
            'product_name' => $plan->product?->name,
            'variant_name' => $plan->variant?->name,
            'status' => $plan->status,
            'overdue' => $plan->status === MenuBatchPlan::PLANNED && $plan->planned_at->isPast(),
        ];
    }
}
