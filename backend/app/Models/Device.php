<?php

namespace App\Models;

use App\Enums\DeviceApp;
use App\Enums\DeviceType;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person's own app install (user_id set) or a store's paired POS/KDS terminal (user_id null). A terminal
 * authenticates as itself with its own Sanctum token (the `device` guard), never as a user.
 *
 * @property DeviceType $type
 * @property DeviceApp|null $app P6 stage B: the POS app it runs, as sent at pairing (null: paired before)
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property int $sync_last_seq P3-31: the offline queue as the till last reported it
 * @property int $sync_pending_ops
 * @property \Illuminate\Support\Carbon|null $sync_oldest_op_at
 * @property \Illuminate\Support\Carbon|null $sync_reported_at
 * @property int|null $sync_clock_skew_seconds
 * @property string|null $sync_key M5 S3: the till's queue signing key (base64, encrypted at rest; App\Domain\Pos\Sync\SyncSignature only)
 * @property int $sync_key_id its number (kid; 0: no key yet)
 * @property string|null $sync_key_prev the key before the last rotation
 * @property string|null $sync_chain the head of the chain of signed operations the server holds (`kid:mac`)
 * @property bool $smart_kassa P6-16: an Android smart kassa (receipts through the provider's virtual kassa)
 * @property string|null $fiscal_kassa_id P6-16: the provider's id of that kassa
 * @property int|null $display_for_device_id P6-14: a customer display follows this till's cart (null: the branch's latest)
 * @property string|null $uzqr_api_key Wave P: this till's UzQR API key (encrypted at rest; never returned: UzQrTillKeys)
 * @property string|null $uzqr_api_key_last4 its last four characters (what the owner sees)
 * @property \Illuminate\Support\Carbon|null $uzqr_api_key_set_at
 */
class Device extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;
    use \App\Traits\HasPublicUuid;

    protected $fillable = [
        'user_id',
        'store_id',
        'branch_id',
        'type',
        'app',
        'name',
        'platform',
        'app_version',
        'push_token',
        'paired_by',
        'last_seen_at',
        'display_for_device_id',
    ];

    /**
     * The push token is a delivery credential for P1-22's sender, not something to show back.
     */
    protected $hidden = [
        'id',
        'push_token',
        'remember_token',
        // M5 S3: the queue signing keys never leave the server after they were issued
        'sync_key',
        'sync_key_prev',
        'sync_chain',
        // Wave P: the till's UzQR API key never leaves the server
        'uzqr_api_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'app' => DeviceApp::class,
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            // P3-31: the offline queue as the till last reported it (App\Domain\Pos\Sync)
            'sync_last_seq' => 'integer',
            'sync_pending_ops' => 'integer',
            'sync_oldest_op_at' => 'datetime',
            'sync_reported_at' => 'datetime',
            'sync_clock_skew_seconds' => 'integer',
            // M5 S3: the queue signing keys, encrypted at rest (App\Domain\Pos\Sync\SyncSignature)
            'sync_key' => 'encrypted',
            'sync_key_id' => 'integer',
            'sync_key_prev' => 'encrypted',
            // P6-16 (App\Domain\Pos\TerminalFlow)
            'smart_kassa' => 'boolean',
            // Wave P: the till's UzQR API key, encrypted at rest (App\Domain\Pos\QrPayments\UzQr\UzQrTillKeys)
            'uzqr_api_key' => 'encrypted',
            'uzqr_api_key_set_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isTerminal(): bool
    {
        return $this->type->isTerminal();
    }

    /**
     * Devices have no password: Authenticatable is only here so a terminal can be the `device` guard's user.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @param  Builder<Device>  $query
     * @return Builder<Device>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    public function pairedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paired_by');
    }

    /**
     * @return HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * The shape apps and owners see (no ids, no push token).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'name' => $this->name,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'store_id' => $this->store_id,
            'branch_id' => $this->branch_id,
            'user' => $this->user_id && $this->relationLoaded('user') && $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name, 'role' => $this->user->role->value]
                : null,
            'has_push_token' => filled($this->push_token),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
