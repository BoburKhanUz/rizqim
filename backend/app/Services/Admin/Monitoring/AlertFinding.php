<?php

namespace App\Services\Admin\Monitoring;

/**
 * One problem a check found right now. `subjectKey` is what the problem is about (global, a queue, a device): the
 * evaluator keeps one unresolved alert per rule and subject. `details` carries numbers and names only. `severity`
 * overrides the kind's for this finding (M3: a disk at 80 % is a warning, at 90 % critical; an alert that grows
 * critical is raised in place and announced again).
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
        public readonly ?string $severity = null,
    ) {}
}
