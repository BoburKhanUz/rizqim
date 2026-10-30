<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Billing\BillPresenter;
use App\Models\Order;
use App\Models\OrderChargeWaiver;
use App\Models\OrderItem;

/**
 * A POS order as the till and the waiter phone show it (P3-12): its place and people, the version to send back with
 * the next change, the charge lines with what they were based on (PosChargesCalculator), and every line with its round,
 * firing, kitchen state and void. Prices are the server's; the app never computes them (docs/POS_SPEC.md §0).
 */
class PosOrderPresenter
{
    public const RELATIONS = ['items.addedBy:id,name', 'items.voidedBy:id,name', 'items.weighedBy:id,name', 'waiter:id,name', 'diningTable', 'hall', 'chargeWaivers'];

    public function __construct(
        protected PosChargesCalculator $charges,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function order(Order $order): array
    {
        $order->loadMissing(self::RELATIONS);
        $charges = $this->charges->compute($order);
        $table = $order->diningTable;
        $items = $order->items->sortBy('id')->values();

        return [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'channel' => $order->channel->value,
            'type' => $order->order_type->value,
            'store_id' => (int) $order->store_id,
            'branch_id' => $order->branch_id !== null ? (int) $order->branch_id : null,
            'version' => (int) $order->version,
            'hall' => $order->hall_id !== null ? ['id' => (int) $order->hall_id, 'name' => $order->hall->name ?? $order->hall_ref] : null,
            'table' => $table !== null ? ['id' => (int) $table->id, 'name' => $table->name, 'kind' => $table->kind->value] : null,
            'hall_ref' => $order->hall_ref,
            'table_ref' => $order->table_ref,
            // P6-25: the table plate of a counter order carried to the table (kafe "stolga olib kelish")
            'table_label' => $order->table_label,
            'guests_count' => $order->guests_count,
            'waiter' => $order->waiter ? ['id' => (int) $order->waiter->id, 'name' => $order->waiter->name] : null,
            'opened_by' => (int) $order->user_id,
            'device_id' => $order->device_id,
            'booking_id' => $order->getAttribute('booking_id') !== null ? (int) $order->getAttribute('booking_id') : null,
            // P3-26: the booking by its public id and what its deposits still hold to pay the bills with
            'booking_uuid' => BillPresenter::bookingUuid($order),
            'deposit_available' => BillPresenter::depositAvailable($order)->toString(),
            'bill_locked' => $order->getAttribute('bill_locked_at') !== null,
            'bill_requested_at' => $order->bill_requested_at?->toJSON(),
            'notes' => $order->notes,
            'opened_at' => $order->created_at?->toJSON(),
            'updated_at' => $order->updated_at?->toJSON(),
            'subtotal' => (string) $order->subtotal,
            'discount' => (string) $order->discount,
            // P3-37: the till's discount: percent|amount|null, the value typed ("10.00" %, or so'm), why, who gave it
            'discount_kind' => $order->getAttribute('discount_kind'),
            'discount_value' => $order->getAttribute('discount_value') !== null ? (string) $order->getAttribute('discount_value') : null,
            'discount_reason' => $order->getAttribute('discount_reason'),
            'discount_by' => $order->getAttribute('discount_by') !== null ? (int) $order->getAttribute('discount_by') : null,
            'service_charge_amount' => (string) $order->service_charge_amount,
            'place_fee_amount' => (string) $order->place_fee_amount,
            'room_fee_amount' => (string) $order->room_fee_amount,
            'min_spend_topup_amount' => (string) $order->min_spend_topup_amount,
            // P6-34: the packaging lines' total (charges.packaging_lines) and the pickup time (null: now)
            'packaging_amount' => (string) ($order->getAttribute('packaging_amount') ?? '0.00'),
            'pickup' => \App\Domain\Pos\Takeaway\ScheduledPickup::present($order),
            'total_price' => (string) $order->total_price,
            'charges' => $charges->toArray() + [
                'waivers' => $order->chargeWaivers->sortBy('id')->values()->map(fn (OrderChargeWaiver $waiver) => [
                    'charge' => $waiver->charge,
                    'reason' => $waiver->reason,
                    'waived_by' => $waiver->waived_by !== null ? (int) $waiver->waived_by : null,
                    'approved_by' => $waiver->approved_by !== null ? (int) $waiver->approved_by : null,
                    'waived_at' => $waiver->created_at?->toJSON(),
                ])->all(),
            ],
            // P3-31: an order a till sold offline: its local number and what the manager should look at
            'offline' => $order->local_number !== null || $order->offline_created_at !== null || !empty($order->sync_flags) ? [
                'local_number' => $order->local_number,
                'created_at' => $order->offline_created_at?->toJSON(),
                'synced_at' => $order->synced_at?->toJSON(),
                'flags' => array_map(fn (array $flag) => [
                    'flag' => (string) ($flag['flag'] ?? ''),
                    'op_id' => isset($flag['op_id']) ? (string) $flag['op_id'] : null,
                    'amount' => isset($flag['amount']) ? (string) $flag['amount'] : null,
                    'name' => isset($flag['name']) ? (string) $flag['name'] : null,
                ], (array) ($order->sync_flags ?? [])),
            ] : null,
            // P6-13: the queue number and where it is in the pickup queue (preparing, ready, handed); null without one
            'queue' => PickupQueue::present($order),
            'rounds' => $items->max('round_no') !== null ? (int) $items->max('round_no') : 0,
            // P6-21: the order's courses and where each is (held, sent, ready, served); [] without courses
            'courses' => PosCourses::summary($items),
            // P6-25: lines waiting for their real weight (no bill until they are weighed)
            'weight_pending' => $items->filter(fn (OrderItem $item) => $item->awaitingWeight() && !$item->isVoid())->count(),
            'items' => $items->map(fn (OrderItem $item) => $this->item($item))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function item(OrderItem $item): array
    {
        $data = [
            'id' => (int) $item->id,
            'round_no' => $item->round_no,
            'product_variant_id' => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'quantity' => $item->quantity,
            'unit' => $item->unit->value,
            'quantity_label' => $item->quantityLabel(),
            'unit_price' => (string) $item->unit_price,
            'modifiers' => array_map(fn (array $modifier) => [
                'id' => (int) ($modifier['id'] ?? 0),
                'name' => (string) ($modifier['name'] ?? ''),
                'group_name' => isset($modifier['group_name']) ? (string) $modifier['group_name'] : null,
                'quantity' => (int) ($modifier['quantity'] ?? 1),
                'price_delta' => (string) ($modifier['price_delta'] ?? '0.00'),
            ], $item->modifiers ?? []),
            'modifiers_total' => (string) $item->modifiers_total,
            'total_price' => (string) $item->total_price,
            'note' => $item->note,
            'seat_no' => $item->seat_no,
            'fire_mode' => $item->fire_mode?->value,
            'due_at' => $item->due_at?->toJSON(),
            'kitchen_status' => $item->kitchen_status->value,
            'station_id' => $item->station_id,
            'sent_at' => $item->sent_at?->toJSON(),
            'ready_at' => $item->ready_at?->toJSON(),
            'added_by' => $item->addedBy ? ['id' => (int) $item->addedBy->id, 'name' => $item->addedBy->name] : null,
            'added_at' => $item->created_at?->toJSON(),
            'voided_at' => $item->voided_at?->toJSON(),
            'voided_by' => $item->voidedBy ? ['id' => (int) $item->voidedBy->id, 'name' => $item->voidedBy->name] : null,
            'void_reason' => $item->void_reason,
            // P6-21: the line's course (null: no courses)
            'course_no' => $item->course_no,
            // P6-25: "weight later": awaiting (the quantity is the estimate) | weighed (the real weight) | null
            'weight_status' => $item->weight_status?->value,
            'weight_basis' => $item->weight_basis?->value,
            'weighed_by' => $item->weighedBy ? ['id' => (int) $item->weighedBy->id, 'name' => $item->weighedBy->name] : null,
            'weighed_at' => $item->weighed_at?->toJSON(),
        ];

        // P6-15: the parts of a kompleks line (the kitchen prepares these), only on such lines
        if ($item->set_parts) {
            $data['set_parts'] = $item->set_parts;
        }
        // P6-31: a combo line's parts as sold (slot, dish, pieces, modifiers, price delta, receipt label, kitchen line id),
        // only on such lines; the line's kitchen_status is its parts' (ready once all are)
        if ($item->isComboLine()) {
            $data['combo_parts'] = $item->combo_parts;
        }

        return $data;
    }
}
