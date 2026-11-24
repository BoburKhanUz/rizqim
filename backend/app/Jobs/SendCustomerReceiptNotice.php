<?php

namespace App\Jobs;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Integrations\FiscalizedBy;
use App\Domain\Receipts\CustomerOrderReceipts;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\OrderChannel;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\TelegramContent;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave P (receipts): a sale or refund receipt of an order was registered (CustomerReceiptsServiceProvider). After
 * commit, on a worker:
 *
 *  - a sale receipt records who issued the order's receipt in orders.fiscalized_by, when nothing has yet
 *    (`payment_provider` for a receipt Payme / Click / Paylov registered, `venue_pos` for the till's);
 *  - the order's customer (a Mini App or mobile order; a till order's user is the cashier) gets one Telegram message:
 *    "Buyurtma #... fiskal cheki tayyor" (or "qaytarish cheki"), a "Chekni ochish" URL button and the Mini App button.
 *    Only an https link on the tax committee's host is sent (CustomerOrderReceipts::publicUrl). Nothing for a customer
 *    without a Telegram chat, a blocked one or one who switched order messages off (NotificationRouter); the Mini App's
 *    order history shows the receipt either way (the legal delivery, ZRU-792 art. 23).
 *
 * Never twice for a receipt: fiscal_receipts.customer_notified_at is claimed atomically before the message is queued
 * (a retried job, a repeated SetFiscalData, a second save find it set). The message is queued through TelegramMessenger,
 * which retries the Bot API call itself; this job is tried once and only logs a failure (no URL, chat id or text).
 */
class SendCustomerReceiptNotice implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $receiptId) {}

    public function handle(): void
    {
        try {
            $this->run();
        } catch (Throwable $e) {
            Log::warning('Customer receipt notice failed', ['receipt_id' => $this->receiptId, 'error' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }

    private function run(): void
    {
        $receipt = FiscalReceipt::find($this->receiptId);
        if ($receipt === null || $receipt->status !== FiscalReceiptStatus::ISSUED || $receipt->non_fiscal
            || !in_array($receipt->type, [FiscalReceiptType::SALE, FiscalReceiptType::REFUND], true)) {
            return;
        }

        // The receipt's own store's order only (CustomerOrderReceipts::orderOf)
        $order = CustomerOrderReceipts::orderOf($receipt);
        if ($order === null) {
            return;
        }

        if ($receipt->type === FiscalReceiptType::SALE && $order->fiscalized_by === null) {
            Order::whereKey($order->id)->whereNull('fiscalized_by')->toBase()->update([
                'fiscalized_by' => ($receipt->driver === 'external' ? FiscalizedBy::PAYMENT_PROVIDER : FiscalizedBy::VENUE_POS)->value,
            ]);
        }

        // The customer's order list (GET /orders, revision-gated) shows the receipt now
        app(\App\Domain\Realtime\ChangeRevision::class)->touchUser((int) $order->user_id);

        $this->tellTheCustomer($receipt, $order);
    }

    private function tellTheCustomer(FiscalReceipt $receipt, Order $order): void
    {
        $url = CustomerOrderReceipts::publicUrl($receipt->qr_url);
        // A till order's user is the cashier who rang it up: it has no customer to tell (OrderChannel::isPos)
        if ($url === null || !in_array($order->channel, [OrderChannel::TELEGRAM, OrderChannel::MOBILE], true)) {
            return;
        }

        $customer = $order->user;
        $telegram = app(TelegramMessenger::class);
        if ($customer === null || $customer->isBlocked() || !$customer->hasTelegram() || !$telegram->isConfigured()
            || !app(NotificationPreferenceService::class)->allows($customer, NotificationType::ORDER_UPDATES, NotificationChannel::TELEGRAM)) {
            return;
        }

        // The claim: whoever sets it first sends the message
        $claimed = FiscalReceipt::whereKey($receipt->id)->whereNull('customer_notified_at')->toBase()->update(['customer_notified_at' => now()]);
        if ($claimed !== 1) {
            return;
        }

        $buttons = [[['text' => '🧾 Chekni ochish', 'url' => $url]]];
        $appButton = $telegram->webAppButton("📱 Buyurtmani ko'rish (Ilova)");
        if ($appButton !== null) {
            $buttons[] = [$appButton];
        }

        app(NotificationRouter::class)->send($customer, new NotificationMessage(
            NotificationType::ORDER_UPDATES,
            telegram: new TelegramContent($this->text($receipt, $order), ['parse_mode' => 'Markdown', 'reply_markup' => ['inline_keyboard' => $buttons]]),
        ));
    }

    private function text(FiscalReceipt $receipt, Order $order): string
    {
        $refund = $receipt->type === FiscalReceiptType::REFUND;
        $seller = is_array($order->seller_snapshot) ? ($order->seller_snapshot['legal_name'] ?? null) : null;
        $seller = is_string($seller) && trim($seller) !== '' ? $seller : ($order->store?->getAttribute('legal_name') ?: $order->store?->name);
        $amount = Money::of($receipt->total);

        return ($refund ? '↩️ *Buyurtma #' . $order->short_code . ' qaytarish cheki tayyor*' : '🧾 *Buyurtma #' . $order->short_code . ' fiskal cheki tayyor*')
            . ($seller ? "\n🏪 Sotuvchi: " . $this->escape((string) $seller) : '')
            . ($amount->isPositive() ? "\n💰 Summa: " . number_format($amount->toFloat(), 0, '.', ' ') . " so'm" : '')
            . "\n\nChekni soliq xizmati sahifasida ochish uchun pastdagi tugmani bosing.";
    }

    /**
     * Free text in a (legacy) Markdown message: its markup characters escaped.
     */
    private function escape(string $text): string
    {
        return str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text);
    }
}
