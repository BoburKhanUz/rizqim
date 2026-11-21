<?php

namespace App\Domain\Pos\Releases;

/**
 * Wave M5 S5-a: what GET /terminal `app_release.state` says. Only a notice, never a gate: no state stops a shift, an
 * order, a payment, a receipt, a refund or the offline sync.
 */
enum AppReleaseState: string
{
    /** Nothing to say: the latest version, or the till never reported one. */
    case OK = 'ok';

    /** A newer version exists (a quiet notice), or the till is below the minimum but before `minimum_from`. */
    case UPDATE_AVAILABLE = 'update_available';

    /** Below the minimum and past `minimum_from` (a red notice; the till still works). */
    case UPDATE_REQUIRED = 'update_required';
}
