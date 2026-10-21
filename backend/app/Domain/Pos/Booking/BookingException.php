<?php

namespace App\Domain\Pos\Booking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A booking rule refused the action; the message is for the cashier (Uzbek). `reason` is a stable code for clients,
 * `status` the HTTP status the API answers with, `context` extra data (the conflicting bookings, amounts).
 */
class BookingException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Uncaught in an API request: the standard error shape, {success: false, message, data: {reason, ...context}}.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (!$request->is('api/*') && !$request->expectsJson()) {
            return null;
        }

        return response()->json(['success' => false, 'message' => $this->getMessage(), 'data' => ['reason' => $this->reason] + $this->context], $this->status);
    }
}
