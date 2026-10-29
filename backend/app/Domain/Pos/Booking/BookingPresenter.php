<?php

namespace App\Domain\Pos\Booking;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\Quantity;

/**
 * The booking as the POS and the web panel read it (docs/API.md "Bron va nasiya"). Money as decimal strings
 * ("12000.00"), times as UTC ISO 8601.
 */
class BookingPresenter
{
    public const SUMMARY = 'array{uuid: string, branch_id: int, dining_table_id: int|null, table_ids: list<int>, starts_at: string, ends_at: string, guests_planned: int, event_type: string, event_type_label: string, contact_name: string, contact_phone: string|null, source: string, source_label: string, status: string, status_label: string, min_spend: string|null, note: string|null, order_uuid: string|null, deposit_total: string, deposit_available: string, deposit_unsettled: bool, items_count: int, cancel_reason: string|null, version: int, created_by: int|null, confirmed_at: string|null, arrived_at: string|null, completed_at: string|null, canceled_at: string|null, confirmation_sent_at: string|null, confirmation_channel: string|null, created_at: string|null, updated_at: string|null}';

    public const ITEM = 'array{id: int, product_variant_id: int, product_variant_uuid: string|null, product_name: string, variant_name: string|null, unit: string, quantity: int|float, modifiers: list<array<string, mixed>>, unit_price: string, note: string|null, due_at: string|null}';

    public const PAYMENT = 'array{uuid: string, amount: string, tender: string, card_type: string|null, transaction_id: string|null, status: string, status_label: string, remaining: string, applied_amount: string, refunded_amount: string, retained_amount: string, fiscal_receipt_uuid: string|null, fiscal_status: string|null, settle_reason: string|null, settle_receipt_uuid: string|null, settle_fiscal_status: string|null, approved_by: int|null, user_id: int|null, created_at: string|null, settled_at: string|null}';

    public const DETAIL = 'array{uuid: string, branch_id: int, dining_table_id: int|null, table_ids: list<int>, starts_at: string, ends_at: string, guests_planned: int, event_type: string, event_type_label: string, contact_name: string, contact_phone: string|null, source: string, source_label: string, status: string, status_label: string, min_spend: string|null, note: string|null, order_uuid: string|null, deposit_total: string, deposit_available: string, deposit_unsettled: bool, items_count: int, cancel_reason: string|null, version: int, created_by: int|null, confirmed_at: string|null, arrived_at: string|null, completed_at: string|null, canceled_at: string|null, confirmation_sent_at: string|null, confirmation_channel: string|null, created_at: string|null, updated_at: string|null, deposit_required: string, deposit_missing: string, items: list<' . self::ITEM . '>, payments: list<' . self::PAYMENT . '>}';

    /**
     * @return array<string, mixed>
     */
    public function summary(Booking $booking): array
    {
        $booking->loadMissing(['tables', 'payments']);
        $payments = $booking->payments;

        $available = $payments->filter(fn (BookingPayment $payment) => $payment->status === BookingPaymentStatus::TAKEN)
            ->reduce(fn (Money $sum, BookingPayment $payment) => $sum->plus($payment->remaining()), Money::zero());
        $main = $booking->dining_table_id !== null ? (int) $booking->dining_table_id : null;
        $tableIds = array_values(array_unique(array_merge(
            $main !== null ? [$main] : [],
            $booking->tables->sortBy('id')->pluck('dining_table_id')->map(fn ($id) => (int) $id)->all(),
        )));

        return [
            'uuid' => (string) $booking->uuid,
            'branch_id' => (int) $booking->branch_id,
            'dining_table_id' => $main,
            'table_ids' => $tableIds,
            'starts_at' => $booking->starts_at->toJSON(),
            'ends_at' => $booking->ends_at->toJSON(),
            'guests_planned' => (int) $booking->guests_planned,
            'event_type' => $booking->event_type->value,
            'event_type_label' => $booking->event_type->label(),
            'contact_name' => (string) $booking->contact_name,
            'contact_phone' => $booking->contact_phone,
            'source' => $booking->source->value,
            'source_label' => $booking->source->label(),
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'min_spend' => $booking->min_spend,
            'note' => $booking->note,
            'order_uuid' => $booking->order_id !== null ? Order::withTrashed()->whereKey($booking->order_id)->value('uuid') : null,
            'deposit_total' => $payments->reduce(fn (Money $sum, BookingPayment $payment) => $sum->plus($payment->amount), Money::zero())->toString(),
            'deposit_available' => $available->toString(),
            // Over (canceled, no-show, completed) with deposit money nobody decided about yet: refund or retain
            'deposit_unsettled' => !$booking->status->holdsSlot() && $available->isPositive(),
            'items_count' => $booking->relationLoaded('items') ? $booking->items->count() : (int) ($booking->items_count ?? $booking->items()->count()),
            'cancel_reason' => $booking->cancel_reason,
            'version' => (int) $booking->version,
            'created_by' => $booking->created_by !== null ? (int) $booking->created_by : null,
            'confirmed_at' => $booking->confirmed_at?->toJSON(),
            'arrived_at' => $booking->arrived_at?->toJSON(),
            'completed_at' => $booking->completed_at?->toJSON(),
            'canceled_at' => $booking->canceled_at?->toJSON(),
            // P3-38: when and how the guest was last sent the confirmation (null: never)
            'confirmation_sent_at' => $booking->confirmation_sent_at?->toJSON(),
            'confirmation_channel' => $booking->confirmation_channel,
            'created_at' => $booking->created_at?->toJSON(),
            'updated_at' => $booking->updated_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Booking $booking): array
    {
        $booking->loadMissing(['items', 'payments']);

        $rules = app(BookingRules::class);
        $branch = Branch::find($booking->branch_id);
        $required = $branch !== null ? $rules->depositRequired($branch, $booking) : Money::zero();
        $taken = $booking->payments->filter(fn (BookingPayment $payment) => $payment->status === BookingPaymentStatus::TAKEN)
            ->reduce(fn (Money $sum, BookingPayment $payment) => $sum->plus($payment->amount), Money::zero());

        return $this->summary($booking) + [
            // P6-26: the deposit the branch's table booking rule asks for, and what of it is still missing
            'deposit_required' => $required->toString(),
            'deposit_missing' => $required->isGreaterThan($taken) ? $required->minus($taken)->toString() : '0.00',
            'items' => $booking->items->sortBy([['due_at', 'asc'], ['id', 'asc']])->values()->map(fn (BookingItem $item) => $this->item($item))->all(),
            'payments' => $booking->payments->sortBy('id')->values()->map(fn (BookingPayment $payment) => $this->payment($payment))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function item(BookingItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'product_variant_id' => (int) $item->product_variant_id,
            // P3-26: the variant's uuid, as POS order lines and the POS catalog name it
            'product_variant_uuid' => ProductVariant::withTrashed()->whereKey($item->product_variant_id)->value('uuid'),
            'product_name' => (string) $item->product_name,
            'variant_name' => $item->variant_name,
            'unit' => $item->unit->value,
            'quantity' => Quantity::normalize($item->quantity) ?? 0,
            'modifiers' => $item->modifiers ?? [],
            'unit_price' => $item->unit_price,
            'note' => $item->note,
            'due_at' => $item->due_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payment(BookingPayment $payment): array
    {
        return [
            'uuid' => (string) $payment->uuid,
            'amount' => $payment->amount,
            'tender' => (string) $payment->tender,
            'card_type' => $payment->card_type,
            'transaction_id' => $payment->transaction_id,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'remaining' => $payment->status === BookingPaymentStatus::TAKEN ? $payment->remaining()->toString() : '0.00',
            'applied_amount' => $payment->applied_amount,
            'refunded_amount' => $payment->refunded_amount,
            'retained_amount' => $payment->retained_amount,
            'fiscal_receipt_uuid' => $payment->fiscal_receipt_uuid,
            'fiscal_status' => $payment->fiscal_status,
            'settle_reason' => $payment->settle_reason,
            'settle_receipt_uuid' => $payment->settle_receipt_uuid,
            'settle_fiscal_status' => $payment->settle_fiscal_status,
            'approved_by' => $payment->approved_by !== null ? (int) $payment->approved_by : null,
            'user_id' => $payment->user_id !== null ? (int) $payment->user_id : null,
            'created_at' => $payment->created_at?->toJSON(),
            'settled_at' => $payment->settled_at?->toJSON(),
        ];
    }
}
