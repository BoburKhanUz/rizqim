<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\StaffPinService;
use Illuminate\Testing\TestResponse;

/**
 * Helpers for the P1-12 access tests: real bearer tokens (each request authenticates afresh), paired terminals, PINs.
 */
trait AccessFixtures
{
    use OrderLifecycleFixtures;

    /**
     * A request with this bearer token only: no user left over from an earlier request of the test.
     */
    protected function withBearer(?string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $token === null ? $this : $this->withToken($token);
    }

    protected function bearerJson(string $method, string $uri, ?string $token, array $data = []): TestResponse
    {
        return $this->withBearer($token)->json($method, $uri, $data);
    }

    /**
     * @return array{device: Device, token: string}
     */
    protected function pairTerminal(User $creator, ?Branch $branch = null, DeviceType $type = DeviceType::POS): array
    {
        $service = app(DevicePairingService::class);
        $code = $service->createCode($creator->store, $branch, $type, 'Kassa', $creator)['code'];

        return $service->pair($code, ['platform' => 'android']);
    }

    protected function givePin(User $user, string $pin = '4831'): User
    {
        app(StaffPinService::class)->setPin($user, $pin, null);

        return $user->refresh();
    }
}
