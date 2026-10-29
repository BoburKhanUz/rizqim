<?php

namespace App\Domain\Integrations;

use Database\Factories\Integrations\ExternalRefFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-31 (docs/PHASE5_PLAN.md 4.3): a RIZQIM record (branch, category, product, variant, modifier, order) and its id in
 * the outside system of one connection; unique both ways.
 *
 * @property int $id
 * @property int $integration_connection_id
 * @property ExternalRefEntity $entity_type
 * @property int $entity_id
 * @property string $external_id
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExternalRef extends Model
{
    /** @use HasFactory<ExternalRefFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): ExternalRefFactory
    {
        return ExternalRefFactory::new();
    }

    protected function casts(): array
    {
        return [
            'entity_type' => ExternalRefEntity::class,
            'entity_id' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
