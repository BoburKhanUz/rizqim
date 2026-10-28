<?php

namespace App\Domain\Integrations;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Database\Factories\Integrations\IntegrationConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * P4-31 (docs/PHASE5_PLAN.md 4.3): a store's (or branch's) link to an outside system. Phase 4 keeps the data model and
 * the admin read-out (Integratsiyalar → Ulanishlar); phase 5 adapters fill it. `credentials` is encrypted at rest and
 * hidden from every serialisation: callers read single keys through credentialKeys() / credential().
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int|null $branch_id
 * @property IntegrationProvider $provider
 * @property IntegrationDirection $direction
 * @property IntegrationMode $mode
 * @property IntegrationConnectionStatus $status
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $last_success_at
 * @property \Illuminate\Support\Carbon|null $last_webhook_at
 * @property \Illuminate\Support\Carbon|null $last_error_at
 * @property string|null $last_error
 * @property int $errors_today
 * @property int|null $health_score
 * @property \Illuminate\Support\Carbon|null $health_checked_at
 * @property \Illuminate\Support\Carbon|null $paused_at
 * @property int|null $paused_by
 * @property string|null $pause_reason
 * @property IntegrationConnectionStatus|null $status_before_pause
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationConnection extends Model
{
    /** @use HasFactory<IntegrationConnectionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    // Never in toArray()/toJson(), an API resource or a log context
    protected $hidden = ['credentials'];

    protected static function booted(): void
    {
        static::creating(function (self $connection): void {
            $connection->uuid ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): IntegrationConnectionFactory
    {
        return IntegrationConnectionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'direction' => IntegrationDirection::class,
            'mode' => IntegrationMode::class,
            'status' => IntegrationConnectionStatus::class,
            'status_before_pause' => IntegrationConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_success_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'last_error_at' => 'datetime',
            'errors_today' => 'integer',
            'health_score' => 'integer',
            'health_checked_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    /**
     * @return HasMany<IntegrationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class);
    }

    /**
     * @return HasMany<ExternalRef, $this>
     */
    public function externalRefs(): HasMany
    {
        return $this->hasMany(ExternalRef::class);
    }

    /**
     * The names of the stored credentials (never their values).
     *
     * @return list<string>
     */
    public function credentialKeys(): array
    {
        return array_map('strval', array_keys($this->credentials ?? []));
    }

    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    public function isPaused(): bool
    {
        return $this->status === IntegrationConnectionStatus::PAUSED;
    }
}
