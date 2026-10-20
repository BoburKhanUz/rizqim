<?php

namespace Tests\Feature;

use App\Enums\CourierType;
use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\OrderAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-36: a courier who works only in the app (no Telegram; the restaurant's own or a Rizqim fleet courier) is assigned
 * like any other. The assignment never depends on Telegram: a Telegram courier hears about it there, an app-only one
 * by push, and one with neither still gets the order.
 */
class CourierWithoutTelegramTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPush();
        $this->setUpLifecycleStore();
        config(['services.telegram.bot_token' => '123456:app-courier']);
        $this->fakeFirebase();

        $this->manager->update(['telegram_id' => '5001']);
        $this->owner->update(['telegram_id' => '5003']);
        $this->courier->update(['telegram_id' => '5002']);
    }

    /**
     * A courier the store added in the panel or by API invite who never opened the Telegram link, signed in to the
     * courier app by SMS instead.
     */
    private function appCourier(CourierType $type = CourierType::OWN, ?string $pushToken = 'app-courier-token-1', array $attributes = []): User
    {
        $courier = $this->makeStaff(UserRole::COURIER, $this->branch, null, $attributes + [
            'telegram_id' => null,
            'telegram_invite_token' => (string) Str::uuid(),
            'courier_type' => $type->value,
        ]);
        $this->makeDevice($courier, DeviceType::COURIER, $pushToken);

        return $courier;
    }

    /**
     * @return list<string> chat ids that got a Telegram message so far
     */
    private function telegramChats(): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage'))
            ->map(fn ($pair) => (string) $pair[0]['chat_id'])->values()->all();
    }

    private function telegramTexts(): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage'))
            ->map(fn ($pair) => (string) $pair[0]['text'])->values()->all();
    }

    private function assign(User $courier)
    {
        $order = $this->makeOrder(OrderStatus::READY);

        return [$order, $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $courier->id])];
    }

    public function test_an_app_only_courier_is_assigned_and_told_by_push(): void
    {
        foreach ([CourierType::OWN, CourierType::FLEET] as $index => $type) {
            $courier = $this->appCourier($type, "app-courier-token-{$index}");

            [$order, $response] = $this->assign($courier);
            $response->assertOk();

            $order->refresh();
            $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->status);
            $this->assertSame($courier->id, (int) $order->courier_id);
            $this->assertTrue(OrderAssignment::where('order_id', $order->id)->where('courier_id', $courier->id)->where('is_current', true)->exists());

            $push = $this->fcmRequests()->filter(fn ($request) => $request['message']['token'] === "app-courier-token-{$index}")->sole();
            $this->assertSame('Buyurtma sizga biriktirildi', $push['message']['notification']['title']);
            $this->assertSame('courier_tasks', $push['message']['data']['type']);
        }

        // No Telegram message to a courier who has none
        $this->assertNotContains('', $this->telegramChats());
    }

    public function test_a_telegram_courier_is_still_told_on_telegram(): void
    {
        [$order, $response] = $this->assign($this->courier);
        $response->assertOk();

        $this->assertSame($this->courier->id, (int) $order->fresh()->courier_id);
        $this->assertContains('5002', $this->telegramChats());
    }

    public function test_an_unclaimed_invite_placeholder_is_never_messaged_as_a_chat(): void
    {
        // An API invite leaves a 'temp_…' placeholder until the Telegram link is opened
        $courier = $this->appCourier(attributes: ['telegram_id' => 'temp_6716a1b2c3']);

        [$order, $response] = $this->assign($courier);
        $response->assertOk();

        $this->assertSame($courier->id, (int) $order->fresh()->courier_id);
        $this->assertNotContains('temp_6716a1b2c3', $this->telegramChats());
        $this->assertSame(['app-courier-token-1'], $this->fcmTokens());
    }

    public function test_a_courier_reached_by_nothing_is_still_assigned(): void
    {
        // Signed in to the app, but no push token yet (or Firebase is not set up)
        $courier = $this->appCourier(pushToken: null);

        [$order, $response] = $this->assign($courier);
        $response->assertOk();

        $this->assertSame($courier->id, (int) $order->fresh()->courier_id);
        $this->assertSame([], $this->fcmTokens());

        $this->setUpPush(configured: false);
        $other = $this->appCourier(pushToken: 'app-courier-token-9');
        [$order, $response] = $this->assign($other);
        $response->assertOk();
        $this->assertSame($other->id, (int) $order->fresh()->courier_id);
    }

    public function test_auto_assignment_picks_an_app_only_courier(): void
    {
        // The Telegram courier already has an order in hand: the idle app courier is less loaded
        $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);
        $courier = $this->appCourier();
        $order = $this->makeOrder(OrderStatus::READY);

        $this->setStatus($order, $this->manager, 'courier_assigned')->assertOk();

        $this->assertSame($courier->id, (int) $order->fresh()->courier_id);
        $this->assertSame(['app-courier-token-1'], $this->fcmTokens());
    }

    public function test_the_courier_list_offers_app_only_couriers(): void
    {
        $courier = $this->appCourier(CourierType::FLEET);

        $listed = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/couriers')->assertOk()->json('data'))
            ->firstWhere('id', $courier->id);

        $this->assertNotNull($listed);
        $this->assertSame('fleet', $listed['courier_type']);
    }

    public function test_a_courier_without_telegram_or_a_live_app_is_not_assignable(): void
    {
        // Never signed in anywhere: an unclaimed invite
        $invited = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => null, 'telegram_invite_token' => (string) Str::uuid()]);
        // Signed in once, but the owner revoked the phone
        $revoked = $this->appCourier();
        Device::where('user_id', $revoked->id)->update(['revoked_at' => now()]);

        foreach ([$invited, $revoked] as $courier) {
            [$order, $response] = $this->assign($courier);
            $response->assertUnprocessable();
            $this->assertNull($order->fresh()->courier_id);
        }
    }

    public function test_an_app_only_courier_on_the_way_does_not_send_the_no_courier_card_to_managers(): void
    {
        $courier = $this->appCourier();
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $courier->id]);

        $this->setStatus($order, $courier, 'on_the_way')->assertOk();

        $this->assertSame(OrderStatus::ON_THE_WAY, $order->fresh()->status);
        foreach ($this->telegramTexts() as $text) {
            $this->assertStringNotContainsString("Kuryer yo'qligi sababli", $text);
        }
    }
}
