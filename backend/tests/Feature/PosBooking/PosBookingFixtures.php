<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\ManagementFixtures;

/**
 * P3-17 test helpers: the management store (two branches, a foreign store), a cashier, rooms, and fakes for the
 * contracts other tasks implement (fiscal issuer and lines, order opener, manager approvals) and for Telegram.
 */
trait PosBookingFixtures
{
    use ManagementFixtures;

    protected FakeFiscalIssuer $fiscal;
    protected FakeFiscalLineBuilder $fiscalLines;
    protected FakePosOrderOpener $opener;
    protected FakeManagerApprovals $approvals;
    protected FakeTelegramMessenger $telegram;
    protected User $cashier;
    protected int $roomA;
    protected int $roomB;
    protected int $roomC;
    protected int $otherBranchRoom;

    protected function setUpBookings(): void
    {
        $this->setUpManagement();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        $this->fiscal = new FakeFiscalIssuer();
        $this->fiscalLines = new FakeFiscalLineBuilder();
        $this->opener = new FakePosOrderOpener();
        $this->approvals = new FakeManagerApprovals();
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(FiscalIssuer::class, $this->fiscal);
        $this->app->instance(FiscalLineBuilder::class, $this->fiscalLines);
        $this->app->instance(PosOrderOpener::class, $this->opener);
        $this->app->instance(ManagerApprovals::class, $this->approvals);
        $this->app->instance(TelegramMessenger::class, $this->telegram);

        $this->roomA = $this->makeRoom($this->branch, 'Kabinka 1');
        $this->roomB = $this->makeRoom($this->branch, 'Kabinka 2');
        $this->roomC = $this->makeRoom($this->branch, 'Katta zal');
        $this->otherBranchRoom = $this->makeRoom($this->otherBranch, 'Kabinka 1');
    }

    protected function makeRoom(Branch $branch, string $name, bool $active = true): int
    {
        $hall = DB::table('halls')->where('branch_id', $branch->id)->value('id') ?? DB::table('halls')->insertGetId([
            'store_id' => $branch->store_id, 'branch_id' => $branch->id, 'name' => 'Ichki zal', 'sort_order' => 0,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('dining_tables')->insertGetId([
            'store_id' => $branch->store_id, 'branch_id' => $branch->id, 'hall_id' => $hall, 'name' => $name, 'kind' => 'room',
            'seats' => 12, 'sort_order' => 0, 'is_active' => $active, 'min_spend_mode' => 'per_table',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A JSON request as `$user` (a normal sign-in), with extra headers.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function api(User $user, string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->withHeaders($headers)->json($method, $uri, $data);
    }

    /**
     * A JSON request with a bearer token (a terminal PIN session).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function apiToken(string $token, string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withBearer($token)->withHeaders($headers)->json($method, $uri, $data);
    }

    /**
     * A PIN session token of `$user` on a new POS terminal of `$branch`.
     *
     * @return array{token: string, device_id: int}
     */
    protected function terminalSession(User $user, Branch $branch): array
    {
        $this->givePin($user, '5926');
        $terminal = $this->pairTerminal($this->owner, $branch, DeviceType::POS);
        $token = (string) $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $user->id, 'pin' => '5926'])
            ->assertOk()
            ->json('data.token');

        return ['token' => $token, 'device_id' => (int) $terminal['device']->id];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function bookingPayload(array $overrides = []): array
    {
        return $overrides + [
            'branch_id' => $this->branch->id,
            'dining_table_id' => $this->roomA,
            'starts_at' => '2026-11-05T19:00:00+05:00',
            'ends_at' => '2026-11-05T23:30:00+05:00',
            'guests_planned' => 12,
            'event_type' => 'gap',
            'contact_name' => "Jo'ra boshi Akmal",
            'contact_phone' => '90 123 45 67',
            'source' => 'phone',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createBooking(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->api($as ?? $this->cashier, 'POST', '/api/v1/pos/bookings', $this->bookingPayload($overrides));
    }

    /**
     * A booking created through the API; its uuid.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function booked(array $overrides = []): string
    {
        return (string) $this->createBooking($overrides)->assertCreated()->json('data.uuid');
    }

    protected function takeDeposit(string $uuid, string $amount = '1000000', string $tender = 'qr', ?string $key = null, ?User $as = null): TestResponse
    {
        return $this->api($as ?? $this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", [
            'amount' => $amount,
            'tender' => $tender,
        ], ['Idempotency-Key' => $key ?? 'dep-' . bin2hex(random_bytes(4))]);
    }
}
