<?php

namespace App\Domain\Receipts;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\QrPayments\UzQr\SendUzQrReceiptLink;
use App\Enums\PaymentStatus;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\Store;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wave P (receipts): what a customer's GET /orders adds to each of their orders (ZRU-792 art. 23 3-qism: the
 * electronic receipt reaches the buyer's "shaxsiy kabinet"; VM 943 1a-ilova 8: its link):
 *
 *  - `seller`: the order's orders.seller_snapshot, or the store's current details for an order from before it
 *    (SellerDetails);
 *  - `receipts`: its sale and refund receipts from fiscal_receipts — an online payment's (subject `order`: Payme,
 *    Click, Paylov), the till's (subject `order_bill` of one of its bills, and the `pos_refund` refunds of those) —
 *    each as {uuid, type: sale|refund, status, provider, url, issued_at}. `status` is `ready` (registered, `url` is the
 *    link), `pending` ("tayyorlanmoqda") or `unavailable` (refused, blocked or without a usable link: the customer asks
 *    the restaurant). An online payment whose receipt is not in fiscal_receipts yet says so from its payload.fiscal
 *    state (uuid null). Never the provider payload, an error or a problem code.
 *
 * `url` is only ever an https link on the tax committee's receipt host (qr_payments.uzqr.receipt_hosts, ofd.soliq.uz;
 * the UzQR check SendUzQrReceiptLink::allowedLink); any other value is dropped and the receipt is `unavailable`.
 *
 * Tenancy: the receipts are read for the given orders only (the caller's own) and must be of the order's store.
 * Advance and credit receipts and non-fiscal (sandbox) ones are left out. A fixed number of queries per list.
 */
final class CustomerOrderReceipts
{
    public const READY = 'ready';
    public const PENDING = 'pending';
    public const UNAVAILABLE = 'unavailable';

    /**
     * Sets `seller` and `receipts` on each order as (serialized) relations: the list stays a list of orders, and
     * nothing is ever written back (a relation is not a column).
     *
     * @param  Collection<int, Order>  $orders
     */
    public function attach(Collection $orders): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $stores = $this->storesWithoutSnapshot($orders);
        $receipts = $this->receiptsOf($orders);
        $payments = Payment::query()
            ->whereIn('order_id', $this->idsOf($orders))
            ->whereIn('status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value])
            ->orderBy('id')
            ->get(['id', 'order_id', 'provider', 'status', 'payload'])
            ->groupBy('order_id');

        foreach ($orders as $order) {
            $snapshot = $order->seller_snapshot;
            $store = $stores->get($order->store_id);
            $seller = is_array($snapshot) ? SellerDetails::fromSnapshot($snapshot) : ($store !== null ? SellerDetails::of($store) : null);

            $order->setRelation('seller', $seller !== null ? collect($seller) : null);
            $order->setRelation('receipts', collect($this->forOrder(collect($receipts[$order->id] ?? []), $payments->get($order->id, collect()))));
        }
    }

    /**
     * The link a customer may be given: https on the tax committee's host, or null.
     */
    public static function publicUrl(?string $url): ?string
    {
        return is_string($url) && $url !== '' && SendUzQrReceiptLink::allowedLink($url) ? $url : null;
    }

    /**
     * The order a receipt is for (its subject: the order, one of its bills, or a till refund of one), of the
     * receipt's own store; null for any other subject.
     */
    public static function orderOf(FiscalReceipt $receipt): ?Order
    {
        $orderId = match ($receipt->subject_type) {
            'order' => $receipt->subject_id,
            OrderBill::FISCAL_SUBJECT => OrderBill::whereKey($receipt->subject_id)->where('store_id', $receipt->store_id)->value('order_id'),
            PosRefund::FISCAL_SUBJECT => PosRefund::whereKey($receipt->subject_id)->where('store_id', $receipt->store_id)->value('order_id'),
            default => null,
        };

        return $orderId !== null ? Order::whereKey($orderId)->where('store_id', $receipt->store_id)->first() : null;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Store>
     */
    private function storesWithoutSnapshot(Collection $orders): Collection
    {
        $ids = $orders->filter(fn (Order $order) => !is_array($order->seller_snapshot))->pluck('store_id')->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Store::withTrashed()->whereIn('id', $ids)
            ->get(['id', 'legal_name', 'tin', 'tax_number', 'legal_address', 'phone', 'complaint_contact'])
            ->keyBy('id');
    }

    /**
     * The sale and refund receipts of the orders, by order id.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, list<FiscalReceipt>>
     */
    private function receiptsOf(Collection $orders): array
    {
        $orderIds = $this->idsOf($orders);
        $storeOf = $orders->mapWithKeys(fn (Order $order) => [$order->id => (int) $order->store_id]);
        $columns = [
            'fiscal_receipts.id', 'fiscal_receipts.uuid', 'fiscal_receipts.store_id', 'fiscal_receipts.type', 'fiscal_receipts.status',
            'fiscal_receipts.driver', 'fiscal_receipts.provider', 'fiscal_receipts.qr_url', 'fiscal_receipts.issued_at',
            'fiscal_receipts.subject_type', 'fiscal_receipts.subject_id', 'fiscal_receipts.original_receipt_id',
        ];
        $fiscal = fn () => FiscalReceipt::query()
            ->whereIn('fiscal_receipts.type', [FiscalReceiptType::SALE->value, FiscalReceiptType::REFUND->value])
            ->where('fiscal_receipts.non_fiscal', false);

        // The online payments' receipts and the bills' (one query; a bill tells its order)
        $rows = $fiscal()
            ->leftJoin('order_bills', function ($join) {
                $join->on('order_bills.id', '=', 'fiscal_receipts.subject_id')
                    ->where('fiscal_receipts.subject_type', '=', OrderBill::FISCAL_SUBJECT);
            })
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('fiscal_receipts.subject_type', 'order')->whereIn('fiscal_receipts.subject_id', $orderIds))
                ->orWhere(fn ($query) => $query->where('fiscal_receipts.subject_type', OrderBill::FISCAL_SUBJECT)
                    ->whereIn('fiscal_receipts.subject_id', DB::table('order_bills')->whereIn('order_id', $orderIds)->select('id'))))
            ->get([...$columns, 'order_bills.order_id as bill_order_id']);

        $orderOf = [];
        foreach ($rows as $row) {
            $orderOf[$row->id] = $row->subject_type === 'order' ? (int) $row->subject_id : (int) $row->getAttribute('bill_order_id');
        }

        // The till's refunds: they name the bill's sale receipt they refund
        $billSales = $rows->filter(fn (FiscalReceipt $row) => $row->subject_type === OrderBill::FISCAL_SUBJECT && $row->type === FiscalReceiptType::SALE)->modelKeys();
        if ($billSales !== []) {
            foreach ($fiscal()->where('fiscal_receipts.type', FiscalReceiptType::REFUND->value)
                ->where('fiscal_receipts.subject_type', PosRefund::FISCAL_SUBJECT)
                ->whereIn('fiscal_receipts.original_receipt_id', $billSales)
                ->get($columns) as $refund) {
                $orderOf[$refund->id] = $orderOf[(int) $refund->original_receipt_id] ?? 0;
                $rows->push($refund);
            }
        }

        $byOrder = [];
        foreach ($rows->unique('id') as $row) {
            $orderId = $orderOf[$row->id] ?? 0;
            // Tenancy: only a receipt of the order's own store
            if ($orderId > 0 && $storeOf->get($orderId) === (int) $row->store_id) {
                $byOrder[$orderId][] = $row;
            }
        }

        return $byOrder;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<int>
     */
    private function idsOf(Collection $orders): array
    {
        return $orders->map(fn (Order $order) => (int) $order->id)->values()->all();
    }

    /**
     * @param  Collection<int, FiscalReceipt>  $receipts
     * @param  Collection<int, Payment>  $payments
     * @return list<array{uuid: string|null, type: string, status: string, provider: string|null, url: string|null, issued_at: string|null}>
     */
    private function forOrder(Collection $receipts, Collection $payments): array
    {
        $entries = $receipts
            ->sortBy(fn (FiscalReceipt $receipt) => [$receipt->type === FiscalReceiptType::SALE ? 0 : 1, $receipt->issued_at?->getTimestamp() ?? PHP_INT_MAX, $receipt->id])
            ->map(fn (FiscalReceipt $receipt) => $this->entry($receipt))
            ->values()
            ->all();

        $hasSale = $receipts->contains(fn (FiscalReceipt $receipt) => $receipt->type === FiscalReceiptType::SALE);
        $hasRefund = $receipts->contains(fn (FiscalReceipt $receipt) => $receipt->type === FiscalReceiptType::REFUND);

        // An online payment whose receipt is not recorded yet: its state from payload.fiscal (never its problems)
        foreach ($payments as $payment) {
            $fiscal = is_array($payment->payload['fiscal'] ?? null) ? $payment->payload['fiscal'] : [];
            $provider = is_string($fiscal['provider'] ?? null) ? $fiscal['provider'] : (string) $payment->provider;

            if (!$hasSale && ($status = $this->stateOf($fiscal['status'] ?? null)) !== null) {
                array_unshift($entries, $this->pendingEntry('sale', $status, $provider));
                $hasSale = true;
            }

            if (!$hasRefund && $payment->status === PaymentStatus::REFUNDED) {
                $refundState = $this->stateOf($fiscal['refund_status'] ?? null);
                // Payme reports a refund receipt the tax office refused in its CANCEL fiscal data
                $cancel = is_array($fiscal['cancel_data'] ?? null) ? $fiscal['cancel_data'] : null;
                if ($refundState === null && $cancel !== null && (int) ($cancel['status_code'] ?? 0) !== 0) {
                    $refundState = self::UNAVAILABLE;
                }
                if ($refundState !== null) {
                    $entries[] = $this->pendingEntry('refund', $refundState, $provider);
                    $hasRefund = true;
                }
            }
        }

        return $entries;
    }

    /**
     * @return array{uuid: string|null, type: string, status: string, provider: string|null, url: string|null, issued_at: string|null}
     */
    private function entry(FiscalReceipt $receipt): array
    {
        $url = $receipt->status === FiscalReceiptStatus::ISSUED ? self::publicUrl($receipt->qr_url) : null;
        $status = match ($receipt->status) {
            FiscalReceiptStatus::ISSUED => $url !== null ? self::READY : self::UNAVAILABLE,
            FiscalReceiptStatus::PENDING => self::PENDING,
            FiscalReceiptStatus::FAILED => self::UNAVAILABLE,
        };

        return [
            'uuid' => $receipt->uuid,
            'type' => $receipt->type->value,
            'status' => $status,
            'provider' => $receipt->driver === 'external' ? $receipt->provider : 'pos',
            'url' => $url,
            'issued_at' => $receipt->status === FiscalReceiptStatus::ISSUED ? $receipt->issued_at?->toIso8601String() : null,
        ];
    }

    /**
     * @return array{uuid: null, type: string, status: string, provider: string|null, url: null, issued_at: null}
     */
    private function pendingEntry(string $type, string $status, string $provider): array
    {
        return ['uuid' => null, 'type' => $type, 'status' => $status, 'provider' => $provider !== '' ? mb_substr($provider, 0, 32) : null, 'url' => null, 'issued_at' => null];
    }

    /**
     * OnlineFiscalRecorder's state of a receipt not in fiscal_receipts yet, as the customer sees it; null for
     * none or `recorded` (the receipt row speaks then).
     */
    private function stateOf(mixed $status): ?string
    {
        return match ($status) {
            OnlineFiscalRecorder::STATUS_PENDING => self::PENDING,
            OnlineFiscalRecorder::STATUS_BLOCKED, OnlineFiscalRecorder::STATUS_FAILED => self::UNAVAILABLE,
            default => null,
        };
    }
}
