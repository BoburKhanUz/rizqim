<?php

namespace App\Services\Admin\Integrations;

use App\Domain\Integrations\IntegrationEvent;
use App\Services\Audit\AuditRedactor;
use App\Services\Admin\Today\PlatformStats;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-31 Integratsiyalar → Hodisalar jurnali: the integration journal, newest first, read-only. Bodies and error texts go
 * through AuditRedactor (keys that look secret dropped to "[redacted]", phone numbers masked), whoever reads them: the
 * journal is for finding what broke, not for personal data.
 */
final class IntegrationEvents
{
    public const ROW = 'array{uuid: string, connection: array{uuid: string, provider: string, provider_label: string, store: array{id: int, name: string}|null}|null, direction: string, direction_label: string, type: string, status: string, status_label: string, http_status: int|null, attempts: int, next_attempt_at: string|null, error: string|null, subject: array{type: string, id: int|null}|null, duration_ms: int|null, created_at: string|null}';

    public const DETAIL = 'array{uuid: string, connection: array{uuid: string, provider: string, provider_label: string, store: array{id: int, name: string}|null}|null, direction: string, direction_label: string, type: string, status: string, status_label: string, http_status: int|null, attempts: int, next_attempt_at: string|null, error: string|null, subject: array{type: string, id: int|null}|null, duration_ms: int|null, created_at: string|null, idempotency_key: string|null, signature_valid: bool|null, last_attempt_at: string|null, request_body: array<array-key, mixed>|null, response_body: array<array-key, mixed>|null}';

    /**
     * @param  array<string, mixed>  $filters  connection (uuid), store_id, status, type, direction, from, to (Y-m-d, Tashkent)
     * @return Builder<IntegrationEvent>
     */
    public function query(array $filters): Builder
    {
        $query = IntegrationEvent::query()->with(['connection.store:id,name']);

        if (filled($filters['connection'] ?? null)) {
            $query->whereHas('connection', fn (Builder $connection) => $connection->where('uuid', strtolower((string) $filters['connection'])));
        }
        if (filled($filters['store_id'] ?? null)) {
            $query->whereHas('connection', fn (Builder $connection) => $connection->where('store_id', (int) $filters['store_id']));
        }
        foreach (['status', 'type', 'direction'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('created_at', '>=', CarbonImmutable::parse((string) $filters['from'], PlatformStats::TIMEZONE)->startOfDay()->utc());
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('created_at', '<', CarbonImmutable::parse((string) $filters['to'], PlatformStats::TIMEZONE)->addDay()->startOfDay()->utc());
        }

        return $query;
    }

    public function find(string $uuid): ?IntegrationEvent
    {
        return IntegrationEvent::query()->with(['connection.store:id,name'])->where('uuid', strtolower($uuid))->first();
    }

    /**
     * @param  iterable<IntegrationEvent>  $events
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $events): array
    {
        $rows = [];
        foreach ($events as $event) {
            $rows[] = $this->row($event);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function row(IntegrationEvent $event): array
    {
        $connection = $event->connection;

        return [
            'uuid' => $event->uuid,
            'connection' => $connection ? [
                'uuid' => $connection->uuid,
                'provider' => $connection->provider->value,
                'provider_label' => $connection->provider->label(),
                'store' => $connection->store ? ['id' => (int) $connection->store->id, 'name' => (string) $connection->store->name] : null,
            ] : null,
            'direction' => $event->direction->value,
            'direction_label' => $event->direction->label(),
            'type' => $event->type,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'http_status' => $event->http_status,
            'attempts' => (int) $event->attempts,
            'next_attempt_at' => $event->next_attempt_at?->toIso8601String(),
            'error' => IntegrationConnections::redactText($event->error),
            'subject' => $event->subject_type !== null ? ['type' => $event->subject_type, 'id' => $event->subject_id] : null,
            'duration_ms' => $event->duration_ms,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(IntegrationEvent $event): array
    {
        return [
            ...$this->row($event),
            'idempotency_key' => $event->idempotency_key,
            'signature_valid' => $event->signature_valid,
            'last_attempt_at' => $event->last_attempt_at?->toIso8601String(),
            'request_body' => $event->request_body !== null ? AuditRedactor::clean($event->request_body) : null,
            'response_body' => $event->response_body !== null ? AuditRedactor::clean($event->response_body) : null,
        ];
    }
}
