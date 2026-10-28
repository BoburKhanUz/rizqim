<?php

namespace App\Services\Admin;

use RuntimeException;

/**
 * P4-11: an admin sign-in or team rule refusing a request: answered as {success: false, message, code, data: {reason,
 * ...}} with the given status (App\Http\Controllers\Api\V1\Admin\AdminController::refuse).
 */
class AdminAuthException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 422,
        public readonly array $data = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
