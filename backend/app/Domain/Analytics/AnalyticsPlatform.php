<?php

namespace App\Domain\Analytics;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md A.3): analytics_events.platform, from Telegram.WebApp.platform. Stored data:
 * never renumber. A name off the list (a new Telegram client, a typo) is UNKNOWN, never a reason to lose the batch.
 */
enum AnalyticsPlatform: int
{
    case UNKNOWN = 0;
    case IOS = 1;
    case ANDROID = 2;
    case TDESKTOP = 3;
    case MACOS = 4;
    case WEB = 5;
    case WEBA = 6;

    public static function fromTelegram(mixed $platform): self
    {
        return match ($platform) {
            'ios' => self::IOS,
            'android', 'android_x' => self::ANDROID,
            // Unigram is a desktop (Windows) client like Telegram Desktop
            'tdesktop', 'unigram' => self::TDESKTOP,
            'macos' => self::MACOS,
            'web', 'webk', 'webz' => self::WEB,
            'weba' => self::WEBA,
            default => self::UNKNOWN,
        };
    }
}
