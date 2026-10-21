<?php

namespace App\Domain\Pos\Billing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A bill, payment, refund, shift or approval action refused: an Uzbek message for the cashier, a stable `reason` code
 * the terminal branches on and the HTTP status (409 for state conflicts, 422 for bad input, 403 for a missing or
 * rejected approval). Left uncaught in an API request it renders the standard error shape
 * ({success: false, message, data: {reason, ...context}}).
 */
class PosActionException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 409,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $reason, string $message, array $context = []): self
    {
        return new self($reason, $message, 409, $context);
    }

    public static function invalid(string $reason, string $message, array $context = []): self
    {
        return new self($reason, $message, 422, $context);
    }

    public static function forbidden(string $reason, string $message, array $context = []): self
    {
        return new self($reason, $message, 403, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return ['reason' => $this->reason] + $this->context;
    }

    public function render(Request $request): ?JsonResponse
    {
        if (!$request->is('api/*') && !$request->expectsJson()) {
            return null;
        }

        return response()->json(['success' => false, 'message' => $this->getMessage(), 'data' => $this->data()], $this->status);
    }
}
