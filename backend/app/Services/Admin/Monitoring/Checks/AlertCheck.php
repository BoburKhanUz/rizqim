<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertFinding;

/**
 * P4-15: what the evaluator measures for one rule kind.
 */
interface AlertCheck
{
    /**
     * The problems that exist right now under the rule's thresholds (an empty list = all fine), or null when the
     * state could not be read: the evaluator then leaves the rule's alerts as they are.
     *
     * @return list<AlertFinding>|null
     */
    public function findings(AlertRule $rule): ?array;
}
