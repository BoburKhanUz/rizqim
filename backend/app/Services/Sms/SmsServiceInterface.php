<?php

namespace App\Services\Sms;

interface SmsServiceInterface
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param  int|null  $ttlSeconds  how long the message may wait in a gateway's queue before it expires unsent:
     *                                a sign-in code passes its own lifetime; null is the driver's default for other
     *                                messages (Onlinegroup: services.onlinegroup.message_ttl_seconds). Drivers without
     *                                a queue lifetime ignore it.
     */
    public function send(string $phone, string $message, ?int $ttlSeconds = null): bool;
}
