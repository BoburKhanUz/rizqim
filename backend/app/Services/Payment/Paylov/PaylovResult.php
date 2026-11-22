<?php

namespace App\Services\Payment\Paylov;

/**
 * One answer of Paylov's API. developer.paylov.uz/error-codes: "Every error response from the gateway has the same
 * shape: {result: null, error: {code, message, data?}}"; /subscribe/changes: "success → {result: {...}, error: null}".
 * `transient` says whether asking again later may succeed (no answer, 5xx, 429 and the codes TRANSIENT lists).
 */
final readonly class PaylovResult
{
    /**
     * Codes of error-codes that describe a passing state of Paylov or its processing, not our request. The page says
     * nothing about which errors are worth retrying: this list is our reading of its descriptions.
     */
    public const TRANSIENT = [
        'server_error', 'rate_limit_exceeded', 'unknown_error', 'issuer_unavailable', 'processing_error',
        'ofd_error', 'fiscal_receipt_not_generated', 'ofd_too_many_requests_for_this_receipt',
    ];

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public bool $ok,
        public ?array $result,
        public ?string $errorCode,
        public int $httpStatus,
        public bool $transient,
    ) {}

    /**
     * @param  array<string, mixed>|null  $result
     */
    public static function success(?array $result, int $httpStatus = 200): self
    {
        return new self(true, $result ?? [], null, $httpStatus, false);
    }

    public static function failure(string $code, int $httpStatus, ?bool $transient = null): self
    {
        $transient ??= $httpStatus === 0 || $httpStatus >= 500 || $httpStatus === 429 || in_array($code, self::TRANSIENT, true);

        return new self(false, null, $code, $httpStatus, $transient);
    }

    public static function unreachable(): self
    {
        return self::failure('connection_failed', 0, true);
    }
}
