<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Contracts\VoucherLedger;
use App\Domain\Pos\Vouchers\Console\RetryVoucherReceipts;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\Voucher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * P6-43: gift certificates. VoucherLedger (what billing consumes for PosTender::VOUCHER) is VoucherLedgerService; a
 * certificate's advance receipt the provider confirms (or fails) later is recorded on it (fiscal.receipt_updated).
 * Review S6: typing promo codes at the till (POST /pos/orders/{uuid}/promo) is limited to 10 a minute per staff member
 * and per terminal (`pos-promo`), so codes cannot be guessed at the API's 300 a minute; a plain 429. Review C12: an
 * advance receipt that was not issued is sent again hourly (pos:vouchers-retry-receipts).
 */
class PosVouchersServiceProvider extends ServiceProvider
{
    public const FISCAL_SUBJECT = 'voucher';

    public function register(): void
    {
        $this->app->bind(VoucherLedger::class, VoucherLedgerService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RetryVoucherReceipts::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pos:vouchers-retry-receipts')->hourly()->withoutOverlapping(30);
        });

        RateLimiter::for('pos-promo', function (Request $request) {
            $user = $request->user();
            $deviceId = $user !== null ? data_get($user->currentAccessToken(), 'device_id') : null;
            $refused = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => "Promo-kod juda ko'p marta kiritildi. Bir daqiqadan so'ng qayta urinib ko'ring.",
                'data' => ['reason' => 'promo_rate_limited'],
            ], 429, $headers);

            $limits = [Limit::perMinute(10)->by('pos-promo:user:' . ($user?->getAuthIdentifier() ?? $request->ip()))->response($refused)];
            if ($deviceId !== null) {
                $limits[] = Limit::perMinute(10)->by('pos-promo:device:' . $deviceId)->response($refused);
            }

            return $limits;
        });

        Event::listen(FiscalReceiptUpdatedBroadcast::class, function (FiscalReceiptUpdatedBroadcast $event) {
            if ($event->subjectType !== self::FISCAL_SUBJECT || $event->subjectId === null) {
                return;
            }
            try {
                Voucher::whereKey($event->subjectId)->update(['fiscal_receipt_uuid' => $event->uuid, 'fiscal_status' => $event->status]);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
