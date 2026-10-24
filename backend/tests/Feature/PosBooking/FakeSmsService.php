<?php

namespace Tests\Feature\PosBooking;

use App\Services\Sms\SmsServiceInterface;

/**
 * Records the SMS messages instead of sending them (P3-38 booking confirmations).
 */
class FakeSmsService implements SmsServiceInterface
{
    /** @var list<array{phone: string, text: string}> */
    public array $sent = [];

    public function send(string $phone, string $message): bool
    {
        $this->sent[] = ['phone' => $phone, 'text' => $message];

        return true;
    }
}
