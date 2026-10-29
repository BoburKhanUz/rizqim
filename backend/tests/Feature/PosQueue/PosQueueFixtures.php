<?php

namespace Tests\Feature\PosQueue;

use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosSync\PosSyncFixtures;

/**
 * P6-13 helpers: the P3-31 branch (a till with its cashier and open shift) switched to a format with order numbers
 * and calling (`fast_food`: queue_numbers, queue_calling), a second till with its own cashier and shift, and a cook.
 */
trait PosQueueFixtures
{
    use PosSyncFixtures;

    /** @var array{device: \App\Models\Device, token: string} */
    protected array $till2;

    protected User $cashier2;

    protected string $cashier2Token;

    protected PosShift $shift2;

    protected User $cook;

    protected function setUpPosQueue(string $profile = 'fast_food'): void
    {
        $this->setUpPosSync();
        $this->useProfile($this->branch, $profile);

        $this->cashier2 = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->till2 = $this->pairTerminal($this->owner, $this->branch);
        $this->cashier2Token = $this->pinSession($this->till2, $this->cashier2, '7302');
        Carbon::setTestNow(now()->subHour());
        $this->shift2 = app(ShiftService::class)->open($this->cashier2, $this->till2['device'], Money::of('50000'));
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));

        $this->cook = $this->makeStaff(UserRole::COOK, $this->branch);
    }

    protected function useProfile(Branch $branch, string $profile): void
    {
        $branch->forceFill(['pos_settings' => array_merge($branch->pos_settings ?? [], ['profile' => $profile])])->save();
    }

    /**
     * A takeaway opened online at a till: 2 x Shashlik, sent at once; $queueNumber is the till's proposal.
     */
    protected function openTakeaway(?string $token = null, ?int $queueNumber = null, bool $send = true): TestResponse
    {
        return $this->tillAs('POST', '/pos/orders', array_filter([
            'channel' => 'pos_takeaway',
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 2]],
            'send' => $send,
            'queue_number' => $queueNumber,
        ], fn ($value) => $value !== null), $token);
    }

    /**
     * A walk-in takeaway the till sold offline with the number it printed from its block.
     *
     * @return array<string, mixed>
     */
    protected function offlineOpen(string $localNumber, ?int $queueNumber, ?User $staff = null): array
    {
        return $this->op('order.open', array_filter([
            'order' => (string) \Illuminate\Support\Str::uuid(),
            'local_number' => $localNumber,
            'queue_number' => $queueNumber,
            'channel' => 'pos_takeaway',
            'items' => [$this->line($this->tea, '1')],
            'send' => true,
            'menu_taken_at' => now()->subHour()->toJSON(),
        ], fn ($value) => $value !== null), $staff, 15);
    }

    protected function bootstrapQueue(?string $token = null): array
    {
        return (array) $this->tillAs('GET', '/pos/bootstrap', [], $token)->assertOk()->json('data.queue');
    }

    /**
     * The cook bumps every sent line of the order ready on the kitchen display.
     */
    protected function bumpAll(string $orderUuid): void
    {
        $order = \App\Models\Order::where('uuid', $orderUuid)->firstOrFail();
        foreach ($order->items()->whereNotNull('sent_at')->get() as $item) {
            $this->withBearer(null);
            $this->actingAs($this->cook, 'sanctum')
                ->postJson("/api/v1/staff/orders/{$orderUuid}/items/{$item->id}/ready")
                ->assertOk();
        }
        $this->withBearer(null);
    }
}
