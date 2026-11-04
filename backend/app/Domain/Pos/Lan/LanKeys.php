<?php

namespace App\Domain\Pos\Lan;

/**
 * P6 local bridge: the keys a branch's LAN uses (pos/packages/pos_lan/README.md §2), all HMAC-SHA256 of the branch's
 * 32-byte secret S: a device's own key (only that device and the hub, which derives it from S), the beacon key (every
 * terminal of the branch) and the grant key (the server and the hub only: a device cannot forge a staff grant). The
 * Dart side (pos_lan LanKeys) derives the same bytes; tests pin both to the same vectors.
 */
final class LanKeys
{
    public const DEVICE_LABEL = 'rizqim-lan/v1/device/';
    public const BEACON_LABEL = 'rizqim-lan/v1/beacon';
    public const GRANT_LABEL = 'rizqim-lan/v1/grant';

    /** A new branch secret: 32 random bytes. */
    public static function newSecret(): string
    {
        return random_bytes(32);
    }

    public static function device(string $secret, string $deviceUuid): string
    {
        return hash_hmac('sha256', self::DEVICE_LABEL . strtolower($deviceUuid), $secret, true);
    }

    public static function beacon(string $secret): string
    {
        return hash_hmac('sha256', self::BEACON_LABEL, $secret, true);
    }

    public static function grant(string $secret): string
    {
        return hash_hmac('sha256', self::GRANT_LABEL, $secret, true);
    }

    /**
     * A signed offline grant: base64url(payload JSON) "." base64url(HMAC(grant key, that base64url text)).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function signGrant(string $secret, array $payload): string
    {
        $body = self::base64url((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $body . '.' . self::base64url(hash_hmac('sha256', $body, self::grant($secret), true));
    }

    /**
     * The payload of a grant whose signature holds, else null (tests and support tools; the hub checks it offline).
     *
     * @return array<string, mixed>|null
     */
    public static function verifyGrant(string $secret, string $grant): ?array
    {
        $parts = explode('.', $grant);
        if (count($parts) !== 2) {
            return null;
        }
        $expected = self::base64url(hash_hmac('sha256', $parts[0], self::grant($secret), true));
        if (!hash_equals($expected, $parts[1])) {
            return null;
        }
        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $payload = $json === false ? null : json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    public static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
