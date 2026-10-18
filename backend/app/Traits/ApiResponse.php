<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Send a success response.
     */
    protected function success(mixed $data = [], string $message = '', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Send an error response.
     */
    protected function error(string $message = 'Error occurred', mixed $data = [], int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }
}
