<?php

namespace Database\Factories\Integrations;

use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationConnectionStatus;
use App\Domain\Integrations\IntegrationDirection;
use App\Domain\Integrations\IntegrationMode;
use App\Domain\Integrations\IntegrationProvider;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * P4-31: a sandbox Alipos connection of a new store, with a fake key and a healthy record.
 *
 * @extends Factory<IntegrationConnection>
 */
class IntegrationConnectionFactory extends Factory
{
    protected $model = IntegrationConnection::class;

    public function definition(): array
    {
        return [
            'store_id' => fn () => Store::create(['name' => 'Integratsiya ' . Str::random(6), 'slug' => 'integration-' . Str::lower(Str::random(10)), 'commission_rate' => 10, 'status' => 'active'])->id,
            'branch_id' => null,
            'provider' => IntegrationProvider::ALIPOS,
            'direction' => IntegrationDirection::RIZQIM_TO_POS,
            'mode' => IntegrationMode::SANDBOX,
            'status' => IntegrationConnectionStatus::ACTIVE,
            'credentials' => ['api_key' => 'sk_test_' . Str::random(24)],
            'settings' => ['terminal_id' => 'T-1'],
            'last_success_at' => now()->subMinutes(2),
            'errors_today' => 0,
            'health_score' => 100,
            'health_checked_at' => now()->subMinute(),
        ];
    }

    public function paused(string $reason = 'Sinov'): static
    {
        return $this->state(fn () => [
            'status' => IntegrationConnectionStatus::PAUSED,
            'status_before_pause' => IntegrationConnectionStatus::ACTIVE,
            'paused_at' => now()->subHour(),
            'pause_reason' => $reason,
        ]);
    }
}
