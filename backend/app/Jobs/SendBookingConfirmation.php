<?php

namespace App\Jobs;

use App\Services\Sms\SmsServiceInterface;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P3-38: the booking confirmation to the guest (App\Domain\Pos\Booking\BookingConfirmationService), sent by a queue
 * worker once the request that asked for it has committed. `telegram`: to the customer's chat through
 * TelegramMessenger (which queues and retries the Bot API call as every other Telegram message); `sms`: to the phone
 * through the SMS service. A failure is logged, never reported back to the till.
 */
class SendBookingConfirmation implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public int $bookingId,
        public string $channel,
        public string $recipient,
        public string $text,
    ) {}

    public function handle(TelegramMessenger $telegram, SmsServiceInterface $sms): void
    {
        try {
            if ($this->channel === 'telegram') {
                $telegram->sendMessage($this->recipient, $this->text);

                return;
            }

            if (!$sms->send($this->recipient, $this->text)) {
                Log::warning("Booking {$this->bookingId}: the confirmation SMS was not sent.");
            }
        } catch (Throwable $e) {
            Log::error("Booking {$this->bookingId}: the confirmation over {$this->channel} failed: {$e->getMessage()}");
        }
    }
}
