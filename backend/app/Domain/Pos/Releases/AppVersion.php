<?php

namespace App\Domain\Pos\Releases;

/**
 * Wave M5 S5-a: an app version as terminals report it, `<version>+<build>` (the build is optional, 0 when missing).
 * Only the shape is checked here; what a version is worth is decided by PosAppReleases.
 */
final class AppVersion
{
    /** The request header every POS app sends (rizqim_api RizqimClient). */
    public const HEADER = 'X-Rizqim-App-Version';

    private const PATTERN = '/^(\d{1,6}(?:\.\d{1,6}){1,3})(?:\+(\d{1,9}))?$/';

    /**
     * Review 3 M4: devices.app_version is varchar(32). The pattern alone lets 37 characters through, which the
     * heartbeat save could not store; anything longer than the column is not a version.
     */
    public const MAX_LENGTH = 32;

    /**
     * @return array{version: string, build: int}|null null for anything that is not a plain version
     */
    public static function parse(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > self::MAX_LENGTH || !preg_match(self::PATTERN, $value, $match)) {
            return null;
        }

        return ['version' => $match[1], 'build' => (int) ($match[2] ?? 0)];
    }

    /**
     * The canonical text of a reported version, or null when it is not one.
     */
    public static function normalize(?string $value): ?string
    {
        $parsed = self::parse($value);

        return $parsed === null ? null : $parsed['version'] . '+' . $parsed['build'];
    }

    /**
     * @param  array{version: string, build: int}  $a
     * @param  array{version: string, build: int}  $b
     * @return int -1, 0 or 1
     */
    public static function compare(array $a, array $b): int
    {
        return version_compare($a['version'], $b['version']) <=> 0 ?: $a['build'] <=> $b['build'];
    }
}
