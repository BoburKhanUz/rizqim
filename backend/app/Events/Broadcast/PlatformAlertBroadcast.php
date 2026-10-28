<?php

namespace App\Events\Broadcast;

use App\Models\Alert;

/**
 * ops.alert.opened | ops.alert.acknowledged | ops.alert.resolved (P4-15) on `platform.ops`: an alert changed. Ids and
 * statuses only; the admin app refetches the alert through the API.
 */
class PlatformAlertBroadcast extends PlatformOpsBroadcast
{
    public const OPENED = 'opened';

    public const ACKNOWLEDGED = 'acknowledged';

    public const RESOLVED = 'resolved';

    public int $alertId;

    public string $kind;

    public string $severity;

    public string $state;

    public function __construct(Alert $alert, public string $action)
    {
        $this->alertId = (int) $alert->id;
        $this->kind = $alert->kind;
        $this->severity = $alert->severity;
        $this->state = $alert->state;
    }

    public function broadcastAs(): string
    {
        return 'ops.alert.' . $this->action;
    }

    /**
     * @return array{id: int, kind: string, severity: string, state: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->alertId, 'kind' => $this->kind, 'severity' => $this->severity, 'state' => $this->state];
    }
}
