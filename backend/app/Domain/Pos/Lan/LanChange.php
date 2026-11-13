<?php

namespace App\Domain\Pos\Lan;

/**
 * P6 review (S5): why `pos.lan_changed` went to a branch's terminals (App\Events\Broadcast\PosLanChangedBroadcast):
 * the hub refetches GET /pos/lan at once instead of with its next bootstrap.
 */
enum LanChange: string
{
    /** A terminal of the branch was revoked: the hub drops it. */
    case DEVICE_REVOKED = 'device_revoked';

    /** The owner rotated the branch's key: every device takes its new keys. */
    case KEY_ROTATED = 'key_rotated';

    /** The hub moved (the owner's choice, or the hub's secret went to another till). */
    case HUB_CHANGED = 'hub_changed';

    /** The owner changed the hub's port. */
    case PORT_CHANGED = 'port_changed';
}
