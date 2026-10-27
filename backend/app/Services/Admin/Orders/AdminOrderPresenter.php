<?php

namespace App\Services\Admin\Orders;

use App\Enums\AdminPermission;
use App\Enums\CancelReason;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefundRequest;
use App\Models\OrderSlaEvent;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminPii;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-14: marketplace orders as the Boshqaruv markazi shows them: the list row (no phone numbers) and the order card
 * (phones through AdminPii: masked unless admin.pii.view, a reveal is audited). Money as decimal strings, times ISO 8601.
 */
class AdminOrderPresenter
{
    /** Relations a list row reads. */
    public const LIST_RELATIONS = [
        'store:id,name',
        'branch:id,name,region_id',
        'branch.region:id,name_uz',
        'user:id,name',
        'courier:id,name',
        'payments:id,order_id,status,amount,payload',
    ];

    public function __construct(
        private readonly OrderSla $sla,
        private readonly AdminPii $pii,
        private readonly AdminPermissionService $permissions,
        private readonly RefundRequestService $refunds,
    ) {}

    /**
     * Adds `refund_pending` (a request waiting for approval) to each row.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function withRefundPending(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('orders.*');
        }

        return $query->selectRaw("exists(select 1 from order_refund_requests r where r.order_id = orders.id and r.status = 'pending') as refund_pending");
    }

    /**
     * @param  array{accept: int, prepare: int, deliver: int}  $limits
     * @return array<string, mixed>
     */
    public function row(Order $order, array $limits, ?CarbonInterface $now = null): array
    {
        [$acceptedAt, $enRouteAt] = AdminOrderQuery::stageTimes($order);
        $region = $order->branch?->region;

        return [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'status_group' => AdminOrderQuery::groupOf($order->status->value),
            'type' => $order->order_type->value,
            'channel' => $order->channel->value,
            'channel_label' => $order->channel->label(),
            'store' => $order->store ? ['id' => (int) $order->store->id, 'name' => (string) $order->store->name] : null,
            'branch' => $order->branch ? ['id' => (int) $order->branch->id, 'name' => (string) $order->branch->name] : null,
            'region' => $region ? ['id' => (int) $region->id, 'name' => (string) $region->name_uz] : null,
            'customer_name' => $order->user?->name,
            'total' => $order->total_price,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status->value,
            'courier' => $order->courier ? ['id' => (int) $order->courier->id, 'name' => (string) $order->courier->name] : null,
            'sla' => $this->sla->current($order, $acceptedAt, $enRouteAt, $limits, $now),
            'refund_pending' => (bool) $order->getAttribute('refund_pending'),
            'manual_refund_required' => $this->manualRefundRequired($order),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The order card (GET /admin/orders/{uuid}).
     *
     * @param  array{accept: int, prepare: int, deliver: int}  $limits
     * @return array<string, mixed>
     */
    public function card(Order $order, User $viewer, array $limits): array
    {
        $order->loadMissing([
            'store:id,name,phone,telegram_admin_chat_id',
            'branch:id,name,address,region_id',
            'branch.region:id,name_uz',
            'user:id,name,phone,telegram_id',
            'courier:id,name,phone',
            'items',
            'payments',
            'statusHistories.user:id,name,role,platform_role',
        ]);
        $now = now();
        $histories = $order->statusHistories->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();
        $acceptedAt = $histories->first(fn (OrderStatusHistory $row) => $row->status->value === 'accepted')?->created_at;
        $enRouteAt = $histories->first(fn (OrderStatusHistory $row) => in_array($row->status->value, ['on_the_way', 'delivery'], true))?->created_at;

        $balance = $this->refunds->balance($order);
        $requests = OrderRefundRequest::query()
            ->where('order_id', $order->id)
            ->with(['requester:id,name', 'decider:id,name'])
            ->orderByDesc('id')
            ->get();
        $can = fn (AdminPermission $permission) => $this->permissions->has($viewer, $permission);

        $base = $this->row($order, $limits, $now);
        $base['sla'] = $this->sla->current($order, $acceptedAt, $enRouteAt, $limits, $now);
        $base['refund_pending'] = $requests->contains(fn (OrderRefundRequest $request) => $request->isPending());

        return $base + [
            'notes' => $order->notes,
            'delivery_address' => $order->order_type->value === 'delivery' ? $order->delivery_address : null,
            'customer' => $order->user ? [
                'id' => (int) $order->user->id,
                'name' => $order->user->name,
                'phone' => $this->pii->phone($viewer, $order->user->phone, 'user', (int) $order->user->id),
                'has_telegram' => $order->user->telegram_id !== null && !str_starts_with((string) $order->user->telegram_id, 'temp_'),
            ] : null,
            'courier' => $order->courier ? [
                'id' => (int) $order->courier->id,
                'name' => (string) $order->courier->name,
                'phone' => $this->pii->phone($viewer, $order->courier->phone, 'user', (int) $order->courier->id),
            ] : null,
            'store' => $order->store ? [
                'id' => (int) $order->store->id,
                'name' => (string) $order->store->name,
                'phone' => $this->pii->phone($viewer, $order->store->phone, 'store', (int) $order->store->id),
            ] : null,
            'branch' => $order->branch ? [
                'id' => (int) $order->branch->id,
                'name' => (string) $order->branch->name,
                'address' => $order->branch->address,
            ] : null,
            'items' => $order->items->sortBy('id')->values()->map(fn (OrderItem $item) => [
                'id' => (int) $item->id,
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'modifiers' => trim($item->modifiersLabel()) !== '' ? trim($item->modifiersLabel(), ' ()') : null,
                'quantity' => $item->quantityLabel(),
                'unit_price' => $item->unit_price,
                'total' => $item->total_price,
                'void' => $item->isVoid(),
            ])->all(),
            'totals' => [
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'delivery_price' => $order->delivery_price,
                'service_charge' => $order->service_charge_amount,
                'place_fee' => $order->place_fee_amount,
                'total' => $order->total_price,
                'commission' => $order->commission_amount,
                'payment_owner' => $order->payment_owner,
            ],
            'cancel' => $order->status->isCancellation() ? [
                'reason' => $order->cancel_reason?->value,
                'reason_label' => $order->cancel_reason?->label(),
                'note' => $order->cancel_note,
            ] : null,
            'payments' => $order->payments->sortBy('id')->values()->map(fn (Payment $payment) => [
                'uuid' => (string) $payment->uuid,
                'provider' => $payment->provider,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'manual_refund_required' => !empty($payment->payload['manual_refund_required']) && $payment->status === PaymentStatus::PAID,
                'manual_refund_approved' => array_values(array_map(fn (array $row) => [
                    'refund_request_id' => (int) ($row['refund_request_id'] ?? 0),
                    'amount' => (string) ($row['amount'] ?? '0.00'),
                    'approved_at' => $row['approved_at'] ?? null,
                ], (array) ($payment->payload['manual_refund_approved'] ?? []))),
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->all(),
            'refunds' => [
                'paid' => $balance['paid']->toString(),
                'refunded' => $balance['refunded']->toString(),
                'refundable' => $balance['refundable']->toString(),
                'requests' => $requests->map(fn (OrderRefundRequest $request) => $this->refundRequest($request, $viewer))->all(),
            ],
            'history' => $histories->map(fn (OrderStatusHistory $row) => [
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'from_status' => $row->from_status?->value,
                'at' => $row->created_at?->toIso8601String(),
                'actor' => self::actor($row->user),
                'notes' => $row->notes,
            ])->all(),
            'sla_timeline' => $this->sla->timeline($order, $histories, $limits, $now),
            'sla_events' => OrderSlaEvent::where('order_id', $order->id)->orderBy('crossed_at')->orderBy('id')->get()
                ->map(fn (OrderSlaEvent $event) => [
                    'stage' => $event->stage,
                    'stage_label' => OrderSlaSettings::label($event->stage),
                    'state' => $event->state,
                    'limit_minutes' => $event->limit_minutes,
                    'crossed_at' => $event->crossed_at->toIso8601String(),
                ])->all(),
            'fiscal_receipts' => FiscalReceipt::query()->forSubject('order', (int) $order->id)->orderBy('id')->get()
                ->map(fn (FiscalReceipt $receipt) => [
                    'uuid' => (string) $receipt->uuid,
                    'type' => $receipt->type->value,
                    'status' => $receipt->status->value,
                    'provider' => $receipt->provider,
                    'total' => $receipt->total,
                    'receipt_number' => $receipt->receipt_number,
                    'qr_url' => $receipt->qr_url,
                    'issued_at' => $receipt->issued_at?->toIso8601String(),
                    'error' => $receipt->error !== null ? mb_substr((string) $receipt->error, 0, 300) : null,
                ])->all(),
            'audit' => $this->audit($order),
            'actions' => [
                'can_intervene' => $can(AdminPermission::ORDERS_INTERVENE),
                'can_cancel' => $can(AdminPermission::ORDERS_INTERVENE) && OrderInterventionService::canCancel($order),
                'cancel_reasons' => OrderInterventionService::canCancel($order)
                    ? collect(CancelReason::staffOptionsFor($order->order_type, $order->status))->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all()
                    : [],
                'transitions' => array_map(fn ($status) => ['status' => $status->value, 'label' => $status->label()], OrderInterventionService::fixableTo($order)),
                'message_store' => $can(AdminPermission::ORDERS_INTERVENE),
                'message_customer' => $can(AdminPermission::ORDERS_INTERVENE) && $order->user?->telegram_id !== null,
                'can_request_refund' => $can(AdminPermission::ORDERS_REFUND_REQUEST) && $this->refunds->canRequest($order)
                    && $balance['refundable']->isPositive() && !$base['refund_pending'],
                'can_approve_refund' => $can(AdminPermission::ORDERS_REFUND_APPROVE),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refundRequest(OrderRefundRequest $request, User $viewer): array
    {
        $request->loadMissing(['requester:id,name', 'decider:id,name']);

        return [
            'id' => (int) $request->id,
            'uuid' => (string) $request->uuid,
            'amount' => $request->amount,
            'status' => $request->status,
            'status_label' => RefundRequestService::statusLabel($request->status),
            'reason' => $request->reason,
            'requested_by' => $request->requester ? ['id' => (int) $request->requester->id, 'name' => (string) $request->requester->name] : null,
            'requested_at' => $request->created_at?->toIso8601String(),
            'decided_by' => $request->decider ? ['id' => (int) $request->decider->id, 'name' => (string) $request->decider->name] : null,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'decision_note' => $request->decision_note,
            'execution' => $request->execution,
            'mine' => (int) $request->requested_by === (int) $viewer->id,
            'can_decide' => $this->refunds->canDecide($request, $viewer),
        ];
    }

    /**
     * @return array{id: int, name: string, role: string, team: bool}|null
     */
    public static function actor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->platform_role?->label() ?? $user->role->labelUz(),
            'team' => $user->platform_role !== null,
        ];
    }

    public function manualRefundRequired(Order $order): bool
    {
        if (!$order->status->isCancellation() && $order->status->value !== 'refunded') {
            return false;
        }

        return $order->payments->contains(fn (Payment $payment) => $payment->status === PaymentStatus::PAID
            && !empty($payment->payload['manual_refund_required'])
            && empty($payment->payload['manual_refund_approved']));
    }

    /**
     * The last 50 audit entries about this order: the stores' own (order.cancelled...) and the team's (admin.orders.*).
     *
     * @return list<array<string, mixed>>
     */
    private function audit(Order $order): array
    {
        return AuditLog::query()
            ->with('user:id,name,role,platform_role')
            ->where('model_type', $order->getMorphClass())
            ->where('model_id', $order->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (AuditLog $log) {
                $meta = $log->payload_json;

                return [
                    'id' => (int) $log->id,
                    'action' => $log->action,
                    'actor' => self::actor($log->user),
                    'reason' => is_array($meta) && is_string($meta['reason'] ?? null) ? $meta['reason'] : null,
                    'before' => $log->before,
                    'after' => $log->after,
                    'meta' => $meta,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
