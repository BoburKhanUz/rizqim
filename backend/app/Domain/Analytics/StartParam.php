<?php

namespace App\Domain\Analytics;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 2-bo'lim 5-band, A.4): a Mini App start_param reduced to its kind, and for a
 * campaign link (`c_<code>`) the code that step 4 resolves to campaigns.id. The shapes in use:
 * `c_<code>` (campaign, code ^[a-z0-9_]{3,32}$, lower-cased), `ref_` / `referrer_` / `rizqim_<id>` (a friend's
 * referral, ReferralService), `invite_<token>` (staff invite), `store_<uuid>` and `s_<store>` (a store's link).
 * Anything else is UNKNOWN. Nothing of the raw value is kept.
 */
final class StartParam
{
    public const CAMPAIGN_CODE = '/^[a-z0-9_]{3,32}$/';

    private function __construct(
        public readonly StartKind $kind,
        public readonly ?string $campaignCode = null,
    ) {}

    public static function parse(?string $param): self
    {
        if ($param === null || $param === '') {
            return new self(StartKind::NONE);
        }
        // Telegram limits start_param to 512 characters; anything longer is no link of ours
        if (strlen($param) > 512) {
            return new self(StartKind::UNKNOWN);
        }
        $lower = strtolower($param);
        if (str_starts_with($lower, 'c_')) {
            $code = substr($lower, 2);

            return preg_match(self::CAMPAIGN_CODE, $code) === 1 ? new self(StartKind::CAMPAIGN, $code) : new self(StartKind::UNKNOWN);
        }
        foreach (['ref_', 'referrer_', 'rizqim_'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return new self(StartKind::REFERRAL);
            }
        }
        if (str_starts_with($lower, 'invite_')) {
            return new self(StartKind::INVITE);
        }
        if (str_starts_with($lower, 'store_') || str_starts_with($lower, 's_')) {
            return new self(StartKind::STORE);
        }

        return new self(StartKind::UNKNOWN);
    }
}
