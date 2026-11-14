<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Booking\AdvanceLedgerException;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\BanquetEvent;
use App\Models\BanquetSettlement;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * P6-54: the AdvanceLedger of an event's order (banquet_events.order_id): the advances the payment schedule holds
 * (BanquetAdvances) less what the final settlement already offset. They are offset only against the settlement's bill
 * (PosTender::PREPAYMENT on POST /pos/bills/{uuid}/payments, which the settle action sends itself), so an event-day
 * bill paid before the settlement never takes them. Under the settlement's row lock; the same amount for the same
 * bill again is a no-op (a retried payment), like BookingAdvanceLedger.
 *
 * Every other order goes to the booking deposits ledger (CompositeAdvanceLedger).
 */
final class BanquetPrepaymentLedger implements AdvanceLedger
{
    public static function eventOf(Order $order): ?BanquetEvent
    {
        return $order->getKey() !== null ? BanquetEvent::where('order_id', $order->getKey())->first() : null;
    }

    public function available(Order $order): Money
    {
        $event = self::eventOf($order);
        if ($event === null) {
            return Money::zero();
        }
        $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->first();

        return Money::max(Money::zero(), BanquetAdvances::total($event)->minus($settlement->applied_amount ?? '0'));
    }

    public function apply(Order $order, Money $amount, string $billUuid): void
    {
        if (!$amount->isPositive()) {
            throw new AdvanceLedgerException("Avansdan ayiriladigan summa musbat bo'lishi kerak.", 'invalid_amount');
        }
        $event = self::eventOf($order);
        if ($event === null) {
            throw new AdvanceLedgerException("Bu buyurtma tadbirga bog'lanmagan: ayiriladigan avans yo'q.", 'no_booking');
        }

        DB::transaction(function () use ($event, $amount, $billUuid) {
            BanquetEvent::whereKey($event->id)->lockForUpdate()->first();
            $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->lockForUpdate()->first();
            if ($settlement === null || $settlement->bill_uuid !== $billUuid) {
                throw new AdvanceLedgerException("Tadbir zakladlari faqat yakuniy hisob schyotida ayiriladi: avval tadbirni yakunlang.", 'not_settlement_bill', 409);
            }

            $already = Money::zero();
            foreach ($settlement->applications ?? [] as $application) {
                if ($application['bill_uuid'] === $billUuid) {
                    $already = $already->plus($application['amount']);
                }
            }
            if ($already->isPositive()) {
                if ($already->equals($amount)) {
                    return;
                }

                throw new AdvanceLedgerException('Bu schyotga avans allaqachon boshqa summada ayirilgan (' . $already->toString() . " so'm).", 'already_applied', 409, ['applied' => $already->toString()]);
            }

            $available = BanquetAdvances::total($event)->minus($settlement->applied_amount);
            if ($amount->isGreaterThan($available)) {
                throw new AdvanceLedgerException('Avans yetarli emas: mavjud ' . $available->toString() . " so'm, so'ralgan " . $amount->toString() . " so'm.", 'insufficient_advance', 422, ['available' => $available->toString(), 'requested' => $amount->toString()]);
            }

            $applications = $settlement->applications ?? [];
            $applications[] = ['bill_uuid' => $billUuid, 'amount' => $amount->toString(), 'at' => now()->toJSON()];
            $settlement->forceFill([
                'applied_amount' => Money::of($settlement->applied_amount)->plus($amount)->toString(),
                'applications' => $applications,
            ])->save();
        });
    }

    public function reverse(Order $order, Money $amount, string $billUuid): void
    {
        if (!$amount->isPositive()) {
            throw new AdvanceLedgerException("Avansga qaytariladigan summa musbat bo'lishi kerak.", 'invalid_amount');
        }
        $event = self::eventOf($order);

        DB::transaction(function () use ($event, $amount, $billUuid) {
            $settlement = $event !== null ? BanquetSettlement::where('banquet_event_id', $event->id)->lockForUpdate()->first() : null;
            $applied = Money::zero();
            foreach ($settlement->applications ?? [] as $application) {
                if ($application['bill_uuid'] === $billUuid) {
                    $applied = $applied->plus($application['amount']);
                }
            }
            if ($settlement === null || $amount->isGreaterThan($applied)) {
                throw new AdvanceLedgerException("Bu schyotga avansdan {$applied->toString()} so'm ayirilgan: undan ko'pini qaytarib bo'lmaydi.", 'reverse_exceeds_applied', 409, ['applied' => $applied->toString()]);
            }

            $applications = $settlement->applications ?? [];
            $applications[] = ['bill_uuid' => $billUuid, 'amount' => $amount->negated()->toString(), 'at' => now()->toJSON(), 'reversed' => true];
            $settlement->forceFill([
                'applied_amount' => Money::of($settlement->applied_amount)->minus($amount)->toString(),
                'applications' => $applications,
            ])->save();
        });
    }
}
