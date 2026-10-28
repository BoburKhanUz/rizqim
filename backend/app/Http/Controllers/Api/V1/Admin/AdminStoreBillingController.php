<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BillingPeriod;
use App\Enums\CommissionChannel;
use App\Enums\CourierType;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Admin\AdminRules;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;
use App\Services\Admin\AdminAudit;
use App\Services\Finance\Billing\BillingPresenter;
use App\Services\Finance\Billing\CommissionPolicy;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Finance\SubscriptionService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * P4-21: a store's subscription and commission in the Boshqaruv markazi (docs/API.md, "Billing v2"). Reading:
 * admin.subscriptions.manage or admin.finance.view. Every change: admin.subscriptions.manage, a step-up and a
 * `reason`, audited with the state before and after (the service also writes its own subscription.* entries).
 * Stage B (P4-24 POS va obunalar, P4-22 360° card) builds its screens on these.
 */
#[Group('Admin')]
class AdminStoreBillingController extends AdminController
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly BillingPresenter $presenter,
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * Subscriptions
     *
     * Current subscriptions, newest first, cursor pages (`cursor`, `per_page` 1–100, default 30). Filters: `status`
     * (trialing, active, past_due, locked, cancelled — cancelled lists ended rows), `plan` (code), `period` (monthly,
     * yearly), `trial_ends_within` (days: trials ending in that many days), `store_id`. Each row: the subscription and
     * its store's name.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array{subscription: ' . BillingPresenter::SUBSCRIPTION . ', store: array{id: int, name: string}}>, next_cursor: string|null, per_page: int, counts: array<string, int>}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'plan' => 'nullable|string|max:50',
            'period' => ['nullable', Rule::in(BillingPeriod::values())],
            'trial_ends_within' => 'nullable|integer|min:0|max:365',
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $status = $data['status'] ?? null;
        $page = StoreSubscription::query()
            ->when($status === null, fn ($query) => $query->current())
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when(isset($data['plan']), fn ($query) => $query->whereIn('tariff_plan_id', TariffPlan::where('code', $data['plan'])->select('id')))
            ->when(isset($data['period']), fn ($query) => $query->where('billing_period', $data['period']))
            ->when(isset($data['trial_ends_within']), fn ($query) => $query->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())->where('trial_ends_at', '<=', now()->addDays((int) $data['trial_ends_within'])))
            ->when(isset($data['store_id']), fn ($query) => $query->where('store_id', (int) $data['store_id']))
            ->with(['plan', 'nextPlan', 'store' => fn ($query) => $query->withTrashed()->select(['id', 'name'])])
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 30) ?: 30);

        $counts = StoreSubscription::query()->current()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)->all();

        return $this->success([
            'data' => collect($page->items())->map(fn (StoreSubscription $row) => [
                'subscription' => $this->presenter->subscription($row),
                'store' => ['id' => (int) $row->store_id, 'name' => (string) $row->store->name],
            ])->values()->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'counts' => $counts,
        ]);
    }

    /**
     * A store's billing
     *
     * `store`; `subscription` (the current one or null); `entitlements` (as GET /owner/billing/entitlements);
     * `debt` (debt_balance, subscription_debt — unpaid invoices, commission_debt — what the credit limit counts,
     * credit_limit, over_credit_limit); `commission` (`rates` per channel with `source` override | plan | flat |
     * default, `overrides`, `flat_rate`, `delivery_courier`); `history` (subscriptions, newest first); `invoices` (the
     * last 24 with their lines, paid and outstanding parts and `dunning` steps).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{store: array{id: int, name: string, status: string}, subscription: ' . BillingPresenter::SUBSCRIPTION . '|null, entitlements: array<string, mixed>, debt: array{debt_balance: string, subscription_debt: string, commission_debt: string, credit_limit: string, over_credit_limit: bool}, commission: array{rates: array<string, array{rate: string, source: string, default: string}>, overrides: array<string, string>, flat_rate: string|null, delivery_courier: string}, history: list<' . BillingPresenter::SUBSCRIPTION . '>, invoices: list<' . BillingPresenter::INVOICE . '>}}')]
    #[ApiDocResponse(404, 'No such store (not_found)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(int $id): JsonResponse
    {
        $store = Store::find($id);
        if ($store === null) {
            return $this->notFound();
        }

        return $this->success($this->presenter->store($store));
    }

    /**
     * Assign or change the plan
     *
     * `plan` (an active plan's code), `period` (monthly | yearly; yearly only on a v2 plan; for a store without a
     * subscription, which starts now with the plan's trial when it is its first), `immediately` (default false: the
     * change waits for the end of the month or the yearly term; a higher v2 plan applied now is prorated, a lower one
     * always waits), `reason`. Answers the store's billing.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Not allowed (billing_rule) or invalid', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'plan' => 'required|string|max:50',
            'period' => ['nullable', Rule::in(BillingPeriod::values())],
            'immediately' => 'nullable|boolean',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $store = Store::find($id);
        if ($store === null) {
            return $this->notFound();
        }
        $plan = TariffPlan::where('code', $data['plan'])->first();
        if ($plan === null) {
            return $this->refuse('Tarif topilmadi.', 'plan_not_found', 422);
        }

        return $this->change($request, $store, function () use ($store, $plan, $data, $request) {
            $period = isset($data['period']) ? BillingPeriod::from($data['period']) : null;
            $subscription = $this->subscriptions->assignPlan($store, $plan, (bool) ($data['immediately'] ?? false), $this->member($request), null, $period);
            if ($period !== null && $subscription->billing_period !== $period) {
                $this->subscriptions->changePeriod($subscription, $period, $this->member($request));
            }
        }, 'Tarif saqlandi.');
    }

    /**
     * Change the billing period
     *
     * `period` monthly | yearly (v2 plans only), `reason`. During the trial it applies at once; otherwise monthly →
     * yearly from the next month (the yearly invoice is issued then) and yearly → monthly when the paid term ends.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'No subscription or not allowed (billing_rule)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function period(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(BillingPeriod::values())],
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        return $this->withSubscription($request, $id, fn (StoreSubscription $subscription) => $this->subscriptions->changePeriod($subscription, BillingPeriod::from($data['period']), $this->member($request)), "Davr saqlandi.");
    }

    /**
     * Cancel the subscription
     *
     * `immediately` (true: it ends now and the month in progress is not billed; false: at the end of the month or of
     * the paid yearly term), `reason`. Months that already ended are billed first.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'No subscription (no_subscription)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'immediately' => 'required|boolean',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        return $this->withSubscription($request, $id, fn (StoreSubscription $subscription) => $this->subscriptions->cancel($subscription, (bool) $data['immediately'], $this->member($request)), 'Obuna bekor qilindi.');
    }

    /**
     * Extend the trial
     *
     * `trial_ends_at` (a later moment, at most 90 days from now) or `days` (added to the current trial end), `reason`.
     * Only a subscription in its trial. The months the trial touches are not billed.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Not in its trial, too far or not later (billing_rule)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function trial(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'trial_ends_at' => 'required_without:days|nullable|date',
            'days' => 'required_without:trial_ends_at|nullable|integer|min:1|max:90',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        return $this->withSubscription($request, $id, function (StoreSubscription $subscription) use ($data, $request) {
            $until = isset($data['trial_ends_at'])
                ? Carbon::parse($data['trial_ends_at'], (string) config('billing.timezone'))
                : Carbon::instance($subscription->trial_ends_at ?? now())->addDays((int) $data['days']);

            return $this->subscriptions->extendTrial($subscription, $until, $this->member($request));
        }, 'Sinov davri uzaytirildi.');
    }

    /**
     * Change the store's commission
     *
     * `overrides`: the store's own rate per channel ({"pickup": "3.00", "own_delivery": null, ...}; channels
     * rizqim_courier, own_delivery, pickup, pos, aggregator; a percent 0–100 with up to 2 decimals; null removes it
     * and the channel takes its default); `flat_rate` (present: the store's flat rate for every online channel, null
     * to move it to the channel rates); `delivery_courier` own | fleet (who delivers its Mini App delivery orders:
     * own delivery 10% or RIZQIM courier 15%); `reason`. Only new orders are affected: an order keeps the rate it was
     * placed with. Answers the commission block.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{rates: array<string, array{rate: string, source: string, default: string}>, overrides: array<string, string>, flat_rate: string|null, delivery_courier: string}}')]
    public function commission(Request $request, int $id): JsonResponse
    {
        $rate = 'numeric|min:0|max:100|decimal:0,2';
        $data = $request->validate([
            'overrides' => 'sometimes|array',
            'overrides.*' => 'nullable|' . $rate,
            'flat_rate' => 'sometimes|nullable|' . $rate,
            'delivery_courier' => ['sometimes', Rule::enum(CourierType::class)],
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        foreach (array_keys($data['overrides'] ?? []) as $channel) {
            if (CommissionChannel::tryFrom((string) $channel) === null) {
                return $this->refuse("Noma'lum kanal: {$channel}.", 'validation', 422, ['errors' => ['overrides' => ["Noma'lum kanal: {$channel}."]]]);
            }
        }

        $store = Store::find($id);
        if ($store === null) {
            return $this->notFound();
        }

        $before = $this->presenter->commission($store);
        DB::transaction(function () use ($store, $data) {
            $locked = Store::whereKey($store->id)->lockForUpdate()->firstOrFail();
            $update = [];
            if (array_key_exists('overrides', $data)) {
                $overrides = (array) ($locked->commission_overrides ?? []);
                foreach ($data['overrides'] as $channel => $value) {
                    if ($value === null) {
                        unset($overrides[$channel]);
                    } else {
                        $overrides[$channel] = CommissionPolicy::normalize($value);
                    }
                }
                ksort($overrides);
                $update['commission_overrides'] = $overrides === [] ? null : $overrides;
            }
            if (array_key_exists('flat_rate', $data)) {
                $update['commission_rate'] = CommissionPolicy::normalize($data['flat_rate']);
            }
            if (array_key_exists('delivery_courier', $data)) {
                $update['delivery_courier'] = $data['delivery_courier'];
            }
            $locked->forceFill($update)->save();
        });
        $store->refresh();
        $after = $this->presenter->commission($store);

        AdminAudit::for($request)->subject($store)->store((int) $store->id)->changes($before, $after);
        if ($before === $after) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($after, "Komissiya saqlandi. U yangi buyurtmalardan amal qiladi.");
    }

    /**
     * @param  callable(StoreSubscription): mixed  $change
     */
    private function withSubscription(Request $request, int $id, callable $change, string $message): JsonResponse
    {
        $store = Store::find($id);
        if ($store === null) {
            return $this->notFound();
        }
        $subscription = $this->subscriptions->current($store);
        if ($subscription === null) {
            return $this->refuse("Bu ovqatxonada obuna yo'q.", 'no_subscription', 422);
        }

        return $this->change($request, $store, fn () => $change($subscription), $message);
    }

    /**
     * Run a change, audit the subscription before and after, answer the store's billing.
     */
    private function change(Request $request, Store $store, callable $change, string $message): JsonResponse
    {
        $before = $this->state($store);
        try {
            $change();
        } catch (\DomainException $e) {
            return $this->refuse($e->getMessage(), 'billing_rule', 422);
        }
        $this->entitlements->forget((int) $store->id);
        $after = $this->state($store);

        $current = $this->subscriptions->current($store) ?? StoreSubscription::where('store_id', $store->id)->latest('id')->first();
        AdminAudit::for($request)->subject($current ?? $store)->store((int) $store->id)->changes($before, $after);
        if ($before === $after) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($this->presenter->store($store->fresh() ?? $store), $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Store $store): array
    {
        $subscription = $this->subscriptions->current($store);

        return $subscription === null ? ['subscription' => null] : [
            'plan' => $subscription->plan->code,
            'status' => $subscription->status->value,
            'billing_period' => $subscription->billing_period->value,
            'next_plan' => $subscription->nextPlan?->code,
            'next_billing_period' => $subscription->next_billing_period?->value,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
    }
}
