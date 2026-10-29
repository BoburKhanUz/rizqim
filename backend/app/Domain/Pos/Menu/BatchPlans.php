<?php

namespace App\Domain\Pos\Menu;

use App\Domain\Pos\Shifts\BusinessDay;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\Branch;
use App\Models\MenuBatch;
use App\Models\MenuBatchPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Quantity;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * "Keyingi partiya HH:MM" (P6-15): when a branch plans to cook a product next. The plan is shown wherever the dish is
 * "tugadi" (POS bootstrap `next_batches`, the POS catalog's and the public menu availability's `next_batches`, the Mini App
 * card) and moves no stock. Opening the batch of that product finishes the earliest matching plan (started());
 * a plan whose time has passed without a batch simply stops being shown (it stays `planned` in the owner's list,
 * `overdue`).
 */
class BatchPlans
{
    /** Plans further ahead than this are refused: "keyingi partiya" is today's or tomorrow's. */
    public const MAX_AHEAD_HOURS = 36;

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * Plans the next batch. `$at` is an absolute moment (it must not be in the past, nor further than MAX_AHEAD_HOURS).
     */
    public function plan(Branch $branch, Product $product, ?ProductVariant $variant, Carbon $at, ?Quantity $quantity, ?string $label, ?User $actor): MenuBatchPlan
    {
        if ((int) $product->store_id !== (int) $branch->store_id || ($variant !== null && (int) $variant->product_id !== (int) $product->id)) {
            throw ValidationException::withMessages(['product_id' => "Mahsulot bu filial do'koniga tegishli emas."]);
        }
        // Stored in the application's time zone whatever zone the moment came in (the column has no zone)
        $at = $at->copy()->setTimezone((string) config('app.timezone'));
        if ($at->lt(now()->subMinute()) || $at->gt(now()->addHours(self::MAX_AHEAD_HOURS))) {
            throw ValidationException::withMessages(['time' => "Keyingi partiya vaqti hozirdan keyin, ko'pi bilan " . self::MAX_AHEAD_HOURS . ' soat ichida bo\'lishi kerak.']);
        }
        if ($quantity !== null && !$quantity->isPositive()) {
            throw ValidationException::withMessages(['quantity' => "Miqdor noldan katta bo'lishi kerak."]);
        }

        $plan = MenuBatchPlan::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'planned_at' => $at,
            'business_date' => BusinessDay::of($branch, $at),
            'planned_quantity' => $quantity?->toString(),
            'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 100) : null,
            'status' => MenuBatchPlan::PLANNED,
            'created_by' => $actor?->id,
        ]);

        $this->audit->record('menu.batch_planned', $plan, [], [], [
            'product' => $product->name,
            'variant' => $variant?->name,
            'planned_at' => $at->toIso8601String(),
            'quantity' => $quantity?->toNumber(),
        ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        $this->announce($plan);

        return $plan;
    }

    /**
     * "12:30" in the store's time zone: today's date there, so an hour that has already gone by is refused (plan()).
     */
    public function momentOf(Branch $branch, string $time): Carbon
    {
        $store = Store::find($branch->store_id);
        $zone = StoreTimezone::of($store);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $match)) {
            throw ValidationException::withMessages(['time' => "Vaqt HH:MM ko'rinishida bo'lishi kerak."]);
        }

        $local = CarbonImmutable::now($zone)->setTime((int) $match[1], (int) $match[2]);

        return Carbon::instance($local);
    }

    public function cancel(MenuBatchPlan $plan, ?User $actor): MenuBatchPlan
    {
        if ($plan->status !== MenuBatchPlan::PLANNED) {
            return $plan;
        }
        $plan->forceFill(['status' => MenuBatchPlan::CANCELED])->save();

        $this->audit->record('menu.batch_plan_canceled', $plan, [], [], [
            'product_id' => (int) $plan->product_id,
            'planned_at' => $plan->planned_at->toIso8601String(),
        ], ['actor' => $actor, 'store_id' => (int) $plan->store_id, 'branch_id' => (int) $plan->branch_id]);

        $this->announce($plan);

        return $plan;
    }

    /**
     * The batch was opened: the earliest plan for that product (and variant, or any when one side names none) that is
     * still planned and not stale is done. Called inside BatchService::open's transaction.
     */
    public function started(MenuBatch $batch): void
    {
        $plan = MenuBatchPlan::query()
            ->where('branch_id', $batch->branch_id)
            ->where('product_id', $batch->product_id)
            ->planned()
            ->where('planned_at', '>=', now()->subHours(8))
            ->where(function ($query) use ($batch) {
                $query->whereNull('product_variant_id');
                if ($batch->product_variant_id !== null) {
                    $query->orWhere('product_variant_id', $batch->product_variant_id);
                }
            })
            ->orderBy('planned_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($plan !== null) {
            $plan->forceFill(['status' => MenuBatchPlan::STARTED, 'menu_batch_id' => $batch->id])->save();
        }
    }

    /**
     * The plans still ahead on the branches (what the menus show), earliest first. Each is `{id, uuid, product_id,
     * product_variant_id, planned_at, time (HH:MM in the store's zone), quantity, label}`.
     *
     * @param  list<int>  $branchIds
     * @return array<int, list<array<string, mixed>>> branch id => plans
     */
    public function upcoming(array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        $plans = MenuBatchPlan::query()
            ->whereIn('branch_id', $branchIds)
            ->planned()
            ->where('planned_at', '>', now())
            ->where('planned_at', '<=', now()->addHours(self::MAX_AHEAD_HOURS))
            ->orderBy('planned_at')
            ->orderBy('id')
            ->get();
        if ($plans->isEmpty()) {
            return [];
        }

        $zones = $this->zones($plans->pluck('store_id')->unique()->values()->all());

        return $plans->groupBy('branch_id')
            ->map(fn (Collection $rows) => $rows->map(fn (MenuBatchPlan $plan) => $this->present($plan, $zones[(int) $plan->store_id] ?? StoreTimezone::orDefault(null)))->values()->all())
            ->mapWithKeys(fn (array $rows, $branchId) => [(int) $branchId => $rows])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(MenuBatchPlan $plan, string $zone): array
    {
        return [
            'id' => (int) $plan->id,
            'uuid' => $plan->uuid,
            'product_id' => (int) $plan->product_id,
            'product_variant_id' => $plan->product_variant_id !== null ? (int) $plan->product_variant_id : null,
            'planned_at' => $plan->planned_at->toJSON(),
            'time' => $plan->planned_at->copy()->setTimezone($zone)->format('H:i'),
            'quantity' => $plan->planned_quantity !== null ? Quantity::normalize($plan->planned_quantity) : null,
            'label' => $plan->label,
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<int, string> store id => time zone
     */
    public function zones(array $storeIds): array
    {
        $zones = [];
        foreach (Store::query()->whereIn('id', $storeIds)->get(['id', 'timezone']) as $store) {
            $zones[(int) $store->id] = StoreTimezone::of($store);
        }

        return $zones;
    }

    private function announce(MenuBatchPlan $plan): void
    {
        // Nothing became (un)available: the menus refetch to show or hide the time (reason `batch_plan`, no items)
        event(new MenuAvailabilityChangedBroadcast((int) $plan->store_id, (int) $plan->branch_id, [], 'batch_plan'));
    }
}
