<?php

namespace App\Models;

use App\Enums\SubscriptionInvoiceStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P1-23: a store's monthly subscription invoice (docs/BILLING.md), one per store and month. Created and voided only
 * through App\Services\Finance\SubscriptionService, which posts and reverses it on the store's debt ledger.
 *
 * @property int $id
 * @property string $uuid
 * @property string $number
 * @property int $store_id
 * @property int|null $store_subscription_id
 * @property int|null $tariff_plan_id
 * @property string $period
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property int $terminals_count
 * @property int $included_terminals
 * @property numeric-string $base_fee
 * @property numeric-string $fee_per_terminal
 * @property array<int, array<string, mixed>> $lines
 * @property numeric-string $amount
 * @property \App\Enums\SubscriptionInvoiceStatus $status
 * @property \Illuminate\Support\Carbon $due_date
 * @property \Illuminate\Support\Carbon $issued_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $ledger_transaction_id
 * @property int|null $void_transaction_id
 *
 * P4-21
 * @property \App\Enums\InvoiceKind $kind
 * @property int $sequence
 * @property numeric-string $paid_amount what the ledger has paid of it (subscription_debt is the rest)
 *
 * Review W2: the first member's request to void an invoice of the four-eyes threshold or more
 * @property int|null $void_requested_by
 * @property \Illuminate\Support\Carbon|null $void_requested_at
 * @property string|null $void_request_reason
 */
class SubscriptionInvoice extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'terminals_count' => 'integer',
            'included_terminals' => 'integer',
            'base_fee' => 'decimal:2',
            'fee_per_terminal' => 'decimal:2',
            'lines' => 'array',
            'amount' => 'decimal:2',
            'status' => SubscriptionInvoiceStatus::class,
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            // P4-21
            'kind' => \App\Enums\InvoiceKind::class,
            'sequence' => 'integer',
            'paid_amount' => 'decimal:2',
            'void_requested_by' => 'integer',
            'void_requested_at' => 'datetime',
        ];
    }

    /**
     * Review W2: the member who asked to void it (the first of two).
     *
     * @return BelongsTo<User, $this>
     */
    public function voidRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_requested_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function total(): Money
    {
        return Money::of($this->amount);
    }

    /**
     * P4-21: what is still unpaid of it (0 for a void one).
     */
    public function outstanding(): Money
    {
        return $this->status === SubscriptionInvoiceStatus::VOID
            ? Money::zero()
            : Money::max(Money::zero(), $this->total()->minus($this->paid_amount ?? 0));
    }

    /**
     * The shape the owner API returns.
     *
     * @return array{uuid: string, number: string, period: string, period_start: string, period_end: string, plan: array{code: string, name: string}|null, terminals_count: int, included_terminals: int, base_fee: float, fee_per_terminal: float, lines: list<array{type: string, label: string, quantity: int, unit_price: float, amount: float}>, amount: float, kind: string, paid_amount: string, status: string, due_date: string, issued_at: string, paid_at: string|null, voided_at: string|null}
     */
    public function toPublicArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'number' => $this->number,
            'period' => $this->period,
            'period_start' => $this->period_start->toIso8601String(),
            'period_end' => $this->period_end->toIso8601String(),
            'plan' => $this->plan ? ['code' => $this->plan->code, 'name' => $this->plan->name] : null,
            'terminals_count' => $this->terminals_count,
            'included_terminals' => $this->included_terminals,
            'base_fee' => Money::of($this->base_fee)->toFloat(),
            'fee_per_terminal' => Money::of($this->fee_per_terminal)->toFloat(),
            'lines' => array_values(array_map(fn (array $line) => [
                'type' => (string) $line['type'],
                'label' => (string) $line['label'],
                'quantity' => (int) $line['quantity'],
                'unit_price' => Money::of($line['unit_price'])->toFloat(),
                'amount' => Money::of($line['amount'])->toFloat(),
            ], $this->lines)),
            'amount' => $this->total()->toFloat(),
            // P4-21: yearly, upgrade or monthly; what the ledger paid of it, as a decimal string (new fields)
            'kind' => $this->kind->value,
            'paid_amount' => Money::of($this->paid_amount ?? 0)->toString(),
            'status' => $this->status->value,
            'due_date' => $this->due_date->toDateString(),
            'issued_at' => $this->issued_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<StoreSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(StoreSubscription::class, 'store_subscription_id');
    }

    /**
     * @return BelongsTo<TariffPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TariffPlan::class, 'tariff_plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
