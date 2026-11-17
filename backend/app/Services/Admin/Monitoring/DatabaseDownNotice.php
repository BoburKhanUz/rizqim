<?php

namespace App\Services\Admin\Monitoring;

use App\Services\Telegram\TelegramBotApi;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3 (review R3-4): the alert engine keeps its rules and alerts in Postgres, so when the database does not answer it
 * cannot tell anyone. EvaluateAlertsJob then calls down(): one Telegram message straight to the ops chat (config only,
 * no database, no Redis, no queue), at most one per `REPEAT_MINUTES`, remembered in a file under storage/ (flock); and
 * up() once the database answers again: one "tiklandi" message, the file removed. Without a bot token or chat it logs
 * an error instead (once per `REPEAT_MINUTES`).
 */
class DatabaseDownNotice
{
    public const REPEAT_MINUTES = 15;

    public function __construct(
        private readonly TelegramBotApi $api,
    ) {}

    public static function path(): string
    {
        return storage_path('framework/monitoring-db-down.json');
    }

    public function down(Throwable $cause): void
    {
        $this->locked(function (array $state) use ($cause): array {
            $now = now()->getTimestamp();
            $since = (int) ($state['since'] ?? $now);
            $last = isset($state['last_sent']) ? (int) $state['last_sent'] : null;
            if ($last !== null && $now - $last < self::REPEAT_MINUTES * 60) {
                return ['since' => $since, 'last_sent' => $last];
            }

            $minutes = intdiv($now - $since, 60);
            Log::critical('Monitoring: the database does not answer', ['exception' => $cause::class, 'since_minutes' => $minutes]);
            $this->send("[KRITIK] Ma'lumotlar bazasi javob bermayapti" . ($minutes > 0 ? " ({$minutes} daqiqadan beri)" : '')
                . ": ilova va ogohlantirishlar ishlamayapti, kassalar oflayn rejimda.\nNima qilish kerak: serverda docker compose ps postgres va docker compose logs --tail=50 postgres; disk to'lmaganini tekshiring (df -h).");

            return ['since' => $since, 'last_sent' => $now];
        });
    }

    public function up(): void
    {
        if (!is_file(self::path())) {
            return;
        }
        $this->locked(function (array $state): ?array {
            if ($state === []) {
                return null;
            }
            $minutes = intdiv(now()->getTimestamp() - (int) ($state['since'] ?? now()->getTimestamp()), 60);
            $this->send("[HAL QILINDI] Ma'lumotlar bazasi yana javob bermoqda (taxminan {$minutes} daqiqa ishlamadi).");

            return null;
        });
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

    /**
     * @param  callable(array<string, mixed>): (array<string, mixed>|null)  $change  null removes the file
     */
    private function locked(callable $change): void
    {
        $path = self::path();
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            Log::error('Monitoring: the database-down state file cannot be opened', ['path' => $path]);
            $change([]);

            return;
        }

        try {
            flock($handle, LOCK_EX);
            $state = json_decode((string) stream_get_contents($handle), true);
            $next = $change(is_array($state) ? $state : []);
            if ($next === null) {
                ftruncate($handle, 0);
                flock($handle, LOCK_UN);
                fclose($handle);
                @unlink($path);

                return;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($next));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
