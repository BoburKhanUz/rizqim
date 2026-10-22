<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Domain\Pos\Shifts\OrderStamp;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\PosRefund;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P3-18: bills, payments, shifts, refunds and manager approvals.
 *
 *  - ManagerApprovals (the contract other POS areas consume) is ManagerApprovalService;
 *  - a new POS order gets its business day, queue number and shift (OrderStamp, on Order `creating`);
 *  - a receipt the provider confirms (or fails) after a retry finishes its bill or refund (fiscal.receipt_updated);
 *  - POST /pos/approvals is limited per user and terminal (`pos-approvals`), on top of the PIN lockout.
 */
class PosBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ManagerApprovals::class, fn ($app) => $app->make(ManagerApprovalService::class));
    }

    public function boot(): void
    {
        Order::creating(fn (Order $order) => app(OrderStamp::class)->stamp($order));

        Event::listen(FiscalReceiptUpdatedBroadcast::class, function (FiscalReceiptUpdatedBroadcast $event) {
            try {
                if ($event->subjectType === OrderBill::FISCAL_SUBJECT && $event->subjectId !== null && ($bill = OrderBill::find($event->subjectId))) {
                    app(BillFiscalizer::class)->apply($bill, $event->uuid, $event->status);
                } elseif ($event->subjectType === PosRefund::FISCAL_SUBJECT && $event->subjectId !== null && ($refund = PosRefund::find($event->subjectId))) {
                    app(BillFiscalizer::class)->applyRefund($refund, $event->uuid, $event->status);
                }
            } catch (Throwable $e) {
                // The till's retry (and the shift close) finish it instead
                report($e);
            }
        });

        RateLimiter::for('pos-approvals', fn (Request $request) => [
            Limit::perMinute(10)->by('user:' . ($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perMinute(60)->by('ip:' . $request->ip()),
        ]);
    }
}
