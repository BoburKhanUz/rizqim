<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * AdvanceLedger over booking_payments (P3-17): an order opened from a booking (orders.booking_id) may offset the
 * booking's deposits against its bills (PosTender::PREPAYMENT, P3-18).
 *
 * available(): what the booking's taken deposits still hold (amount − applied − refunded − retained).
 * apply(): offsets `amount` against the bill, oldest deposit first, under the booking's row lock and the deposits' row
 * locks, so two bills paid at the same moment never take the same money twice. Applying the same amount to the same
 * bill again is a no-op (a retried payment); a different amount for a bill that already has one is refused. Call it in
 * the transaction that records the bill's payment: if that rolls back, so does the offset.
 */
class BookingAdvanceLedger implements AdvanceLedger
{
    public function available(Order $order): Money
    {
        $bookingId = $order->getAttribute('booking_id');
        if ($bookingId === null) {
            return Money::zero();
        }

        return BookingPayment::where('booking_id', (int) $bookingId)
            ->where('status', BookingPaymentStatus::TAKEN->value)
            ->get()
            ->reduce(fn (Money $sum, BookingPayment $payment) => $sum->plus($payment->remaining()), Money::zero());
    }

    public function apply(Order $order, Money $amount, string $billUuid): void
    {
        if (!$amount->isPositive()) {
            throw new AdvanceLedgerException("Avansdan ayiriladigan summa musbat bo'lishi kerak.", 'invalid_amount');
        }

        if (!\Illuminate\Support\Str::isUuid($billUuid)) {
            throw new AdvanceLedgerException("Schyot identifikatori noto'g'ri.", 'invalid_bill');
        }

        $bookingId = $order->getAttribute('booking_id');
        if ($bookingId === null) {
            throw new AdvanceLedgerException("Bu buyurtma bronga bog'lanmagan: ayiriladigan avans yo'q.", 'no_booking');
        }

        DB::transaction(function () use ($bookingId, $amount, $billUuid) {
            $booking = Booking::whereKey((int) $bookingId)->lockForUpdate()->first();
            if ($booking === null) {
                throw new AdvanceLedgerException('Bron topilmadi.', 'no_booking');
            }

            $payments = BookingPayment::where('booking_id', $booking->id)->orderBy('id')->lockForUpdate()->get();

            $already = Money::zero();
            foreach ($payments as $payment) {
                foreach ($payment->applications ?? [] as $application) {
                    if ($application['bill_uuid'] === $billUuid) {
                        $already = $already->plus($application['amount']);
                    }
                }
            }
            if ($already->isPositive()) {
                if ($already->equals($amount)) {
                    return; // the same offset again (a retried payment)
                }

                throw new AdvanceLedgerException('Bu schyotga avans allaqachon boshqa summada ayirilgan (' . $already->toString() . " so'm).", 'already_applied', 409, [
                    'applied' => $already->toString(),
                ]);
            }

            $taken = $payments->filter(fn (BookingPayment $payment) => $payment->status === BookingPaymentStatus::TAKEN);
            $available = $taken->reduce(fn (Money $sum, BookingPayment $payment) => $sum->plus($payment->remaining()), Money::zero());
            if ($amount->isGreaterThan($available)) {
                throw new AdvanceLedgerException(
                    'Avans yetarli emas: mavjud ' . $available->toString() . " so'm, so'ralgan " . $amount->toString() . " so'm.",
                    'insufficient_advance',
                    422,
                    ['available' => $available->toString(), 'requested' => $amount->toString()],
                );
            }

            $left = $amount;
            foreach ($taken as $payment) {
                if (!$left->isPositive()) {
                    break;
                }

                $part = Money::min($left, $payment->remaining());
                if (!$part->isPositive()) {
                    continue;
                }

                $applied = Money::of($payment->applied_amount)->plus($part);
                $applications = $payment->applications ?? [];
                $applications[] = ['bill_uuid' => $billUuid, 'amount' => $part->toString(), 'at' => now()->toJSON()];

                $payment->forceFill([
                    'applied_amount' => $applied->toString(),
                    'applied_bill_uuid' => $billUuid,
                    'applications' => $applications,
                ]);
                if (!$payment->remaining()->isPositive()) {
                    $payment->status = BookingPaymentStatus::APPLIED;
                }
                $payment->save();

                $left = $left->minus($part);
            }
        });
    }

    /**
     * Review P3-35: the bill's offset given back (its prepayment part refunded), newest deposit first, recorded as a
     * negative application so the deposit's history stays whole. A deposit that holds money again is `taken`.
     */
    public function reverse(Order $order, Money $amount, string $billUuid): void
    {
        if (!$amount->isPositive()) {
            throw new AdvanceLedgerException("Avansga qaytariladigan summa musbat bo'lishi kerak.", 'invalid_amount');
        }

        $bookingId = $order->getAttribute('booking_id');
        DB::transaction(function () use ($bookingId, $order, $amount, $billUuid) {
            $booking = $bookingId !== null ? Booking::whereKey((int) $bookingId)->lockForUpdate()->first() : null;
            // The deposits the bill took, wherever the booking is now (the order may have lost it in a merge)
            $payments = BookingPayment::query()
                ->where(fn ($query) => $query->where('order_id', $order->id)->when($booking !== null, fn ($query) => $query->orWhere('booking_id', $booking?->id)))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $net = [];
            foreach ($payments as $payment) {
                $sum = Money::zero();
                foreach ($payment->applications ?? [] as $application) {
                    if ($application['bill_uuid'] === $billUuid) {
                        $sum = $sum->plus($application['amount']);
                    }
                }
                $net[$payment->id] = $sum;
            }
            $applied = Money::sum(...array_values($net));
            if ($amount->isGreaterThan($applied)) {
                throw new AdvanceLedgerException(
                    "Bu schyotga avansdan {$applied->toString()} so'm ayirilgan: undan ko'pini qaytarib bo'lmaydi.",
                    'reverse_exceeds_applied',
                    409,
                    ['applied' => $applied->toString()],
                );
            }

            $left = $amount;
            foreach ($payments as $payment) {
                if (!$left->isPositive()) {
                    break;
                }
                $part = Money::min($left, $net[$payment->id]);
                if (!$part->isPositive()) {
                    continue;
                }

                $applications = $payment->applications ?? [];
                $applications[] = ['bill_uuid' => $billUuid, 'amount' => $part->negated()->toString(), 'at' => now()->toJSON(), 'reversed' => true];
                $payment->forceFill([
                    'applied_amount' => Money::of($payment->applied_amount)->minus($part)->toString(),
                    'applications' => $applications,
                ]);
                if ($payment->remaining()->isPositive()) {
                    $payment->status = BookingPaymentStatus::TAKEN;
                }
                $payment->save();

                $left = $left->minus($part);
            }

            if ($booking !== null) {
                $booking->version = $booking->version + 1;
                $booking->save();
            }
        });
    }
}
