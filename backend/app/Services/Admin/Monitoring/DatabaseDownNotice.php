<?php

namespace App\Services\Admin\Monitoring;

use App\Services\Telegram\TelegramBotApi;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3 (review R3-4, RR-3, RR-6): the alert engine keeps its rules and alerts in Postgres, so when the database does not
 * answer it cannot tell anyone. EvaluateAlertsJob then calls down(); once the database answers again, up().
 *
 * - One Telegram message straight to the ops chat (config only: no database, no Redis, no queue), at most one per
 *   REPEAT_MINUTES, also across recoveries: a flapping database (connection limit, crash loop) is one message, and
 *   the recovery is announced only after the database has stayed up UP_MINUTES, with the number of flaps.
 * - The decision is recorded FIRST (written and flushed) and the message is sent only if that worked, so a full disk
 *   (the usual reason Postgres stops) can never turn this into a message every minute. The state goes to the first
 *   writable path of monitoring.db_down_state_paths (storage/, then /dev/shm, which is memory); when none can be
 *   written, a message goes out only in the first minute of a clock hour and says so.
 * - Nothing here throws: a failure is logged.
 */
class DatabaseDownNotice
{
    public const REPEAT_MINUTES = 15;

    public const UP_MINUTES = 3;

    public function __construct(
        private readonly TelegramBotApi $api,
    ) {}

    /** @return list<string> */
    public static function paths(): array
    {
        $paths = config('monitoring.db_down_state_paths');

        return is_array($paths) && $paths !== [] ? array_values(array_map('strval', $paths)) : [storage_path('framework/monitoring-db-down.json'), '/dev/shm/rizqim-monitoring-db-down.json'];
    }

    public function down(Throwable $cause): void
    {
        try {
            $now = now()->getTimestamp();
            $state = $this->read() ?? [];
            $since = (int) ($state['since'] ?? $now);
            $flaps = (int) ($state['flaps'] ?? 0) + (isset($state['up_since']) ? 1 : 0);
            $last = isset($state['last_sent']) ? (int) $state['last_sent'] : null;
            $due = $last === null || $now - $last >= self::REPEAT_MINUTES * 60;
            $next = ['since' => $since, 'flaps' => $flaps, 'last_sent' => $due ? $now : $last];

            if (!$this->write($next)) {
                // Nowhere to remember it: at most once an hour, on the clock
                if ((int) now()->format('i') === 0) {
                    $this->send($this->downText($since, $flaps) . "\n(Holatni diskka yozib bo'lmadi: bu xabar soatda bir marta takrorlanadi.)");
                }

                return;
            }
            if ($due) {
                Log::critical('Monitoring: the database does not answer', ['exception' => $cause::class, 'since_minutes' => intdiv($now - $since, 60), 'flaps' => $flaps]);
                $this->send($this->downText($since, $flaps));
            }
        } catch (Throwable $e) {
            Log::error('Monitoring: the database-down notice failed', ['exception' => $e::class]);
        }
    }

    public function up(): void
    {
        try {
            $state = $this->read();
            if ($state === null || !isset($state['since'])) {
                return;
            }
            $now = now()->getTimestamp();
            $upSince = (int) ($state['up_since'] ?? $now);
            if ($now - $upSince < self::UP_MINUTES * 60) {
                $this->write(['up_since' => $upSince] + $state);

                return;
            }

            // Stayed up: announced once; last_sent is kept so a new outage soon after obeys the same limit
            if (!$this->write(['last_sent' => $state['last_sent'] ?? null])) {
                return;
            }
            $minutes = intdiv($upSince - (int) $state['since'], 60);
            $flaps = (int) ($state['flaps'] ?? 0);
            $this->send("[HAL QILINDI] Ma'lumotlar bazasi yana javob bermoqda (taxminan {$minutes} daqiqa ishlamadi"
                . ($flaps > 0 ? ", shu vaqtda {$flaps} marta uzilib-ulandi" : '') . ').');
        } catch (Throwable $e) {
            Log::error('Monitoring: the database-up notice failed', ['exception' => $e::class]);
        }
    }

    private function downText(int $since, int $flaps): string
    {
        $minutes = intdiv(now()->getTimestamp() - $since, 60);

        return "[KRITIK] Ma'lumotlar bazasi javob bermayapti" . ($minutes > 0 ? " ({$minutes} daqiqadan beri" . ($flaps > 0 ? ", {$flaps} marta uzilib-ulandi" : '') . ')' : '')
            . ": ilova va ogohlantirishlar ishlamayapti, kassalar oflayn rejimda.\nNima qilish kerak: serverda docker compose ps postgres va docker compose logs --tail=50 postgres; disk to'lmaganini tekshiring (df -h).";
    }

    /**
     * The state of the first path that holds one.
     *
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        foreach (self::paths() as $path) {
            if (!is_file($path)) {
                continue;
            }
            $raw = @file_get_contents($path);
            $state = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($state)) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Writes the state to the first path that takes it whole (and removes it from the others, so read() never finds
     * an older copy first). False when no path could be written.
     *
     * @param  array<string, mixed>  $state
     */
    private function write(array $state): bool
    {
        $body = (string) json_encode($state);
        $written = false;
        foreach (self::paths() as $path) {
            if ($written) {
                if (is_file($path)) {
                    @unlink($path);
                }

                continue;
            }
            $written = $this->writeFile($path, $body);
        }

        return $written;
    }

    private function writeFile(string $path, string $body): bool
    {
        try {
            $handle = @fopen($path, 'c+');
            if ($handle === false) {
                return false;
            }
            try {
                if (!@flock($handle, LOCK_EX)) {
                    return false;
                }
                $ok = @ftruncate($handle, 0) && @rewind($handle) && @fwrite($handle, $body) === strlen($body) && @fflush($handle);
                @flock($handle, LOCK_UN);

                return $ok && is_file($path);
            } finally {
                @fclose($handle);
            }
        } catch (Throwable) {
            return false;
        }
    }

    private function send(string $text): void
    {
        $chat = null;
        foreach ([config('services.telegram.ops_chat_id'), config('services.telegram.superadmin_chat_id')] as $candidate) {
            if (trim((string) $candidate) !== '') {
                $chat = trim((string) $candidate);
                break;
            }
        }
        if ($chat === null || !$this->api->isConfigured()) {
            Log::error('Monitoring: nobody can be told the database is down: TELEGRAM_BOT_TOKEN or TELEGRAM_OPS_CHAT_ID is empty');

            return;
        }

        try {
            $this->api->call('sendMessage', ['chat_id' => $chat, 'text' => $text], null, (float) config('monitoring.telegram.timeout', 5));
        } catch (Throwable $e) {
            Log::error('Monitoring: the database-down message could not be sent', ['exception' => $e::class]);
        }
    }
}
