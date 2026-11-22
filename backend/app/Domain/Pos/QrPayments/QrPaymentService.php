<?php

namespace App\Domain\Pos\QrPayments;

use App\Domain\Pos\Billing\BillableOrder;
use App\Domain\Pos\Billing\BillFiscalizer;
use App\Domain\Pos\Billing\BillService;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\PaymentStatus;
use App\Events\Broadcast\QrPaymentBroadcast;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * P6-41/P6-42: a dynamic QR at the till (POS_SPEC 2.9 "To'lov"). The cashier asks for one for a bill (all of what it
 * still owes, or a part of a mixed payment); the till prints it on the bill and shows it on the customer display. The
 * customer pays it with Click, Payme or a bank app (UzQR); the provider's confirmation becomes a `qr` payment of the
 * bill (PaymentService, `reference` = the provider's transaction id) with no cashier action, exactly once, in the
 * shift the QR was made in (else the till's open shift), and the bill's sale receipt is issued by Rizqim
 * (BillFiscalizer) — the provider's own fiscalisation never runs for it (no double receipt, POS_SPEC §4).
 *
 *  - one pending QR per bill: a new one cancels the old; a change of the bill (rebilled, split, voided, paid in
 *    another way, a discount) cancels it (cancelStale(), on order.bill_changed) and its confirmation is refused;
 *  - it expires after the branch's `expiry_minutes` unless the provider already started taking the money
 *    (`held_at`: Click Prepare, Payme CreateTransaction), which holds it for qr_payments.hold_minutes more (Payme:
 *    qr_payments.payme.hold_minutes, its own 12 h pending window; review P6W4 C9); a Click Complete with an error
 *    releases the hold (release(), C10); a UzQR's gateway is asked before it expires and a late "paid" is taken (C5);
 *  - a confirmation the bill cannot take (expired, cancelled, bill changed, no open shift) is refused, so the provider
 *    does not take the money (Click reverses on a Complete error, Payme times the transaction out);
 *  - offline the till cannot ask for one (it needs the server): the static QR confirmed by hand stays.
 *
 * Review 11 (Wave P, the UzQR operator): once the operator says "paid" the money ends as a payment of the bill or as
 * a visible paid_unmatched record (owner alert, GET /owner/pos/qr-payments/unmatched), never dropped; a UzQR that
 * ended here while the customer may still pay it at the operator (its lifetime not over there, the cancel not
 * acknowledged, the operator not answering) is asked again (next_check_at) until the operator settles it.
 */
final class QrPaymentService
{
    public function __construct(
        private readonly QrProviders $providers,
        private readonly QrPaymentSettings $settings,
        private readonly ShiftService $shifts,
        private readonly BillFiscalizer $fiscalizer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws PosActionException
     */
    public function create(User $user, OrderBill $bill, ?string $provider, ?string $amount, ?string $shiftUuid, ?Device $device, ?Closure $applied = null): PosQrPayment
    {
        $order = Order::findOrFail($bill->order_id);
        $branch = Branch::findOrFail((int) $order->branch_id);
        $store = Store::findOrFail((int) $order->store_id);
        $settings = $this->settings->present($branch);

        $provider = $provider !== null ? QrProvider::from($provider) : QrProvider::tryFrom((string) $settings['default_provider']);
        if ($provider === null || !in_array($provider->value, $settings['providers'], true)) {
            throw PosActionException::invalid('qr_provider_disabled', "Bu filialda " . ($provider?->label() ?? 'QR') . " orqali to'lov yoqilmagan.");
        }
        $driver = $this->providers->for($store, $provider);
        if (!$driver->configured($store)) {
            throw PosActionException::conflict('provider_not_configured', $provider === QrProvider::UZQR
                ? "UzQR shlyuzi sozlanmagan: egasi fiskal sozlamalarda UzQR drayverini tanlasin."
                : "{$provider->label()} do'konning o'z merchant hisobiga ulanmagan: egasi to'lov sozlamalarida {$provider->label()} kalitlarini kiritib, \"o'z hisobi\"ni yoqsin.", ['provider' => $provider->value]);
        }
        // Wave P: the real UzQR driver needs this till's own key and the store's STIR; without them the till offers
        // the other methods (bootstrap) and a request is refused before anything is sent
        if ($driver instanceof Providers\UzQrProvider && ($why = $driver->readyFor($device, $store)) !== null) {
            throw PosActionException::conflict('uzqr_till_not_configured', $why, ['provider' => $provider->value]);
        }
        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $order->branch_id);
        if ($bill->status->isPayable()) {
            // A line that cannot be receipted refuses the QR before the customer pays (422 mxik_missing)
            $this->fiscalizer->lines($bill);
        }

        $due = $this->assertPayable($order, $bill);
        $amount = $amount !== null ? Money::of($amount) : $due;
        if (!$amount->isPositive()) {
            throw PosActionException::invalid('amount_invalid', "To'lov summasi musbat bo'lishi kerak.");
        }
        if ($amount->isGreaterThan($due)) {
            throw PosActionException::invalid('overpayment', "QR summasi schyotdagi qarzdan ko'p: qoldiq {$due->toString()} so'm.", ['due' => $due->toString()]);
        }

        $qr = new PosQrPayment([
            'uuid' => (string) Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'bill_id' => $bill->id,
            'bill_uuid' => $bill->uuid,
            'provider' => $provider,
            'driver' => $driver->driver(),
            'amount' => $amount->toString(),
            'status' => QrPaymentStatus::PENDING,
            'expires_at' => now()->addMinutes($settings['expiry_minutes']),
            'shift_id' => $shift->id,
            'device_id' => $device?->id,
            'created_by' => $user->id,
        ]);
        try {
            $checkout = $driver->checkout($qr, $store);
        } catch (UzQr\UzQrOperatorException) {
            // Wave P: the operator did not give a QR (down, refused): nothing is kept, the customer pays another way
            throw PosActionException::conflict('provider_unavailable', "UzQR hozir QR bermadi (aloqa yo'q yoki rad etdi): boshqa to'lov usulini tanlang yoki birozdan so'ng qayta urinib ko'ring.", ['provider' => $provider->value]);
        }
        $qr->checkout_payload = $checkout->payload;
        $qr->gateway_reference = $checkout->reference;

        try {
            [$qr, $replaced] = $this->saveCreated($qr, $order, $bill, $due, $user, $device, $applied);
        } catch (\Throwable $e) {
            $this->keepOrphan($qr, $store, $e);

            throw $e;
        }

        foreach ($replaced as $old) {
            $this->announce($old, $store);
        }

        return $qr;
    }

    /**
     * @return array{0: PosQrPayment, 1: list<PosQrPayment>}
     */
    private function saveCreated(PosQrPayment $qr, Order $order, OrderBill $bill, Money $due, User $user, ?Device $device, ?Closure $applied): array
    {
        return DB::transaction(function () use ($qr, $order, $bill, $due, $user, $device, $applied) {
            $bill = OrderBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (!$this->assertPayable($order->refresh(), $bill)->equals($due)) {
                throw PosActionException::conflict('bill_changed', "Schyot hozirgina o'zgardi: QR'ni qayta so'rang.");
            }
            $replaced = [];
            foreach (PosQrPayment::where('bill_id', $bill->id)->where('status', QrPaymentStatus::PENDING->value)->lockForUpdate()->get() as $old) {
                $replaced[] = $this->markCancelled($old, 'replaced');
            }
            $qr->save();

            $this->audit->record('pos.qr_created', $order, [], [], [
                'qr' => $qr->uuid,
                'bill' => $bill->uuid,
                'provider' => $qr->provider->value,
                'amount' => $qr->amount,
                'replaced' => array_map(fn (PosQrPayment $old) => $old->uuid, $replaced),
            ], ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return [$qr, $replaced];
        });
    }

    /**
     * Review 11 LOW: the operator already made an invoice for this request but it could not be saved (the bill
     * changed meanwhile, ...): it is kept, cancelled, so the operator's invoice is cancelled there and watched like
     * any other ended UzQR (a customer who paid it anyway is seen: paid_unmatched).
     */
    private function keepOrphan(PosQrPayment $qr, Store $store, \Throwable $e): void
    {
        if ($qr->provider !== QrProvider::UZQR || $qr->gateway_reference === null) {
            return;
        }
        // A save inside the rolled-back transaction left the model looking saved: a fresh copy is kept
        $orphan = $qr->replicate();
        $orphan->forceFill([
            'uuid' => $qr->uuid,
            'status' => QrPaymentStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => mb_substr($e instanceof PosActionException ? $e->reason : 'create_failed', 0, 64),
            'next_check_at' => now(),
        ]);
        try {
            $orphan->save();
        } catch (\Throwable $saveError) {
            Log::error('UzQR invoice made but not kept: cancel it in the operator\'s cabinet', ['qr' => $qr->uuid, 'invoice' => $qr->gateway_reference, 'error' => $saveError->getMessage()]);

            return;
        }
        $this->announce($orphan, $store);
    }

    /**
     * The cashier cancels a QR nobody paid (a paid one: 409 qr_already_paid; already over: returned as it is).
     *
     * @throws PosActionException
     */
    public function cancel(User $user, PosQrPayment $qr, ?string $reason, ?Device $device, ?Closure $applied = null): PosQrPayment
    {
        $cancelled = DB::transaction(function () use ($user, $qr, $reason, $device, $applied) {
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
            if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) {
                throw PosActionException::conflict('qr_already_paid', "Bu QR allaqachon to'langan: pulni qaytarish uchun qaytarish (refund) qiling.");
            }
            if ($qr->status !== QrPaymentStatus::PENDING) {
                return null;
            }
            $this->markCancelled($qr, 'cashier');
            $this->audit->record('pos.qr_cancelled', Order::find($qr->order_id), [], [], array_filter([
                'qr' => $qr->uuid,
                'bill' => $qr->bill_uuid,
                'reason' => $reason,
            ], fn ($value) => $value !== null), ['actor' => $user, 'device' => $device]);
            if ($applied) {
                $applied();
            }

            return $qr;
        });

        if ($cancelled instanceof PosQrPayment) {
            $this->announce($cancelled);

            return $cancelled;
        }

        return $qr->refresh();
    }

    /**
     * The request as it is now: a pending UzQR asks its gateway first (the till's poll; review P6W4 C5: a bank push
     * paid just before the expiry is accepted, money already moved), then expired when its time ran out.
     */
    public function refresh(PosQrPayment $qr): PosQrPayment
    {
        $qr->refresh();
        if ($qr->status === QrPaymentStatus::PENDING && $this->askUzQr($qr)) {
            return $qr->refresh();
        }
        if ($this->unsettledUzQr($qr)) {
            // Review 11 H1/H2: an ended UzQR the operator has not settled is asked on every read too
            $this->checkEnded($qr);

            return $qr->refresh();
        }
        if ($qr->status !== QrPaymentStatus::PENDING) {
            return $qr;
        }
        if ($this->isExpired($qr)) {
            return $this->expire($qr, true);
        }

        return $qr->refresh();
    }

    /**
     * The provider says the customer paid: the bill takes a `qr` payment (once). 'paid' on the real transition,
     * 'already_confirmed' for a repeat of the same transaction, 'already_paid' when another transaction paid it,
     * 'refused' when the bill cannot take it (the request is then cancelled or expired, so the money is not taken).
     *
     * Review P6W4 C5: $late — the UzQR gateway reports it paid (the money moved): accepted past its expiry (also once
     * expired by time); if the bill can no longer take it, it is marked for reconciliation (unmatched()), never dropped.
     * Review 11 H1: with no time window — whenever the operator's "paid" arrives, it ends as a payment ('paid') or
     * as paid_unmatched ('unmatched'), never a bare 'refused' (a cancelled one, a race lost to a cancel, a bill that
     * no longer takes it, a closed shift).
     *
     * @param  array<string, mixed>  $payload  what the provider sent (kept on the request)
     * @return 'paid'|'already_confirmed'|'already_paid'|'refused'|'unmatched'
     */
    public function confirm(PosQrPayment $qr, string $transactionId, array $payload = [], bool $late = false): string
    {
        $qr->refresh();
        if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) {
            return $qr->provider_transaction_id === $transactionId ? 'already_confirmed' : 'already_paid';
        }
        $late = $late && $qr->provider === QrProvider::UZQR;
        if ($late && $qr->status !== QrPaymentStatus::PENDING && !$this->takesLate($qr)) {
            // Cancelled here (the cashier, a newer QR, a bill change) or already kept: never taken, never dropped
            $this->unmatched($qr, $transactionId, 'paid_after_' . ($qr->cancel_reason ?? $qr->status->value));

            return 'unmatched';
        }
        if (!$late && $qr->status !== QrPaymentStatus::PENDING) {
            return 'refused';
        }
        if (!$late && $this->isExpired($qr)) {
            $this->expire($qr);

            return 'refused';
        }

        $bill = OrderBill::findOrFail($qr->bill_id);
        $user = User::find($qr->created_by);
        $device = $qr->device_id !== null ? Device::find($qr->device_id) : null;
        $shift = $this->shiftFor($qr, $device);
        if ($user === null || $shift === null) {
            if ($late) {
                $this->unmatched($qr, $transactionId, 'shift_closed');

                return 'unmatched';
            }
            $this->cancelBy($qr, 'shift_closed');

            return 'refused';
        }

        try {
            app(PaymentService::class)->pay(
                $user,
                $bill,
                [['tender' => PosTender::QR->value, 'amount' => Money::of($qr->amount)->toString(), 'reference' => mb_substr($transactionId, 0, 100)]],
                null,
                $shift->uuid,
                null,
                $device,
                function () use ($qr, $bill, $transactionId, $payload, $late) {
                    $locked = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== QrPaymentStatus::PENDING && !($late && $this->takesLate($locked))) {
                        throw new QrConfirmationLost();
                    }
                    $payment = Payment::where('bill_id', $bill->id)->where('provider', PosTender::QR->value)
                        ->where('status', PaymentStatus::PAID->value)->orderByDesc('id')->firstOrFail();
                    $payment->forceFill(['payload' => ['qr_payment_uuid' => $locked->uuid, 'qr_provider' => $locked->provider->value]])->save();
                    $locked->forceFill([
                        'status' => QrPaymentStatus::PAID,
                        'paid_at' => now(),
                        'cancelled_at' => null,
                        'cancel_reason' => null,
                        'provider_transaction_id' => $transactionId,
                        'payment_id' => $payment->id,
                        'shift_id' => $payment->shift_id,
                        'provider_payload' => array_merge($locked->provider_payload ?? [], $payload === [] ? [] : ['confirm' => $payload]),
                        'next_check_at' => null,
                        'check_failures' => 0,
                    ])->save();
                },
            );
        } catch (QrConfirmationLost) {
            $qr->refresh();
            if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED) {
                return $qr->provider_transaction_id === $transactionId ? 'already_confirmed' : 'already_paid';
            }
            if ($late) {
                // H1: a cancel won the race against the operator's "paid": kept for reconciliation
                $this->unmatched($qr, $transactionId, 'paid_after_' . ($qr->cancel_reason ?? $qr->status->value));

                return 'unmatched';
            }

            return 'refused';
        } catch (PosActionException $e) {
            $qr->refresh();
            if ($qr->status === QrPaymentStatus::PAID) {
                return $qr->provider_transaction_id === $transactionId ? 'already_confirmed' : 'already_paid';
            }
            Log::info('POS QR confirmation refused by the bill', ['qr' => $qr->uuid, 'reason' => $e->reason]);
            if ($late) {
                $this->unmatched($qr, $transactionId, $e->reason);

                return 'unmatched';
            }
            $this->cancelBy($qr, 'payment_refused:' . $e->reason);

            return 'refused';
        }

        $this->announce($qr->refresh());

        return 'paid';
    }

    /**
     * Whether the provider may still take the money for it (Click Prepare, Payme CheckPerform/Create/Perform): pending,
     * in time, and the bill still owes at least its amount. A stale one is cancelled (or expired) on the way.
     */
    public function payable(PosQrPayment $qr): bool
    {
        $qr->refresh();
        if ($qr->status !== QrPaymentStatus::PENDING) {
            return false;
        }
        if ($this->isExpired($qr)) {
            $this->expire($qr);

            return false;
        }
        if ($this->isStale($qr)) {
            $this->cancelBy($qr, 'bill_changed');

            return false;
        }

        return true;
    }

    /**
     * The provider started taking the money (Click Prepare, Payme CreateTransaction): the expiry waits for it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hold(PosQrPayment $qr, ?string $transactionId, array $payload = []): PosQrPayment
    {
        $qr->forceFill([
            'held_at' => $qr->held_at ?? now(),
            'provider_transaction_id' => $transactionId ?? $qr->provider_transaction_id,
            'provider_payload' => array_merge($qr->provider_payload ?? [], $payload),
        ])->save();

        return $qr;
    }

    /**
     * Review P6W4 C10: the provider gave up the transaction that held the request (Click Complete with error < 0): the
     * hold is released — pending again while in time (qr.pending: the till may show it or make a new one), else expired.
     */
    public function release(PosQrPayment $qr, string $transactionId): void
    {
        $released = DB::transaction(function () use ($qr, $transactionId) {
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
            if ($qr->status !== QrPaymentStatus::PENDING || $qr->held_at === null || $qr->provider_transaction_id !== $transactionId) {
                return null;
            }
            $qr->forceFill([
                'held_at' => null,
                'provider_transaction_id' => null,
                'provider_payload' => array_merge($qr->provider_payload ?? [], ['released' => ['transaction_id' => $transactionId, 'at' => now()->toIso8601String()]]),
            ])->save();

            return $qr;
        });
        if ($released === null) {
            return;
        }
        if ($this->isExpired($released)) {
            $this->expire($released);

            return;
        }
        $this->announce($released);
    }

    /**
     * Cancels the pending QRs of an order whose bill no longer takes them (order.bill_changed).
     */
    public function cancelStale(Order $order): void
    {
        foreach (PosQrPayment::where('order_id', $order->id)->where('status', QrPaymentStatus::PENDING->value)->get() as $qr) {
            if ($this->isStale($qr)) {
                $this->cancelBy($qr, 'bill_changed');
            }
        }
    }

    /**
     * Expires the pending QRs whose time ran out (the minute job); returns how many. Past $until (microtime) a UzQR
     * is expired without asking its gateway first: the ended-UzQR recheck asks it (now or the next run).
     */
    public function expireDue(?float $until = null): int
    {
        $count = 0;
        PosQrPayment::where('status', QrPaymentStatus::PENDING->value)->where('expires_at', '<', now())
            ->orderBy('id')->limit(500)->get()
            ->each(function (PosQrPayment $qr) use (&$count, $until) {
                if ($this->isExpired($qr)) {
                    $this->expire($qr, $until !== null && microtime(true) > $until);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Cancelled by the system or the provider (a reason code), when still pending.
     */
    public function cancelBy(PosQrPayment $qr, string $reason): void
    {
        $cancelled = DB::transaction(function () use ($qr, $reason) {
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();

            return $qr->status === QrPaymentStatus::PENDING ? $this->markCancelled($qr, $reason) : null;
        });
        if ($cancelled !== null) {
            $this->announce($cancelled);
        }
    }

    public function isExpired(PosQrPayment $qr): bool
    {
        if ($qr->status !== QrPaymentStatus::PENDING) {
            return false;
        }
        if ($qr->held_at !== null) {
            // Review P6W4 C9: a Payme transaction stays pending at Payme for its own window (12 h): a late
            // PerformTransaction is still taken (money not refused); the till shows "Mijoz to'lamoqda" meanwhile
            $hold = $qr->provider === QrProvider::PAYME ? (int) config('qr_payments.payme.hold_minutes', 720) : (int) config('qr_payments.hold_minutes', 10);

            return $qr->held_at->copy()->addMinutes($hold)->max($qr->expires_at)->isPast();
        }

        return $qr->expires_at->isPast();
    }

    /**
     * What the bill still owes; refuses a bill that cannot take a payment.
     *
     * @throws PosActionException
     */
    private function assertPayable(Order $order, OrderBill $bill): Money
    {
        if ($bill->status === BillStatus::PAID) {
            throw PosActionException::conflict('bill_paid', "Schyot to'langan.");
        }
        if ($bill->status === BillStatus::VOID) {
            throw PosActionException::conflict('bill_void', "Bu schyot bekor qilingan: buyurtmaning joriy schyotini oling.");
        }
        $paid = Payment::where('bill_id', $bill->id)->where('status', PaymentStatus::PAID->value)->get();
        $due = Money::of($bill->total)->minus(Money::sum(...$paid->pluck('amount')->all()));
        if (!$due->isPositive()) {
            throw PosActionException::conflict('bill_awaiting_fiscal', "Schyot to'liq to'langan, fiskal chek kutilmoqda. Chekni qayta yuboring.");
        }
        if ($paid->isEmpty() && $bill->basis !== BillableOrder::fingerprint($order)) {
            throw BillService::orderChanged();
        }

        return $due;
    }

    private function isStale(PosQrPayment $qr): bool
    {
        $bill = OrderBill::find($qr->bill_id);
        $order = Order::find($qr->order_id);
        if ($bill === null || $order === null) {
            return true;
        }
        try {
            return Money::of($qr->amount)->isGreaterThan($this->assertPayable($order, $bill));
        } catch (PosActionException) {
            return true;
        }
    }

    private function shiftFor(PosQrPayment $qr, ?Device $device): ?PosShift
    {
        $shift = $qr->shift_id !== null ? PosShift::find($qr->shift_id) : null;
        if ($shift !== null && $shift->isOpen()) {
            return $shift;
        }
        $current = $device !== null ? $this->shifts->current($device) : null;

        return $current !== null && (int) $current->branch_id === (int) $qr->branch_id ? $current : null;
    }

    /**
     * Review P6W4 C5 / review 11 H1: a UzQR only expired by time (nobody cancelled it) is taken by the bill when its
     * gateway reports it paid — whenever that answer comes (no time window: an outage may last hours).
     */
    private function takesLate(PosQrPayment $qr): bool
    {
        return $qr->provider === QrProvider::UZQR && $qr->payment_id === null
            && $qr->status === QrPaymentStatus::EXPIRED && $qr->cancel_reason === 'expired';
    }

    /**
     * Review 11 H2/H3: a UzQR that ended here (expired, cancelled) whose money the operator has not settled yet.
     */
    private function unsettledUzQr(PosQrPayment $qr): bool
    {
        return $qr->provider === QrProvider::UZQR && $qr->next_check_at !== null && $qr->payment_id === null
            && in_array($qr->status, [QrPaymentStatus::EXPIRED, QrPaymentStatus::CANCELLED], true);
    }

    /**
     * Whether the end of this request leaves money to watch at the operator (a UzQR with an invoice there).
     */
    private static function watched(PosQrPayment $qr): bool
    {
        return $qr->provider === QrProvider::UZQR && $qr->gateway_reference !== null && $qr->payment_id === null;
    }

    /**
     * Asks a UzQR's gateway (outside any transaction): paid — confirmed (late: past its expiry too); cancelled there —
     * cancelled. True when the answer settled it.
     */
    private function askUzQr(PosQrPayment $qr): bool
    {
        if ($qr->provider !== QrProvider::UZQR || $qr->gateway_reference === null) {
            return false;
        }
        $status = $this->uzqrStatus($qr);
        if (($status['status'] ?? null) === 'paid') {
            // Wave P: the operator's pay_id, RRN and card (last four) go with the confirmation (the receipt reads them)
            $this->confirm($qr, (string) ($status['transaction_id'] ?? $qr->gateway_reference), ['uzqr' => 'status'] + (array) ($status['details'] ?? []), true);

            return true;
        }
        if (($status['status'] ?? null) === 'mismatch') {
            // Wave P: paid, but not our amount: never taken, kept for reconciliation
            $this->unmatched($qr, (string) ($status['transaction_id'] ?? $qr->gateway_reference), 'amount_mismatch');

            return true;
        }
        if (($status['status'] ?? null) === 'cancelled' && $qr->status === QrPaymentStatus::PENDING) {
            $this->cancelBy($qr, 'provider_cancelled');
            PosQrPayment::whereKey($qr->id)->whereNull('operator_cancelled_at')->update(['operator_cancelled_at' => now()]);

            return true;
        }

        return false;
    }

    /**
     * The UzQR gateway's answer about the request (outside any transaction), or null when it gave none.
     *
     * @return array{status: string, transaction_id: string|null, details?: array<string, string>}|null
     */
    private function uzqrStatus(PosQrPayment $qr): ?array
    {
        $driver = $this->providers->of($qr);
        $gateway = $driver instanceof Providers\UzQrProvider ? $driver->gateway() : null;
        try {
            return $gateway?->status($qr, Store::findOrFail($qr->store_id));
        } catch (\Throwable $e) {
            Log::warning('UzQR status check failed: ' . $e->getMessage(), ['qr' => $qr->uuid]);

            return null;
        }
    }

    /**
     * Review 11 H2/H3/M1 (the minute job): asks the operator about the UzQRs that ended here but that it has not
     * settled (next_check_at due), the most overdue first, at most `per_run` of them and none started after
     * `run_seconds` (config qr_payments.uzqr.recheck): the rest wait for the next run, ahead of the newer ones. Paid:
     * taken (expired by time) or kept as paid_unmatched, never dropped and never counted twice. Returns how many it
     * settled.
     */
    public function recheckEndedUzQr(?int $limit = null, ?float $until = null): int
    {
        $limit ??= (int) config('qr_payments.uzqr.recheck.per_run', 50);
        $until ??= microtime(true) + (int) config('qr_payments.uzqr.recheck.run_seconds', 40);
        $settled = 0;
        // Tenancy: the minute job works for every store (no tenant context); each request is asked with its own
        // store's STIR and its own key, and settles into its own bill
        PosQrPayment::query()
            ->where('provider', QrProvider::UZQR->value)
            ->whereIn('status', [QrPaymentStatus::EXPIRED->value, QrPaymentStatus::CANCELLED->value])
            ->whereNull('payment_id')
            ->whereNotNull('next_check_at')
            ->where('next_check_at', '<=', now())
            ->orderBy('next_check_at')->orderBy('id')->limit(max(0, $limit))->get()
            ->each(function (PosQrPayment $qr) use (&$settled, $until) {
                if (microtime(true) > $until) {
                    return false;
                }
                try {
                    if ($this->checkEnded($qr)) {
                        $settled++;
                    }
                } catch (\Throwable $e) {
                    // Never lost: asked again later
                    Log::error('UzQR recheck failed: ' . $e->getMessage(), ['qr' => $qr->uuid]);
                    PosQrPayment::whereKey($qr->id)->whereNotNull('next_check_at')->update(['next_check_at' => now()->addMinutes(5), 'last_checked_at' => now()]);
                }

                return true;
            });

        return $settled;
    }

    /**
     * One ask about an ended UzQR the operator has not settled; plans the next one. True when it is settled now:
     * paid (taken or kept as paid_unmatched), or not paid and no longer payable at the operator — the cancel
     * acknowledged (or the operator says it is cancelled) and both its lifetime and the acknowledgement older than
     * `grace_minutes`. A cancel the operator did not acknowledge is sent again on each ask. No answer: backoff, never
     * given up; the owner is told once when it stays unsettled for long.
     */
    private function checkEnded(PosQrPayment $qr): bool
    {
        $driver = $this->providers->of($qr);
        if (!$driver instanceof Providers\UzQrProvider || $driver->gateway() === null) {
            // Its gateway is gone from this stand (a sandbox one where the sandbox is off): no money moved through it
            Log::warning('UzQR recheck stopped: its gateway is not registered', ['qr' => $qr->uuid, 'driver' => $qr->driver]);
            $qr->forceFill(['next_check_at' => null, 'last_checked_at' => now()])->save();

            return true;
        }
        $status = $this->uzqrStatus($qr);
        $qr->refresh();
        if (!$this->unsettledUzQr($qr)) {
            return true;
        }
        $word = $status['status'] ?? null;
        $transactionId = (string) ($status['transaction_id'] ?? $qr->gateway_reference);
        if ($word === 'paid') {
            $this->confirm($qr, $transactionId, ['uzqr' => 'recheck'] + (array) ($status['details'] ?? []), true);

            return true;
        }
        if ($word === 'mismatch') {
            $this->unmatched($qr, $transactionId, 'amount_mismatch');

            return true;
        }

        $now = now();
        if ($status === null) {
            $failures = $qr->check_failures + 1;
            $this->plan($qr, ['check_failures' => $failures, 'last_checked_at' => $now, 'next_check_at' => $now->copy()->addMinutes(self::backoff($failures))]);
            $this->alertUnsettled($qr, $failures);

            return false;
        }

        // Answered, not paid: pending, or cancelled there
        $acked = $qr->operator_cancelled_at ?? ($word === 'cancelled' ? $now : null);
        if ($acked === null && $this->cancelAtOperator($qr)) {
            $acked = $now;
        }
        $grace = (int) config('qr_payments.uzqr.recheck.grace_minutes', 10);
        $payableUntil = $qr->expires_at->copy()->addMinutes($grace);
        if ($acked !== null && $acked->copy()->addMinutes($grace)->gt($payableUntil)) {
            $payableUntil = $acked->copy()->addMinutes($grace);
        }
        if ($acked !== null && $now->gt($payableUntil)) {
            // Settled unpaid: nothing more to ask, and the key copy it kept is no longer needed (no refund can follow)
            $this->plan($qr, ['operator_cancelled_at' => $acked, 'check_failures' => 0, 'last_checked_at' => $now, 'next_check_at' => null, 'uzqr_api_key' => null]);

            return true;
        }
        $overdue = $acked === null && $now->gt($payableUntil);
        $this->plan($qr, [
            'operator_cancelled_at' => $acked,
            'check_failures' => 0,
            'last_checked_at' => $now,
            'next_check_at' => $now->copy()->addMinutes($overdue ? max(1, (int) config('qr_payments.uzqr.recheck.unacknowledged_minutes', 15)) : 1),
        ]);
        if ($overdue) {
            $this->alertUnsettled($qr, 0);
        }

        return false;
    }

    /**
     * Writes the recheck state of an ended UzQR only while it is still unsettled (a concurrent confirmation that
     * took it as a payment, or kept it as unmatched, is never undone: its key copy stays for its refunds).
     *
     * @param  array<string, mixed>  $fields
     */
    private function plan(PosQrPayment $qr, array $fields): void
    {
        PosQrPayment::whereKey($qr->id)
            ->whereNull('payment_id')
            ->whereNotNull('next_check_at')
            ->whereIn('status', [QrPaymentStatus::EXPIRED->value, QrPaymentStatus::CANCELLED->value])
            ->update($fields);
        $qr->forceFill($fields)->syncOriginal();
    }

    /**
     * Minutes until the next ask after $failures asks in a row with no answer: 1, 1, 1, 2, 4, 8, 16, then the cap.
     */
    private static function backoff(int $failures): int
    {
        $cap = max(1, (int) config('qr_payments.uzqr.recheck.max_backoff_minutes', 30));

        return $failures <= 3 ? 1 : (int) min($cap, 2 ** min(10, $failures - 3));
    }

    /**
     * Sends the cancel to the operator again (the first one was not acknowledged); true when it was.
     */
    private function cancelAtOperator(PosQrPayment $qr): bool
    {
        try {
            $this->providers->of($qr)->cancel($qr, Store::findOrFail($qr->store_id));
        } catch (\Throwable $e) {
            Log::warning('UzQR cancel not acknowledged: ' . $e->getMessage(), ['qr' => $qr->uuid]);

            return false;
        }

        return true;
    }

    /**
     * The owner is told once that an ended UzQR is still not settled `alert_after_minutes` after it ended (the
     * operator does not answer, or keeps it open): it may have been paid.
     */
    private function alertUnsettled(PosQrPayment $qr, int $failures): void
    {
        $after = (int) config('qr_payments.uzqr.recheck.alert_after_minutes', 60);
        if ($qr->alerted_at !== null || ($qr->cancelled_at ?? $qr->expires_at)->copy()->addMinutes($after)->isFuture()) {
            return;
        }
        $marked = PosQrPayment::whereKey($qr->id)->whereNull('alerted_at')->update(['alerted_at' => now()]);
        if ($marked === 0) {
            return;
        }
        $branch = Branch::find($qr->branch_id);
        app(\App\Domain\Pos\Accounts\PosOwnerAlerts::class)->send((int) $qr->store_id, implode("\n", array_filter([
            '⚠️ UzQR to\'lovi holati aniqlanmadi: ' . \App\Domain\Pos\Accounts\PosOwnerAlerts::sum($qr->amount),
            $branch?->name ? "Filial: {$branch->name}" : null,
            'QR ' . ($qr->cancelled_at ?? $qr->expires_at)->format('d.m.Y H:i') . ' da yopilgan, operator ' . ($failures > 0 ? "{$failures} marta javob bermadi" : "uni hali yopmadi") . '.',
            "Mijoz to'lagan bo'lishi mumkin: Rizqim so'rashda davom etadi va to'lov bo'lsa o'zi yozadi. uzqr.uz kabinetida invoys {$qr->gateway_reference} ni tekshiring.",
        ])));
    }

    /**
     * Review P6W4 C5: the gateway took the money but the bill can no longer take it (paid otherwise, changed, the shift
     * closed): kept for reconciliation — never dropped — with the provider's transaction (`cancel_reason`
     * paid_unmatched, audit pos.qr_paid_unmatched); the manager gives the money back from the gateway's cabinet.
     */
    private function unmatched(PosQrPayment $qr, string $transactionId, string $reason): void
    {
        $marked = DB::transaction(function () use ($qr, $transactionId, $reason) {
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
            if ($qr->status === QrPaymentStatus::PAID || $qr->status === QrPaymentStatus::REFUNDED || $qr->cancel_reason === 'paid_unmatched') {
                return null;
            }
            $qr->forceFill([
                'next_check_at' => null,
                'status' => $qr->status === QrPaymentStatus::PENDING ? QrPaymentStatus::CANCELLED : $qr->status,
                'cancelled_at' => $qr->cancelled_at ?? now(),
                'cancel_reason' => 'paid_unmatched',
                'provider_transaction_id' => mb_substr($transactionId, 0, 100),
                'provider_payload' => array_merge($qr->provider_payload ?? [], ['unmatched' => ['transaction_id' => $transactionId, 'reason' => $reason, 'at' => now()->toIso8601String()]]),
            ])->save();
            $this->audit->record('pos.qr_paid_unmatched', Order::find($qr->order_id), [], [], [
                'qr' => $qr->uuid,
                'bill' => $qr->bill_uuid,
                'provider' => $qr->provider->value,
                'amount' => $qr->amount,
                'transaction' => $transactionId,
                'reason' => $reason,
            ], ['store_id' => (int) $qr->store_id, 'branch_id' => (int) $qr->branch_id]);

            return $qr;
        });
        if ($marked !== null) {
            Log::error('POS QR paid at the provider but not taken by the bill: reconcile', ['qr' => $marked->uuid, 'reason' => $reason]);
            event(new QrPaymentBroadcast($marked, (string) Order::whereKey($marked->order_id)->value('uuid')));
            $this->alertUnmatched($marked, $transactionId, $reason);
        }
    }

    /**
     * Review 11 M4: money taken at the provider that no bill took reaches the owner (as the bank-terminal difference
     * does); the list is GET /owner/pos/qr-payments/unmatched.
     */
    private function alertUnmatched(PosQrPayment $qr, string $transactionId, string $reason): void
    {
        try {
            $branch = Branch::find($qr->branch_id);
            app(\App\Domain\Pos\Accounts\PosOwnerAlerts::class)->send((int) $qr->store_id, implode("\n", array_filter([
                "⚠️ {$qr->provider->label()} orqali " . \App\Domain\Pos\Accounts\PosOwnerAlerts::sum($qr->amount) . " to'langan, lekin hech bir schyotga yozilmadi",
                $branch?->name ? "Filial: {$branch->name}" : null,
                'Sabab: ' . $reason . ', tranzaksiya: ' . mb_substr($transactionId, 0, 64),
                "Pulni to'lov tizimi kabinetidan mijozga qaytaring yoki hisob-kitobda inobatga oling (Rizqim: QR to'lovlar → mos kelmaganlar).",
            ])));
        } catch (\Throwable $e) {
            Log::error('POS QR unmatched alert not sent: ' . $e->getMessage(), ['qr' => $qr->uuid]);
        }
    }

    private function expire(PosQrPayment $qr, bool $asked = false): PosQrPayment
    {
        // Review P6W4 C5: a UzQR's gateway is asked before it expires (a bank push paid at the last second)
        if (!$asked && $qr->status === QrPaymentStatus::PENDING && $this->askUzQr($qr)) {
            return $qr->refresh();
        }
        $expired = DB::transaction(function () use ($qr) {
            $qr = PosQrPayment::whereKey($qr->id)->lockForUpdate()->firstOrFail();
            if ($qr->status !== QrPaymentStatus::PENDING || !$this->isExpired($qr)) {
                return null;
            }
            $qr->forceFill(['status' => QrPaymentStatus::EXPIRED, 'cancelled_at' => now(), 'cancel_reason' => 'expired'] + self::watch($qr))->save();

            return $qr;
        });
        if ($expired !== null) {
            $this->announce($expired);

            return $expired;
        }

        return $qr->refresh();
    }

    private function markCancelled(PosQrPayment $qr, string $reason): PosQrPayment
    {
        $qr->forceFill(['status' => QrPaymentStatus::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 64)] + self::watch($qr))->save();

        return $qr;
    }

    /**
     * Review 11 H2/H3: an ended UzQR is asked again from now on, until the operator settles it.
     *
     * @return array<string, mixed>
     */
    private static function watch(PosQrPayment $qr): array
    {
        return self::watched($qr) ? ['next_check_at' => now(), 'check_failures' => 0] : [];
    }

    /**
     * qr.paid | qr.cancelled | qr.expired on the branch's floor channel; a cancelled or expired QR is also dropped at
     * the provider that keeps one (UzQR; best effort).
     */
    private function announce(PosQrPayment $qr, ?Store $store = null): void
    {
        if ($qr->status === QrPaymentStatus::CANCELLED || $qr->status === QrPaymentStatus::EXPIRED) {
            try {
                $this->providers->of($qr)->cancel($qr, $store ?? Store::findOrFail($qr->store_id));
                // Review 11 H3: the operator took the cancel (else the recheck sends it again)
                if (self::watched($qr)) {
                    PosQrPayment::whereKey($qr->id)->whereNull('operator_cancelled_at')->update(['operator_cancelled_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::warning('QR provider cancel failed: ' . $e->getMessage(), ['qr' => $qr->uuid]);
            }
        }
        event(new QrPaymentBroadcast($qr, (string) Order::whereKey($qr->order_id)->value('uuid')));
    }
}
