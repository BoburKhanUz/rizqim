<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Helpers for the P2-12 management API tests: a second store with its own owner and branch, requests as a user, and
 * a staff PIN session token from a paired terminal.
 */
trait ManagementFixtures
{
    use AccessFixtures;
    use DeliveryZoneFixtures;

    protected Store $foreignStore;
    protected User $foreignOwner;
    protected \App\Models\Branch $foreignBranch;

    protected function setUpManagement(): void
    {
        $this->setUpLifecycleStore();
        $this->foreignStore = $this->makeStore('Foreign Grill');
        $this->foreignBranch = $this->makeBranch($this->foreignStore, 'Foreign Branch');
        $this->foreignOwner = $this->makeStaff(UserRole::OWNER, null, $this->foreignStore);
    }

    protected function as(?User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $request = $user ? $this->actingAs($user, 'sanctum') : $this;

        return $request->json($method, $uri, $data);
    }

    /**
     * A terminal PIN session token of $user (signed in on a terminal of their branch).
     */
    protected function pinSession(User $user): string
    {
        $this->givePin($user, '5926');
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        return (string) $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $user->id, 'pin' => '5926'])
            ->assertOk()
            ->json('data.token');
    }
}
