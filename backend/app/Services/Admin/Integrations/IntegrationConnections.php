<?php

namespace App\Services\Admin\Integrations;

use App\Domain\Integrations\ExternalRef;
use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationConnectionStatus;
use App\Domain\Integrations\IntegrationEvent;
use App\Models\User;
use App\Services\Admin\System\SecretMask;
use App\Services\Audit\AuditRedactor;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-31 Integratsiyalar → Ulanishlar: the connections list, the connection card (health, credentials as "configured"
 * and last 4 only, settings through AuditRedactor, the last events, the menu map counts) and the team's pause / resume
 * (admin.integrations.manage, a reason). Nothing here talks to an outside system: phase 5 adapters do.
 *
 * The health verdict follows docs/PHASE5_PLAN.md 5: a score of 80 and above is `good`, 50-79 `attention`, under 50
 * `disconnected`; no score yet is `unknown`.
 */
final class IntegrationConnections
{
    public const HEALTH = 'array{score: int|null, verdict: string, last_success_at: string|null, last_webhook_at: string|null, last_error_at: string|null, last_error: string|null, errors_today: int, checked_at: string|null}';

    public const ROW = 'array{uuid: string, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, provider: string, provider_label: string, direction: string, direction_label: string, mode: string, mode_label: string, status: string, status_label: string, health: ' . self::HEALTH . ', paused: array{at: string|null, by: array{id: int, name: string}|null, reason: string|null}|null, created_at: string|null}';

    /**
     * @param  array<string, mixed>  $filters  status, provider, direction, mode, store_id, health (good | attention | disconnected | unknown), q
     * @return Builder<IntegrationConnection>
     */
    public function query(array $filters): Builder
    {
        $query = IntegrationConnection::query()->with(['store:id,name', 'branch:id,name', 'pausedBy:id,name']);

        foreach (['status', 'provider', 'direction', 'mode'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['store_id'] ?? null)) {
            $query->where('store_id', (int) $filters['store_id']);
        }
        match ($filters['health'] ?? null) {
            'good' => $query->where('health_score', '>=', 80),
            'attention' => $query->whereBetween('health_score', [50, 79]),
            'disconnected' => $query->where('health_score', '<', 50),
            'unknown' => $query->whereNull('health_score'),
            default => null,
        };
        if (filled($filters['q'] ?? null)) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']) . '%';
            $query->where(fn (Builder $inner) => $inner->whereHas('store', fn (Builder $store) => $store->where('name', 'ilike', $like))
                ->orWhereHas('branch', fn (Builder $branch) => $branch->where('name', 'ilike', $like)));
        }

        return $query;
    }

    public function find(string $uuid): ?IntegrationConnection
    {
        return IntegrationConnection::query()->with(['store:id,name', 'branch:id,name', 'pausedBy:id,name'])->where('uuid', strtolower($uuid))->first();
    }

    /**
     * Per status (all connections, filters ignored), plus how many need a look (score under 80).
     *
     * @return array{total: int, configuring: int, active: int, paused: int, disconnected: int, attention: int}
     */
    public function counts(): array
    {
        $byStatus = IntegrationConnection::query()->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');
        $count = fn (IntegrationConnectionStatus $status): int => (int) ($byStatus[$status->value] ?? 0);

        return [
            'total' => (int) $byStatus->sum(),
            'configuring' => $count(IntegrationConnectionStatus::CONFIGURING),
            'active' => $count(IntegrationConnectionStatus::ACTIVE),
            'paused' => $count(IntegrationConnectionStatus::PAUSED),
            'disconnected' => $count(IntegrationConnectionStatus::DISCONNECTED),
            'attention' => IntegrationConnection::query()->where('health_score', '<', 80)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function row(IntegrationConnection $connection): array
    {
        $paused = $connection->isPaused() ? [
            'at' => $connection->paused_at?->toIso8601String(),
            'by' => $connection->pausedBy ? ['id' => (int) $connection->pausedBy->id, 'name' => (string) $connection->pausedBy->name] : null,
            'reason' => $connection->pause_reason,
        ] : null;

        return [
            'uuid' => $connection->uuid,
            'store' => $connection->store ? ['id' => (int) $connection->store->id, 'name' => (string) $connection->store->name] : null,
            'branch' => $connection->branch ? ['id' => (int) $connection->branch->id, 'name' => (string) $connection->branch->name] : null,
            'provider' => $connection->provider->value,
            'provider_label' => $connection->provider->label(),
            'direction' => $connection->direction->value,
            'direction_label' => $connection->direction->label(),
            'mode' => $connection->mode->value,
            'mode_label' => $connection->mode->label(),
            'status' => $connection->status->value,
            'status_label' => $connection->status->label(),
            'health' => [
                'score' => $connection->health_score,
                'verdict' => self::verdict($connection->health_score),
                'last_success_at' => $connection->last_success_at?->toIso8601String(),
                'last_webhook_at' => $connection->last_webhook_at?->toIso8601String(),
                'last_error_at' => $connection->last_error_at?->toIso8601String(),
                'last_error' => self::redactText($connection->last_error),
                'errors_today' => (int) $connection->errors_today,
                'checked_at' => $connection->health_checked_at?->toIso8601String(),
            ],
            'paused' => $paused,
            'created_at' => $connection->created_at?->toIso8601String(),
        ];
    }

    /**
     * The card: the row, its credentials (names, "configured", last 4), settings (secrets dropped, phones masked), the
     * last events and the menu map counts.
     *
     * @return array<string, mixed>
     */
    public function card(IntegrationConnection $connection, IntegrationEvents $events, StuckIntegrationOrders $stuck): array
    {
        $credentials = [];
        foreach ($connection->credentialKeys() as $key) {
            $credentials[] = ['key' => $key, ...SecretMask::describe($connection->credential($key))];
        }

        $refs = ExternalRef::query()->where('integration_connection_id', $connection->id)->groupBy('entity_type')
            ->selectRaw('entity_type, count(*) as total')->pluck('total', 'entity_type')->map(fn ($total) => (int) $total)->all();

        $recent = IntegrationEvent::query()->with(['connection.store:id,name'])->where('integration_connection_id', $connection->id)
            ->orderByDesc('id')->limit(10)->get();

        return [
            ...$this->row($connection),
            'credentials' => $credentials,
            'settings' => AuditRedactor::clean($connection->settings ?? []),
            'recent_events' => $events->rows($recent),
            'menu_map' => ['total' => array_sum($refs), 'by_type' => $refs],
            'stuck_orders' => $stuck->query()->where('integration_events.integration_connection_id', $connection->id)->count(),
        ];
    }

    /**
     * Pauses the connection (phase 5 adapters send nothing for a paused one). Answers false when it already was.
     */
    public function pause(IntegrationConnection $connection, User $member, string $reason): bool
    {
        if ($connection->isPaused()) {
            return false;
        }

        $connection->forceFill([
            'status_before_pause' => $connection->status,
            'status' => IntegrationConnectionStatus::PAUSED,
            'paused_at' => now(),
            'paused_by' => $member->id,
            'pause_reason' => mb_substr($reason, 0, 500),
        ])->save();

        return true;
    }

    /**
     * Back to the status before the pause (`configuring` when that is unknown). Answers false when it was not paused.
     */
    public function resume(IntegrationConnection $connection): bool
    {
        if (!$connection->isPaused()) {
            return false;
        }

        $previous = $connection->status_before_pause;
        $connection->forceFill([
            'status' => $previous !== null && $previous !== IntegrationConnectionStatus::PAUSED ? $previous : IntegrationConnectionStatus::CONFIGURING,
            'status_before_pause' => null,
            'paused_at' => null,
            'paused_by' => null,
            'pause_reason' => null,
        ])->save();

        return true;
    }

    public static function verdict(?int $score): string
    {
        return match (true) {
            $score === null => 'unknown',
            $score >= 80 => 'good',
            $score >= 50 => 'attention',
            default => 'disconnected',
        };
    }

    public static function redactText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $clean = AuditRedactor::clean(['text' => $text])['text'];

        return is_string($clean) ? $clean : null;
    }
}
