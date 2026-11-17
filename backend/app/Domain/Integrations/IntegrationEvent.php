<?php

namespace App\Domain\Integrations;

use Database\Factories\Integrations\IntegrationEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * P4-31 (docs/PHASE5_PLAN.md 4.3, 4.4): one call to or from an outside system. Writers (phase 5) never put tokens or
 * secrets into request_body / response_body; readers show them through AuditRedactor anyway (phone numbers masked,
 * secret-looking keys dropped).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $integration_connection_id
 * @property IntegrationEventDirection $direction
 * @property string $type
 * @property string|null $idempotency_key
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<array-key, mixed>|null $request_body
 * @property array<array-key, mixed>|null $response_body
 * @property int|null $http_status
 * @property bool|null $signature_valid
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $last_attempt_at
 * @property \Illuminate\Support\Carbon|null $next_attempt_at
 * @property IntegrationEventStatus $status
 * @property string|null $error
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationEvent extends Model
{
    /** @use HasFactory<IntegrationEventFactory> */
    use HasFactory;
    use MassPrunable;

    // Order pushes (`order.push`) are what "Qotib qolgan buyurtmalar" watches
    public const TYPE_ORDER_PUSH = 'order.push';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * M3: finished calls (confirmed by the other side, or dead: out of retries) older than 90 days are deleted daily by
     * `php artisan model:prune --model=...IntegrationEvent` (App\Providers\PartitionsServiceProvider: this model is
     * outside app/Models, which the plain model:prune scans). Pending, sent and failed (still retried) calls are kept.
     *
     * @return Builder<IntegrationEvent>
     */
    public function prunable(): Builder
    {
        return static::whereIn('status', [IntegrationEventStatus::ACKNOWLEDGED->value, IntegrationEventStatus::DEAD->value])
            ->where('created_at', '<', now()->subDays(90));
    }

    protected static function newFactory(): IntegrationEventFactory
    {
        return IntegrationEventFactory::new();
    }

    protected function casts(): array
    {
        return [
            'direction' => IntegrationEventDirection::class,
            'status' => IntegrationEventStatus::class,
            'subject_id' => 'integer',
            'request_body' => 'array',
            'response_body' => 'array',
            'http_status' => 'integer',
            'signature_valid' => 'boolean',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
