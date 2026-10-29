<?php

namespace Database\Factories\Integrations;

use App\Domain\Integrations\ExternalRef;
use App\Domain\Integrations\ExternalRefEntity;
use App\Domain\Integrations\IntegrationConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * P4-31: a product mapped to an outside dish id.
 *
 * @extends Factory<ExternalRef>
 */
class ExternalRefFactory extends Factory
{
    protected $model = ExternalRef::class;

    public function definition(): array
    {
        static $entity = 0;

        return [
            'integration_connection_id' => IntegrationConnection::factory(),
            'entity_type' => ExternalRefEntity::PRODUCT,
            'entity_id' => ++$entity,
            'external_id' => 'ext-' . Str::lower(Str::random(12)),
            'meta' => null,
        ];
    }
}
