<?php

namespace App\Services\Push;

/**
 * The Firebase service account the backend signs in as (the JSON key downloaded from Firebase/Google Cloud).
 */
final class FcmCredentials
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $clientEmail,
        public readonly string $privateKey,
    ) {}

    /**
     * From FIREBASE_CREDENTIALS_JSON (base64, or the JSON itself) or the FIREBASE_CREDENTIALS file; null while neither
     * is set or the key is unusable (what is wrong is logged by the caller).
     */
    public static function fromConfig(): ?self
    {
        $json = trim((string) config('push.credentials_json'));

        if ($json !== '') {
            $raw = str_starts_with($json, '{') ? $json : (base64_decode($json, true) ?: '');
        } else {
            $path = trim((string) config('push.credentials_path'));
            if ($path === '') {
                return null;
            }

            $file = str_starts_with($path, '/') ? $path : base_path($path);
            $raw = is_readable($file) ? (string) file_get_contents($file) : '';
        }

        return self::fromJson($raw);
    }

    public static function fromJson(string $json): ?self
    {
        $key = json_decode($json, true);

        if (!is_array($key)) {
            return null;
        }

        $projectId = trim((string) (config('push.project_id') ?: ($key['project_id'] ?? '')));
        $email = trim((string) ($key['client_email'] ?? ''));
        $privateKey = (string) ($key['private_key'] ?? '');

        if ($projectId === '' || $email === '' || $privateKey === '') {
            return null;
        }

        return new self($projectId, $email, $privateKey);
    }
}
