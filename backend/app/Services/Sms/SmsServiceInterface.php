<?php

namespace App\Services\Sms;

interface SmsServiceInterface
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function send(string $phone, string $message): bool;
}
