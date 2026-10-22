<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesApiTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        // The column default (customer) is only on the model once it is read back
        $this->customer->refresh();
    }

    /**
     * @return array<string, array<string, bool>> type => channel => enabled
     */
    protected function matrix($response): array
    {
        $matrix = [];
        foreach ($response->json('data.preferences') as $row) {
            foreach ($row['channels'] as $channel) {
                $matrix[$row['type']][$channel['channel']] = $channel['enabled'];
            }
        }

        return $matrix;
    }

    public function test_a_customer_starts_with_order_updates_on_and_promotions_off(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/me/notification-preferences')->assertOk();

        $this->assertSame([
            'order_updates' => ['telegram' => true, 'push' => true],
            'promotions' => ['telegram' => false, 'push' => false],
        ], $this->matrix($response));
    }

    public function test_each_role_sees_the_types_that_fit_it(): void
    {
        $types = fn ($user) => array_keys($this->matrix(
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/notification-preferences')->assertOk()
        ));

        $this->assertSame(['order_updates', 'staff_new_order', 'courier_tasks'], $types($this->manager));
        $this->assertSame(['order_updates', 'staff_new_order', 'courier_tasks', 'pos_alerts'], $types($this->owner));
        $this->assertSame(['order_updates', 'courier_tasks'], $types($this->courier));
        $this->assertSame(['order_updates', 'promotions'], $types($this->customer));
    }

    public function test_availability_says_whether_the_channel_can_reach_the_user(): void
    {
        $customer = $this->makeCustomer(attributes: ['telegram_id' => null])->refresh();

        $channels = fn () => collect($this->actingAs($customer, 'sanctum')->getJson('/api/v1/me/notification-preferences')
            ->json('data.preferences.0.channels'))->pluck('available', 'channel')->all();

        $this->assertSame(['telegram' => false, 'push' => false], $channels());

        $customer->forceFill(['telegram_id' => '777'])->save();
        $this->makeDevice($customer, \App\Enums\DeviceType::CUSTOMER_MOBILE, 'token-1234567890');
        $this->assertSame(['telegram' => true, 'push' => true], $channels());
    }

    public function test_the_response_never_contains_a_push_token(): void
    {
        $this->makeDevice($this->customer, \App\Enums\DeviceType::CUSTOMER_MOBILE, 'secret-token-xyz-1234');

        $this->assertStringNotContainsString('secret-token-xyz-1234',
            $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/me/notification-preferences')->getContent());
    }

    public function test_only_what_is_sent_changes_and_it_sticks(): void
    {
        $this->actingAs($this->customer, 'sanctum')->putJson('/api/v1/me/notification-preferences', [
            'preferences' => ['order_updates' => ['push' => false], 'promotions' => ['push' => true]],
        ])->assertOk()->assertJsonPath('message', 'Sozlamalar saqlandi.');

        $matrix = $this->matrix($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/me/notification-preferences'));
        $this->assertSame([
            'order_updates' => ['telegram' => true, 'push' => false],
            'promotions' => ['telegram' => false, 'push' => true],
        ], $matrix);

        // Switching back updates the same row
        $this->actingAs($this->customer, 'sanctum')->putJson('/api/v1/me/notification-preferences', [
            'preferences' => ['order_updates' => ['push' => true]],
        ])->assertOk();
        $this->assertSame(2, NotificationPreference::where('user_id', $this->customer->id)->count());
        $this->assertTrue(app(NotificationPreferenceService::class)->allows($this->customer, NotificationType::ORDER_UPDATES, NotificationChannel::PUSH));
    }

    public function test_unknown_types_channels_and_values_are_refused(): void
    {
        $put = fn (array $preferences, $user = null) => $this->actingAs($user ?? $this->customer, 'sanctum')
            ->putJson('/api/v1/me/notification-preferences', ['preferences' => $preferences]);

        $put(['nonsense' => ['push' => true]])->assertUnprocessable()->assertJsonValidationErrors('preferences.nonsense');
        $put(['order_updates' => ['carrier_pigeon' => true]])->assertUnprocessable();
        $put(['order_updates' => ['sms' => true]])->assertUnprocessable();
        $put(['order_updates' => ['push' => 'maybe']])->assertUnprocessable();
        $put(['order_updates' => []])->assertUnprocessable();
        // A customer has no "new orders" setting
        $put(['staff_new_order' => ['push' => true]])->assertUnprocessable();
        $this->actingAs($this->customer, 'sanctum')->putJson('/api/v1/me/notification-preferences', [])->assertUnprocessable();

        $this->assertSame(0, NotificationPreference::count());
    }

    public function test_one_persons_settings_never_touch_or_show_anothers(): void
    {
        $otherStore = $this->makeStore('Other Store');
        $outsider = $this->makeStaff(UserRole::MANAGER, null, $otherStore);

        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/me/notification-preferences', [
            'preferences' => ['staff_new_order' => ['push' => false, 'telegram' => false]],
        ])->assertOk();

        // Same store, same role, other store: all unchanged
        foreach ([$this->makeStaff(UserRole::MANAGER, $this->branch), $outsider, $this->owner] as $other) {
            $matrix = $this->matrix($this->actingAs($other, 'sanctum')->getJson('/api/v1/me/notification-preferences'));
            $this->assertSame(['telegram' => true, 'push' => true], $matrix['staff_new_order']);
        }

        $this->assertSame(2, NotificationPreference::count());
        $this->assertSame([$this->manager->id], NotificationPreference::pluck('user_id')->unique()->values()->all());
    }

    public function test_it_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/v1/me/notification-preferences')->assertUnauthorized();
        $this->putJson('/api/v1/me/notification-preferences', ['preferences' => ['order_updates' => ['push' => false]]])->assertUnauthorized();
    }

    public function test_deleting_a_user_removes_their_settings(): void
    {
        $customer = $this->makeCustomer();
        NotificationPreference::create(['user_id' => $customer->id, 'type' => 'promotions', 'channel' => 'push', 'enabled' => true]);

        $customer->delete();

        $this->assertSame(0, NotificationPreference::count());
    }
}
