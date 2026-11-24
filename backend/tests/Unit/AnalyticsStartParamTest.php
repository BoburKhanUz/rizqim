<?php

namespace Tests\Unit;

use App\Domain\Analytics\AnalyticsPlatform;
use App\Domain\Analytics\StartKind;
use App\Domain\Analytics\StartParam;
use PHPUnit\Framework\TestCase;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 2-bo'lim 5-band, A.4): the Mini App's start_param is reduced to its kind (and a
 * campaign code for step 4's lookup); the raw value is never kept. The platform name is reduced to a small code.
 */
class AnalyticsStartParamTest extends TestCase
{
    public function test_every_known_start_param_shape_has_its_kind(): void
    {
        $cases = [
            [null, StartKind::NONE, null],
            ['', StartKind::NONE, null],
            ['c_tg_ads_1', StartKind::CAMPAIGN, 'tg_ads_1'],
            ['C_Insta2026', StartKind::CAMPAIGN, 'insta2026'],
            ['c_ab', StartKind::UNKNOWN, null], // too short for a campaign code
            ['c_bad-code', StartKind::UNKNOWN, null],
            ['ref_42', StartKind::REFERRAL, null],
            ['REFERRER_42', StartKind::REFERRAL, null],
            ['rizqim_42', StartKind::REFERRAL, null],
            ['invite_abc123', StartKind::INVITE, null],
            ['store_9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', StartKind::STORE, null],
            ['s_12', StartKind::STORE, null],
            ['something_else', StartKind::UNKNOWN, null],
            [str_repeat('x', 600), StartKind::UNKNOWN, null],
        ];
        foreach ($cases as [$param, $kind, $code]) {
            $parsed = StartParam::parse($param);
            $this->assertSame($kind, $parsed->kind, (string) $param);
            $this->assertSame($code, $parsed->campaignCode, (string) $param);
        }
    }

    public function test_platform_names_map_to_codes_and_anything_else_is_unknown(): void
    {
        $this->assertSame(AnalyticsPlatform::IOS, AnalyticsPlatform::fromTelegram('ios'));
        $this->assertSame(AnalyticsPlatform::ANDROID, AnalyticsPlatform::fromTelegram('android'));
        $this->assertSame(AnalyticsPlatform::ANDROID, AnalyticsPlatform::fromTelegram('android_x'));
        $this->assertSame(AnalyticsPlatform::TDESKTOP, AnalyticsPlatform::fromTelegram('tdesktop'));
        $this->assertSame(AnalyticsPlatform::MACOS, AnalyticsPlatform::fromTelegram('macos'));
        $this->assertSame(AnalyticsPlatform::WEB, AnalyticsPlatform::fromTelegram('webk'));
        $this->assertSame(AnalyticsPlatform::WEBA, AnalyticsPlatform::fromTelegram('weba'));
        $this->assertSame(AnalyticsPlatform::UNKNOWN, AnalyticsPlatform::fromTelegram('tizen'));
        $this->assertSame(AnalyticsPlatform::UNKNOWN, AnalyticsPlatform::fromTelegram('IOS '));
    }
}
