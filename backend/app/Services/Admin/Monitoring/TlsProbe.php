<?php

namespace App\Services\Admin\Monitoring;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * M3: when the public HTTPS certificate expires. The scheduler opens a TLS connection to the host
 * (monitoring.tls.host, else APP_URL's host when it is https) and reads the peer certificate; the answer is reused
 * for monitoring.tls.cache_minutes. Without an https host there is nothing to check.
 */
class TlsProbe
{
    public function host(): ?string
    {
        $host = trim((string) config('monitoring.tls.host'));
        if ($host !== '') {
            return $host;
        }
        $url = (string) config('app.url');
        if (!str_starts_with(strtolower($url), 'https://')) {
            return null;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        return $host;
    }

    /**
     * @return array{host: string, expires_at: string|null, reachable: bool}|null null = no https host to check
     */
    public function check(): ?array
    {
        $host = $this->host();
        if ($host === null) {
            return null;
        }

        return Cache::remember('monitoring:tls:' . md5($host), max(1, (int) config('monitoring.tls.cache_minutes', 60)) * 60, function () use ($host) {
            $expires = $this->expiresAt($host, (int) config('monitoring.tls.port', 443));

            return ['host' => $host, 'expires_at' => $expires?->toIso8601ZuluString(), 'reachable' => $expires !== null];
        });
    }

    public function expiresAt(string $host, int $port): ?Carbon
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);
        $errno = 0;
        $error = '';
        $client = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, (float) config('monitoring.tls.timeout', 5), STREAM_CLIENT_CONNECT, $context);
        if ($client === false) {
            Log::warning('Monitoring: TLS certificate could not be read', ['host' => $host, 'error' => mb_substr($error, 0, 200)]);

            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate !== null ? openssl_x509_parse($certificate) : false;
        if (!is_array($parsed) || !isset($parsed['validTo_time_t'])) {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $parsed['validTo_time_t']);
    }
}
