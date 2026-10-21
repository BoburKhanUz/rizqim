<?php

namespace App\Domain\Pos\Accounts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A customer account rule refused the action (unknown, blocked, over the limit, more than the debt...). The message is
 * for the cashier (Uzbek); `reason` is a stable code for clients.
 */
class CustomerAccountException extends RuntimeException
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
