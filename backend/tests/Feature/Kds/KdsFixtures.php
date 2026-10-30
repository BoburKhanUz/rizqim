<?php

namespace Tests\Feature\Kds;

use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\ProductVariant;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosQueue\PosQueueFixtures;

/**
 * P6-33 helpers: the P6-13 fast food branch (a till with its cashier, a cook, order numbers and calling) with KDS v2
 * stations — Grill (shashlik, 300 s), Ichimliklar (choy, 90 s) and the expo station Yig'ish (120 s).
 */
trait KdsFixtures
{
    use PosQueueFixtures;

    protected KitchenStation $grill;

    protected KitchenStation $drinks;

    protected KitchenStation $expo;

    protected function setUpKds(string $profile = 'fast_food'): void
    {
        $this->setUpPosQueue($profile);

        $station = fn (string $name, string $kind, ?int $target, int $order) => KitchenStation::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => $name, 'kind' => $kind,
            'sort_order' => $order, 'target_seconds' => $target,
        ]);
        $this->grill = $station('Grill', 'grill', 300, 1);
        $this->drinks = $station('Ichimliklar', 'drinks', 90, 2);
        $this->expo = $station("Yig'ish", 'expo', 120, 3);

        $this->route($this->shashlik, $this->grill);
        $this->route($this->tea, $this->drinks);
    }

    protected function route(ProductVariant $variant, KitchenStation $station): void
    {
        KitchenStationRoute::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'station_id' => $station->id,
            'product_id' => $variant->product_id,
        ]);
    }

    /**
     * A takeaway with 2 x Shashlik (grill) and 1 x Choy (drinks), sent at once.
     */
    protected function openCombo(): string
    {
        return (string) $this->tillAs('POST', '/pos/orders', [
            'channel' => 'pos_takeaway',
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 2], ['variant_id' => $this->tea->uuid, 'quantity' => 1]],
            'send' => true,
        ])->assertCreated()->json('data.uuid');
    }

    protected function cook(string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer(null);
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->cook, 'sanctum')->json($method, '/api/v1' . $uri, $data);
    }

    protected function owner(string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer(null);
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->owner, 'sanctum')->json($method, '/api/v1' . $uri, $data);
    }

    protected function bump(string $uuid, int $itemId): TestResponse
    {
        return $this->cook('POST', "/staff/orders/{$uuid}/items/{$itemId}/ready");
    }

    /**
     * The ticket of the order in GET /staff/kitchen/tickets (null when it is not listed).
     *
     * @return array<string, mixed>|null
     */
    protected function ticketOf(string $uuid, string $query = ''): ?array
    {
        foreach ((array) $this->cook('GET', '/staff/kitchen/tickets' . $query)->assertOk()->json('data') as $ticket) {
            if ($ticket['uuid'] === $uuid) {
                return $ticket;
            }
        }

        return null;
    }

    protected function useModules(array $modules): void
    {
        $settings = $this->branch->fresh()->pos_settings ?? [];
        $settings['modules'] = array_merge($settings['modules'] ?? [], $modules);
        $this->branch->forceFill(['pos_settings' => $settings])->save();
    }
}
