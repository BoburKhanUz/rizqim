<?php

namespace App\Services\Admin\Monitoring;

/**
 * One problem a check found right now. `subjectKey` is what the problem is about (global, a queue, a device): the
 * evaluator keeps one unresolved alert per rule and subject. `details` carries numbers and names only.
 */
final class AlertFinding
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $subjectKey,
        public readonly string $title,
        public readonly array $details = [],
        public readonly ?int $storeId = null,
    ) {}
}
