<?php

namespace App\Domain\Analytics;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md A.3, A.4): what an app_open's start_param was, kept as props.start_kind. The
 * raw start_param never is (an invite token, a referrer's id, a store's uuid).
 */
enum StartKind: string
{
    case NONE = 'none';
    case CAMPAIGN = 'campaign';
    case REFERRAL = 'referral';
    case STORE = 'store';
    case INVITE = 'invite';
    case UNKNOWN = 'unknown';
}
