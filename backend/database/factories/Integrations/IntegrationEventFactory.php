<?php

namespace Database\Factories\Integrations;

use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationEvent;
use App\Domain\Integrations\IntegrationEventDirection;
use App\Domain\Integrations\IntegrationEventStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * P4-31: an acknowledged outbound order push of a new connection.
 *
 * @extends Factory<IntegrationEvent>
 */
class IntegrationEventFactory extends Factory
{
    protected $model = IntegrationEvent::class;

    public function definition(): array
    {
        return [
            'integration_connection_id' => IntegrationConnection::factory(),
            'direction' => IntegrationEventDirection::OUTBOUND,
            'type' => IntegrationEvent::TYPE_ORDER_PUSH,
            'idempotency_key' => (string) Str::uuid(),
            'subject_type' => null,
            'subject_id' => null,
            'request_body' => ['eatsId' => 'R-' . Str::random(6)],
            'response_body' => ['result' => 'OK'],
            'http_status' => 200,
            'signature_valid' => null,
            'attempts' => 1,
            'last_attempt_at' => now()->subMinute(),
            'status' => IntegrationEventStatus::ACKNOWLEDGED,
            'duration_ms' => 180,
        ];
    }
}
