<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\SubscriptionInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Finance\SubscriptionService;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P1-23: the store's tariff plan and its monthly subscription invoices, read-only (finance.view; docs/BILLING.md).
 * Only the caller's own store exists here.
 */
#[Group('Owner: billing', "Tarif rejasi, obuna davri, hisoblangan terminallar va oylik hisob-fakturalar (ega).")]
class OwnerBillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubscriptionService $subscriptions,
        protected \App\Services\Finance\Billing\Entitlements $entitlements,
    ) {}

    /**
     * What the plan lets the store use
     *
     * P4-21 (docs/BILLING.md, "Funksiyalar va cheklovlar"): `plan` (null without a subscription), `legacy` (true: every
     * feature, the store is not on a v2 plan yet), `period` monthly|yearly, `status` none|trialing|active|past_due|locked,
     * `trial_ends_at`, `current_period`, `term_ends_at` (yearly), `locked_since`, `lock_at` (when an overdue invoice locks
     * it), `settings_locked`, `features` (every App\Enums\PlanFeature: on or off), `required_plans` (the cheapest plan
     * with each gated feature: show "Asosiy tarifda bor"), `devices` (pos and kds: included, active, extra, extra_price
     * as a decimal string, can_add; waiter phones unlimited and free), `notice` (the overdue invoice reminder or the lock
     * message for a banner, null when none). Any member of the store and the terminals read it.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{plan: array{code: string, name: string, tier: string|null, version: int}|null, legacy: bool, period: string|null, status: string, trial_ends_at: string|null, current_period: array{start: string, end: string}|null, term_ends_at: string|null, locked_since: string|null, lock_at: string|null, settings_locked: bool, features: array<string, bool>, required_plans: array<string, string|null>, devices: array{pos: array{included: int|null, active: int, extra: int, extra_price: string|null, can_add: bool}, kds: array{included: int|null, active: int, extra: int, extra_price: string|null, can_add: bool}, waiter: array{included: null, unlimited: bool, extra_price: string, can_add: bool}}, notice: array{level: string, message: string, days_overdue: int, lock_at: string|null, invoice: array{uuid: string, number: string, amount: string, outstanding: string, due_date: string}}|null}}')]
    public function entitlements(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->store_id) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        return $this->success($this->entitlements->toArray((int) $user->store_id));
    }

    /**
     * The current plan and period, the terminals counted so far and an estimate of the period's invoice.
     * `subscription` is null for a store on no plan (commission at the store's own rate, no monthly fee).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{subscription: array{status: string, status_label: string, plan: array{code: string, name: string, description: string|null, summary: string, commission_percent: float|null, monthly_base_fee: float, monthly_fee_per_terminal: float, included_terminals: int, trial_days: int}, started_at: string, trial_ends_at: string|null, current_period: array{period: string, start: string, end: string}, next_plan: array{code: string, name: string, description: string|null, summary: string, commission_percent: float|null, monthly_base_fee: float, monthly_fee_per_terminal: float, included_terminals: int, trial_days: int}|null, cancel_at_period_end: bool, billing_period: string, next_billing_period: string|null, term_ends_at: string|null}|null, commission_rate: float, terminals: array{active_now: int, counted: int|null}, next_invoice: array{period: string, issued_on: string, trial: bool, amount: float, lines: list<array{type: string, label: string, quantity: int, unit_price: float, amount: float}>}|null}}')]
    public function subscription(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->store;

        if (!$store) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        return $this->success($this->subscriptions->overview($store));
    }

    /**
     * The store's subscription invoices, newest month first.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{current_page: int, data: list<array{uuid: string, number: string, period: string, period_start: string, period_end: string, plan: array{code: string, name: string}|null, terminals_count: int, included_terminals: int, base_fee: float, fee_per_terminal: float, lines: list<array{type: string, label: string, quantity: int, unit_price: float, amount: float}>, amount: float, kind: string, paid_amount: string, status: string, due_date: string, issued_at: string, paid_at: string|null, voided_at: string|null}>, per_page: int, total: int, last_page: int}}')]
    public function invoices(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(SubscriptionInvoiceStatus::class)],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'per_page' => 'nullable|integer|min:1|max:100',
            // P2-30: the Laravel page (documented, so the generated clients carry it)
            'page' => 'nullable|integer|min:1|max:100000',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!$user->store_id) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        $invoices = SubscriptionInvoice::query()
            ->where('store_id', $user->store_id)
            ->with('plan:id,code,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('period'), fn ($query) => $query->where('period', $request->input('period')))
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        $invoices->getCollection()->transform(fn (SubscriptionInvoice $invoice) => $invoice->toPublicArray());

        return $this->success($invoices);
    }
}
