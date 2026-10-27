<?php

namespace App\Services\Admin\Monitoring;

use App\Services\Audit\AuditRedactor;
use App\Support\SentryScrubber;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * P4-15: the failed_jobs table as the admin app reads it (Monitoring → Yiqilgan vazifalar). A row's payload holds the
 * serialized job, i.e. whatever the job carried (Telegram text, order ids, sometimes phone numbers or tokens), and its
 * exception text may quote a request or an SQL statement. Neither is ever shown as stored: every string goes through
 * SentryScrubber (tokens, bot URLs, phones, cards) and every structure through AuditRedactor (secret keys, phones),
 * and the serialized job is read without instantiating a single class.
 */
class FailedJobs
{
    private const MAX_TEXT = 20000;

    private const MAX_STRING = 600;

    private const MAX_ITEMS = 50;

    private const MAX_DEPTH = 6;

    /** SQL: the job's display name out of the JSON payload text, without loading the payload. */
    private const NAME_SQL = "substring(payload from '\"displayName\":\"([^\"]*)\"')";

    public function __construct(
        private readonly FailedJobProviderInterface $failer,
    ) {}

    /**
     * The list's query: one row per failed job with the job name and the exception's first line computed in SQL, so
     * the (large) payload and exception texts stay in the database.
     *
     * @param  array{queue?: string|null, q?: string|null, from?: string|null, to?: string|null}  $filters
     */
    public function listQuery(array $filters): Builder
    {
        $query = DB::table('failed_jobs')
            ->selectRaw('id, uuid, queue, connection, failed_at, ' . self::NAME_SQL . " as job_name, split_part(exception, E'\\n', 1) as first_line");

        if (!empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }
        if (!empty($filters['q'])) {
            // The stored name has doubled backslashes (JSON), a search typed with single ones must still match; then
            // LIKE's own escaping
            $term = str_replace('\\', '\\\\', (string) $filters['q']);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term) . '%';
            $query->whereRaw(self::NAME_SQL . ' ilike ?', [$like]);
        }
        if (!empty($filters['from'])) {
            $query->where('failed_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (!empty($filters['to'])) {
            $query->where('failed_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->orderByDesc('id');
    }

    /**
     * @return array{id: int, uuid: string, job: string, job_class: string, queue: string, connection: string, exception_line: string, failed_at: string|null}
     */
    public function presentRow(object $row): array
    {
        $name = $this->jobName($row->job_name ?? null);

        return [
            'id' => (int) $row->id,
            'uuid' => (string) $row->uuid,
            'job' => $this->shortName($name),
            'job_class' => $name,
            'queue' => (string) $row->queue,
            'connection' => (string) $row->connection,
            'exception_line' => $this->text((string) ($row->first_line ?? ''), 300),
            'failed_at' => $row->failed_at !== null ? Carbon::parse($row->failed_at)->toIso8601String() : null,
        ];
    }

    /**
     * The list row of one failed job (no payload, no full exception).
     *
     * @return array{id: int, uuid: string, job: string, job_class: string, queue: string, connection: string, exception_line: string, failed_at: string|null}|null
     */
    public function summary(string $uuid): ?array
    {
        $row = $this->listQuery([])->where('uuid', $uuid)->first();

        return $row !== null ? $this->presentRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(string $uuid): ?array
    {
        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if ($row === null) {
            return null;
        }

        $payload = json_decode((string) $row->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $name = $this->jobName($payload['displayName'] ?? null);
        $exception = (string) $row->exception;

        return [
            'id' => (int) $row->id,
            'uuid' => (string) $row->uuid,
            'job' => $this->shortName($name),
            'job_class' => $name,
            'queue' => (string) $row->queue,
            'connection' => (string) $row->connection,
            'exception_line' => $this->text(strtok($exception, "\n") ?: '', 300),
            'failed_at' => $row->failed_at !== null ? Carbon::parse($row->failed_at)->toIso8601String() : null,
            'attempts' => isset($payload['attempts']) ? (int) $payload['attempts'] : null,
            'max_tries' => isset($payload['maxTries']) ? (int) $payload['maxTries'] : null,
            'exception' => $this->text($exception, self::MAX_TEXT),
            'payload' => $this->redactedPayload($payload),
        ];
    }

    /**
     * Pushes the job back onto its queue and removes the row (php artisan queue:retry). False if it is not there
     * any more (retried or deleted by someone else meanwhile).
     */
    public function retry(string $uuid): bool
    {
        if (!$this->exists($uuid)) {
            return false;
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        // A row that is still there means the push did not happen
        return DB::table('failed_jobs')->where('uuid', $uuid)->doesntExist();
    }

    public function delete(string $uuid): bool
    {
        return $this->failer->forget($uuid);
    }

    public function exists(string $uuid): bool
    {
        return DB::table('failed_jobs')->where('uuid', $uuid)->exists();
    }

    /**
     * The most frequent job names among the failures since $since: [{job, count}].
     *
     * @return list<array{job: string, count: int}>
     */
    public static function topNames(Carbon $since, int $limit = 3): array
    {
        try {
            return DB::table('failed_jobs')
                ->selectRaw(self::NAME_SQL . ' as job_name, count(*) as failures')
                ->where('failed_at', '>=', $since)
                ->groupBy('job_name')
                ->orderByDesc('failures')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => ['job' => self::shortNameOf(self::jobNameOf($row->job_name)), 'count' => (int) $row->failures])
                ->all();
        } catch (Throwable $e) {
            Log::warning('Monitoring: top failed job names could not be read', ['exception' => $e::class]);

            return [];
        }
    }

    public function count(?Carbon $since = null): int
    {
        return DB::table('failed_jobs')->when($since, fn (Builder $query) => $query->where('failed_at', '>=', $since))->count();
    }

    public function latestFailedAt(): ?Carbon
    {
        $latest = DB::table('failed_jobs')->max('failed_at');

        return $latest !== null ? Carbon::parse($latest) : null;
    }

    // ------------------------------------------------------------------------------------------------------------

    private function jobName(mixed $name): string
    {
        return self::jobNameOf($name);
    }

    private static function jobNameOf(mixed $name): string
    {
        // The JSON text has doubled backslashes (App\\Jobs\\X): undo it when the name came from the raw text
        $name = is_string($name) && $name !== '' ? str_replace('\\\\', '\\', $name) : 'Noma\'lum vazifa';

        return mb_substr($name, 0, 200);
    }

    private function shortName(string $name): string
    {
        return self::shortNameOf($name);
    }

    private static function shortNameOf(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    /**
     * Free text with tokens, bot URLs, phones and card numbers masked.
     */
    private function text(string $text, int $limit): string
    {
        $text = mb_substr($text, 0, $limit);

        return (string) AuditRedactor::clean(['text' => SentryScrubber::scrubString($text)])['text'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactedPayload(array $payload): array
    {
        $command = $payload['data']['command'] ?? null;
        unset($payload['data']);

        $payload['command'] = is_string($command) ? $this->readCommand($command) : null;

        return $this->clean($payload);
    }

    /**
     * The serialized job as plain data. Nothing is instantiated (allowed_classes: false), so a payload can carry no
     * code; an encrypted or damaged command is reported as unavailable.
     */
    private function readCommand(string $command): mixed
    {
        $value = @unserialize($command, ['allowed_classes' => false]);

        return $value === false && $command !== 'b:0;' ? '[o\'qib bo\'lmadi]' : $this->plain($value, 0);
    }

    private function plain(mixed $value, int $depth): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            return is_string($value) ? mb_substr($value, 0, self::MAX_STRING) : $value;
        }
        if ($depth >= self::MAX_DEPTH) {
            return '[...]';
        }

        $plain = [];
        foreach (array_slice($value, 0, self::MAX_ITEMS, true) as $key => $item) {
            // Private and protected properties come out as "\0Class\0name" / "\0*\0name"
            $name = is_string($key) && str_contains($key, "\0") ? substr($key, (int) strrpos($key, "\0") + 1) : $key;
            $plain[$name] = $this->plain($item, $depth + 1);
        }
        if (count($value) > self::MAX_ITEMS) {
            $plain['...'] = (count($value) - self::MAX_ITEMS) . ' ta yana';
        }

        return $plain;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function clean(array $data): array
    {
        $data = AuditRedactor::clean($data);
        array_walk_recursive($data, function (mixed &$value) {
            if (is_string($value)) {
                $value = SentryScrubber::scrubString($value);
            }
        });

        return $data;
    }
}
