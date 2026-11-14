<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\PosActionException;
use App\Models\BanquetEvent;
use App\Models\BanquetSettlement;
use App\Models\Order;
use App\Models\OrderBill;
use Closure;

/**
 * Review P6 wave 5 (C1): an in-progress banquet event's order (banquet_events.order_id) is billed and paid only
 * through its final settlement, so its advances are always offset on the ONE settlement bill and the event can always
 * be settled. While the event is in progress:
 *
 *  - a bill is made on its order only by the settlement itself (BanquetSettlementService::settle runs BillService
 *    inside settling()), or once the settlement's bill is paid (a later add-on: its own bill and receipt, C10);
 *  - a payment is taken only on the settlement's bill, or on any bill of the order once that bill is paid;
 *  - everything else is 409 `banquet_settle_first` with `event_uuid`, sending the cashier to "Yakuniy hisob".
 *
 * Every other order (no event, or its event confirmed, settled or cancelled) is never touched. A payment a till took
 * offline (synced later) is never refused here: the money was taken.
 */
final class BanquetBillingGuard
{
    private static int $settling = 0;

    /**
     * Runs $work as the settlement (its bills are allowed).
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function settling(Closure $work): mixed
    {
        self::$settling++;
        try {
            return $work();
        } finally {
            self::$settling--;
        }
    }

    /**
     * Before a bill is made (or made again) on the order.
     *
     * @throws PosActionException
     */
    public static function assertBillable(Order $order): void
    {
        if (self::$settling > 0 || ($event = self::eventOf($order)) === null) {
            return;
        }
        $bill = self::settlementBill($event);
        if ($bill !== null && $bill->status === BillStatus::PAID) {
            return;
        }

        throw self::refusal($event);
    }

    /**
     * Before a payment is taken on the bill.
     *
     * @throws PosActionException
     */
    public static function assertPayable(Order $order, OrderBill $bill): void
    {
        if (self::$settling > 0 || ($event = self::eventOf($order)) === null) {
            return;
        }
        $settlement = self::settlementBill($event);
        if ($settlement !== null && ($settlement->id === $bill->id || $settlement->status === BillStatus::PAID)) {
            return;
        }

        throw self::refusal($event);
    }

    private static function eventOf(Order $order): ?BanquetEvent
    {
        return BanquetEvent::where('order_id', $order->id)->where('status', BanquetEventStatus::IN_PROGRESS->value)->first();
    }

    private static function settlementBill(BanquetEvent $event): ?OrderBill
    {
        $billId = BanquetSettlement::where('banquet_event_id', $event->id)->value('bill_id');

        return $billId !== null ? OrderBill::find($billId) : null;
    }

    private static function refusal(BanquetEvent $event): PosActionException
    {
        return PosActionException::conflict('banquet_settle_first', "Bu tadbir buyurtmasi: schyot va to'lov faqat \"Yakuniy hisob\" orqali (tadbir zakladlari shu schyotda ayiriladi).", [
            'event_uuid' => (string) $event->uuid,
        ]);
    }
}
