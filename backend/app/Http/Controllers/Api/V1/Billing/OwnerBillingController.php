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
    ) {}

    /**
     * The current plan and period, the terminals counted so far and an estimate of the period's invoice.
     * `subscription` is null for a store on no plan (commission at the store's own rate, no monthly fee).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{subscription: array{status: string, status_label: string, plan: array{code: string, name: string, description: string|null, summary: string, commission_percent: float|null, monthly_base_fee: float, monthly_fee_per_terminal: float, included_terminals: int, trial_days: int}, started_at: string, trial_ends_at: string|null, current_period: array{period: string, start: string, end: string}, next_plan: array{code: string, name: string, description: string|null, summary: string, commission_percent: float|null, monthly_base_fee: float, monthly_fee_per_terminal: float, included_terminals: int, trial_days: int}|null, cancel_at_period_end: bool}|null, commission_rate: float, terminals: array{active_now: int, counted: int|null}, next_invoice: array{period: string, issued_on: string, trial: bool, amount: float, lines: list<array{type: string, label: string, quantity: int, unit_price: float, amount: float}>}|null}}')]
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
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{current_page: int, data: list<array{uuid: string, number: string, period: string, period_start: string, period_end: string, plan: array{code: string, name: string}|null, terminals_count: int, included_terminals: int, base_fee: float, fee_per_terminal: float, lines: list<array{type: string, label: string, quantity: int, unit_price: float, amount: float}>, amount: float, status: string, due_date: string, issued_at: string, paid_at: string|null, voided_at: string|null}>, per_page: int, total: int, last_page: int}}')]
    public function invoices(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(SubscriptionInvoiceStatus::class)],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'per_page' => 'nullable|integer|min:1|max:100',
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
