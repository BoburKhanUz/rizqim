<?php

namespace App\Services\Admin\Monitoring;

/**
 * alerts.state: `open` (nobody looked yet), `acknowledged` (a team member took it), `resolved` (done, by a member or
 * because the problem went away). Open and acknowledged are the "unresolved" states: one per rule and subject.
 */
enum AlertState: string
{
    case OPEN = 'open';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $state) => $state->value, self::cases());
    }
}
