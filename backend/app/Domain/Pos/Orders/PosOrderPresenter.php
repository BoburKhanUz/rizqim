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
    public const RELATIONS = ['items.addedBy:id,name', 'items.voidedBy:id,name', 'waiter:id,name', 'diningTable', 'hall', 'chargeWaivers'];

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
            'service_charge_amount' => (string) $order->service_charge_amount,
            'place_fee_amount' => (string) $order->place_fee_amount,
            'room_fee_amount' => (string) $order->room_fee_amount,
            'min_spend_topup_amount' => (string) $order->min_spend_topup_amount,
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
            'rounds' => $items->max('round_no') !== null ? (int) $items->max('round_no') : 0,
            'items' => $items->map(fn (OrderItem $item) => $this->item($item))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function item(OrderItem $item): array
    {
        return [
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
        ];
    }
}
