<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P3-38: the booking confirmation the guest receives (GET/POST /pos/bookings/{uuid}/confirmation[/send]): the room fee
 * by the order's own rules (room tariff × day type, holidays from pos_settings), minimum spend, service charge, place
 * fee, pre-ordered dishes, deposits net of refunds, a room not assigned yet; sending over Telegram or SMS, by itself
 * on confirming, never again on a change; idempotency, permissions and tenant isolation.
 */
class PosBookingConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    private FakeSmsService $sms;

    private const PHONE = '+998901234567'; // the fixture booking's contact phone ("90 123 45 67")

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();

        $this->sms = new FakeSmsService();
        $this->app->instance(SmsServiceInterface::class, $this->sms);

        DB::table('branches')->where('id', $this->branch->id)->update(['address' => 'Chilonzor 9-kvartal', 'phone' => '+998712000000']);
        DB::table('dining_tables')->where('id', $this->roomA)->update(['seats' => 20]);
    }

    private function confirmation(string $uuid, ?User $as = null): TestResponse
    {
        return $this->api($as ?? $this->cashier, 'GET', "/api/v1/pos/bookings/{$uuid}/confirmation");
    }

    private function send(string $uuid, ?string $channel = null, ?string $key = null, ?User $as = null): TestResponse
    {
        return $this->api($as ?? $this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/confirmation/send", $channel !== null ? ['channel' => $channel] : [], [
            'Idempotency-Key' => $key ?? 'send-' . bin2hex(random_bytes(4)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function posSettings(array $settings): void
    {
        DB::table('branches')->where('id', $this->branch->id)->update(['pos_settings' => json_encode($settings)]);
    }

    private function tariff(string $dayType, string $price, ?int $tableId = null): int
    {
        return (int) DB::table('room_tariffs')->insertGetId([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'dining_table_id' => $tableId ?? $this->roomA,
            'day_type' => $dayType, 'price' => $price, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function telegramCustomer(string $telegramId = '5551', ?int $storeId = null, string $phone = self::PHONE): User
    {
        return $this->makeCustomer(0, ['role' => UserRole::CUSTOMER->value, 'phone' => $phone, 'telegram_id' => $telegramId, 'store_id' => $storeId ?? $this->store->id]);
    }

    /**
     * @return array<string, string> kind => "label: value" (or the value)
     */
    private function lines(TestResponse $response): array
    {
        $lines = [];
        foreach ($response->json('data.lines') as $line) {
            $lines[$line['kind']] = $line['label'] !== '' ? $line['label'] . ': ' . $line['value'] : $line['value'];
        }

        return $lines;
    }

    public function test_the_whole_text_with_the_weekday_room_fee(): void
    {
        $weekday = $this->tariff('weekday', '200000');
        $this->tariff('weekend', '300000');
        $uuid = $this->booked(['confirm' => true]);
        $code = strtoupper(substr(str_replace('-', '', $uuid), -6));

        $response = $this->confirmation($uuid)->assertOk()
            ->assertJsonPath('data.room_fee', ['amount' => '200000.00', 'day_type' => 'weekday', 'tariff_id' => $weekday])
            ->assertJsonPath('data.channels', ['telegram' => false, 'sms' => false]);

        $this->assertSame(implode("\n", [
            'Bron tasdiqlandi ✅',
            'Lifecycle Burger, Chilonzor 9-kvartal',
            '📅 Payshanba, 5-noyabr, 19:00–23:30',
            '🚪 Xona: "Kabinka 1" (20 kishilik) · 12 kishi',
            "💵 Xona puli: 200 000 so'm (hafta ichi narxi)",
            "📞 +998712000000 · Bron raqami: {$code}",
        ]), $response->json('data.text'));
        $this->assertSame(['header', 'place', 'time', 'room', 'room_fee', 'contact'], array_column($response->json('data.lines'), 'kind'));
        $this->assertSame(['kind' => 'room_fee', 'label' => 'Xona puli', 'value' => "200 000 so'm (hafta ichi narxi)"], $response->json('data.lines.4'));
    }

    public function test_the_room_fee_is_the_weekend_and_the_holiday_price_of_that_date(): void
    {
        $this->tariff('weekday', '200000');
        $weekend = $this->tariff('weekend', '300000');
        $holiday = $this->tariff('holiday', '450000');

        // Saturday
        $saturday = $this->booked(['starts_at' => '2026-11-07T19:00:00+05:00', 'ends_at' => '2026-11-07T23:00:00+05:00']);
        $response = $this->confirmation($saturday)->assertOk()
            ->assertJsonPath('data.room_fee', ['amount' => '300000.00', 'day_type' => 'weekend', 'tariff_id' => $weekend]);
        $this->assertSame("Xona puli: 300 000 so'm (dam olish kuni narxi)", $this->lines($response)['room_fee']);
        // A requested booking is not called confirmed yet
        $this->assertStringStartsWith("Bron qabul qilindi 📝\n", $response->json('data.text'));

        // A Thursday in the branch's holiday list (pos_settings.holidays): the holiday price
        $this->posSettings(['holidays' => ['2026-11-12']]);
        $thursday = $this->booked(['starts_at' => '2026-11-12T19:00:00+05:00', 'ends_at' => '2026-11-12T23:00:00+05:00']);
        $response = $this->confirmation($thursday)->assertOk()
            ->assertJsonPath('data.room_fee', ['amount' => '450000.00', 'day_type' => 'holiday', 'tariff_id' => $holiday]);
        $this->assertSame("Xona puli: 450 000 so'm (bayram kuni narxi)", $this->lines($response)['room_fee']);

        // After midnight is still the evening's business date (day start 05:00): Saturday 01:00 is Friday's weekday price
        $night = $this->booked(['dining_table_id' => $this->roomA, 'starts_at' => '2026-11-14T01:00:00+05:00', 'ends_at' => '2026-11-14T04:00:00+05:00']);
        $this->confirmation($night)->assertOk()->assertJsonPath('data.room_fee.day_type', 'weekday');
    }

    public function test_the_minimum_spend_of_the_room_or_of_the_booking(): void
    {
        DB::table('dining_tables')->where('id', $this->roomA)->update(['min_spend' => '100000', 'min_spend_mode' => 'per_person']);
        $uuid = $this->booked();
        $this->assertSame("Minimal buyurtma: 100 000 so'm kishi boshiga (jami 1 200 000 so'm)", $this->lines($this->confirmation($uuid))['min_spend']);

        DB::table('dining_tables')->where('id', $this->roomA)->update(['min_spend' => '900000', 'min_spend_mode' => 'per_table']);
        $this->assertSame("Minimal buyurtma: 900 000 so'm", $this->lines($this->confirmation($uuid))['min_spend']);

        // Agreed with the guest on the booking: that one
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['min_spend' => '1500000'])->assertOk();
        $lines = $this->lines($this->confirmation($uuid)->assertOk()->assertJsonPath('data.room_fee', null));
        $this->assertSame("Minimal buyurtma: 1 500 000 so'm", $lines['min_spend']);
        $this->assertArrayNotHasKey('room_fee', $lines);
    }

    public function test_the_service_charge_and_the_place_fee_of_the_rooms_hall(): void
    {
        DB::table('service_fee_settings')->insert([
            'store_id' => $this->store->id, 'branch_id' => null, 'service_charge_percent' => '10.00', 'place_fee_amount' => '5000.00',
            'place_fee_mode' => 'per_person', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $uuid = $this->booked();
        $lines = $this->lines($this->confirmation($uuid));
        $this->assertSame('Xizmat haqi: 10%', $lines['service_charge']);
        $this->assertSame("Joy narxi: 5 000 so'm kishi boshiga (jami 60 000 so'm)", $lines['place_fee']);

        // The room's hall has its own: 12.5 %, a place fee per table
        $hall = (int) DB::table('dining_tables')->where('id', $this->roomA)->value('hall_id');
        DB::table('service_fee_settings')->insert([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $hall, 'service_charge_percent' => '12.50',
            'place_fee_amount' => '50000.00', 'place_fee_mode' => 'per_table', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lines = $this->lines($this->confirmation($uuid));
        $this->assertSame('Xizmat haqi: 12.5%', $lines['service_charge']);
        $this->assertSame("Joy narxi: 50 000 so'm", $lines['place_fee']);

        // Not charged in the hall channel: not announced
        DB::table('service_fee_settings')->where('hall_id', $hall)->update(['service_charge_channels' => json_encode(['pos_counter']), 'place_fee_channels' => json_encode(['pos_counter'])]);
        $lines = $this->lines($this->confirmation($uuid));
        $this->assertArrayNotHasKey('service_charge', $lines);
        $this->assertArrayNotHasKey('place_fee', $lines);
    }

    public function test_the_service_charge_rules_decide_what_is_announced(): void
    {
        // P6-22 (J2 open item): hall 15%, 8 guests or more 20%, a cover of 10 000 per guest — the order's own rules
        DB::table('service_fee_settings')->insert([
            'store_id' => $this->store->id, 'branch_id' => null, 'service_charge_percent' => '10.00', 'place_fee_amount' => '5000.00',
            'place_fee_mode' => 'per_person', 'created_at' => now(), 'updated_at' => now(),
            'service_charge_rules' => json_encode(['by_order_type' => ['hall' => '15'], 'large_group_min_guests' => 8, 'large_group_percent' => '20', 'cover_amount' => '10000']),
        ]);
        $uuid = $this->booked(); // 12 guests
        $profile = function (FormatProfile $profile, array $modules = []) {
            $branch = $this->branch->fresh();
            $branch->forceFill(['pos_settings' => PosSettings::withFormat($branch->pos_settings, $profile, $modules)])->save();
        };
        $profile(FormatProfile::RESTORAN);

        $response = $this->confirmation($uuid)->assertOk();
        $lines = $this->lines($response);
        $this->assertSame("Xizmat haqi: 20% (8 va undan ko'p mehmon)", $lines['service_charge']);
        $this->assertSame("Kuver: 10 000 so'm kishi boshiga (jami 120 000 so'm)", $lines['place_fee']);
        $this->assertStringContainsString("💵 Xizmat haqi: 20% (8 va undan ko'p mehmon)", $response->json('data.text'));

        // A smaller party: the hall percent
        Booking::where('uuid', $uuid)->update(['guests_planned' => 4]);
        $lines = $this->lines($this->confirmation($uuid));
        $this->assertSame('Xizmat haqi: 15%', $lines['service_charge']);
        $this->assertSame("Kuver: 10 000 so'm kishi boshiga (jami 40 000 so'm)", $lines['place_fee']);

        // The module off: the row's own percent and place fee, as the order would charge
        $profile(FormatProfile::RESTORAN, ['service_charge_rules' => false]);
        $lines = $this->lines($this->confirmation($uuid));
        $this->assertSame('Xizmat haqi: 10%', $lines['service_charge']);
        $this->assertSame("Joy narxi: 5 000 so'm kishi boshiga (jami 20 000 so'm)", $lines['place_fee']);

        // A stolovaya never charges a service charge (VM 75), whatever the rules
        $profile(FormatProfile::STOLOVAYA, ['service_charge_rules' => true]);
        $lines = $this->lines($this->confirmation($uuid));
        $this->assertArrayNotHasKey('service_charge', $lines);
    }

    public function test_pre_orders_deposits_net_of_refunds_and_the_cancel_policy(): void
    {
        $uuid = $this->booked();
        $booking = Booking::where('uuid', $uuid)->firstOrFail();
        BookingItem::create(['booking_id' => $booking->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'Osh', 'variant_name' => "To'y oshi", 'unit' => 'kg', 'quantity' => '3', 'unit_price' => '120000', 'due_at' => '2026-11-05 14:00:00']);
        BookingItem::create(['booking_id' => $booking->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'Qozon kabob', 'unit' => 'kg', 'quantity' => '2.5', 'unit_price' => '150000', 'due_at' => '2026-11-05 14:30:00']);
        BookingItem::create(['booking_id' => $booking->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'Somsa', 'unit' => 'piece', 'quantity' => '10', 'unit_price' => '8000', 'due_at' => '2026-11-05 14:30:00']);

        $this->takeDeposit($uuid, '500000', 'cash')->assertCreated();
        $second = $this->takeDeposit($uuid, '300000', 'cash')->assertCreated()->json('data.payment.uuid');
        BookingPayment::where('uuid', $second)->update(['status' => 'refunded', 'refunded_amount' => '300000.00']);

        $this->posSettings(['booking_confirmation' => ['cancel_policy' => "Bekor qilish: 24 soat oldin ayting, bo'nak qaytariladi."]]);

        $response = $this->confirmation($uuid)->assertOk();
        $lines = $this->lines($response);
        $this->assertSame("Oldindan buyurtma: Osh (To'y oshi) 3 kg, Qozon kabob 2.5 kg, Somsa 10 dona", $lines['pre_order']);
        $this->assertSame("Bo'nak olindi: 500 000 so'm", $lines['deposit']);
        $this->assertSame("Bekor qilish: 24 soat oldin ayting, bo'nak qaytariladi.", $lines['cancel_policy']);
        $this->assertStringContainsString("\n🍽 Oldindan buyurtma: Osh (To'y oshi) 3 kg", $response->json('data.text'));
        $this->assertStringContainsString("\n💳 Bo'nak olindi: 500 000 so'm\nℹ️ Bekor qilish: 24 soat", $response->json('data.text'));

        // Everything given back: no deposit line
        BookingPayment::where('booking_id', $booking->id)->update(['status' => 'refunded', 'refunded_amount' => DB::raw('amount')]);
        $this->assertArrayNotHasKey('deposit', $this->lines($this->confirmation($uuid)));
    }

    public function test_a_booking_without_a_room_says_later_and_has_no_room_fee(): void
    {
        $this->tariff('weekday', '200000');
        DB::table('dining_tables')->where('id', $this->roomA)->update(['min_spend' => '900000']);
        $uuid = $this->booked(['dining_table_id' => null]);

        $response = $this->confirmation($uuid)->assertOk()->assertJsonPath('data.room_fee', null);
        $lines = $this->lines($response);
        $this->assertSame('Xona: keyin belgilanadi · 12 kishi', $lines['room']);
        $this->assertArrayNotHasKey('room_fee', $lines);
        $this->assertArrayNotHasKey('min_spend', $lines);
        $this->assertStringContainsString("\n🚪 Xona: keyin belgilanadi · 12 kishi\n", $response->json('data.text'));
    }

    public function test_sent_over_telegram_to_the_stores_customer_with_that_phone(): void
    {
        $uuid = $this->booked(['confirm' => true]);
        // Someone else's number, another store's customer with this number, one who switched Telegram off: never used
        $this->telegramCustomer('6001', null, '+998931112233');
        $this->telegramCustomer('6002', (int) $this->foreignStore->id);
        $this->confirmation($uuid)->assertOk()->assertJsonPath('data.channels.telegram', false);
        $optedOut = $this->telegramCustomer('6003');
        NotificationPreference::create(['user_id' => $optedOut->id, 'type' => 'order_updates', 'channel' => 'telegram', 'enabled' => false]);
        $this->confirmation($uuid)->assertOk()->assertJsonPath('data.channels.telegram', false);

        $this->telegramCustomer('5551');
        $text = $this->confirmation($uuid)->assertOk()->assertJsonPath('data.channels', ['telegram' => true, 'sms' => false])->json('data.text');

        $this->send($uuid)->assertOk()->assertJsonPath('data', ['channel' => 'telegram', 'queued' => true]);

        $this->assertSame([['chat' => '5551', 'text' => $text]], $this->telegram->sent);
        $this->assertSame([], $this->sms->sent);
        $booking = $this->api($this->cashier, 'GET', "/api/v1/pos/bookings/{$uuid}")->assertOk()
            ->assertJsonPath('data.confirmation_channel', 'telegram')
            ->assertJsonPath('data.version', 1); // sending is no change of the booking
        $this->assertNotNull($booking->json('data.confirmation_sent_at'));

        $log = AuditLog::where('action', 'booking.confirmation_sent')->sole();
        $this->assertSame('telegram', $log->payload_json['channel']);
        $this->assertFalse($log->payload_json['auto']);
        $this->assertSame((int) $this->cashier->id, (int) $log->user_id);

        // Asked for SMS while the branch has it off
        $this->send($uuid, 'sms')->assertStatus(422)->assertJsonPath('data.reason', 'no_channel');
    }

    public function test_sms_only_when_the_branch_switched_it_on(): void
    {
        $uuid = $this->booked(['confirm' => true]);

        $this->confirmation($uuid)->assertOk()->assertJsonPath('data.channels', ['telegram' => false, 'sms' => false]);
        $this->send($uuid)->assertStatus(422)->assertJsonPath('data.reason', 'no_channel');
        $this->send($uuid, 'sms')->assertStatus(422)->assertJsonPath('data.reason', 'no_channel');
        $this->send($uuid, 'telegram')->assertStatus(422)->assertJsonPath('data.reason', 'no_channel');
        $this->assertSame([], $this->sms->sent);
        $this->assertNull(Booking::where('uuid', $uuid)->value('confirmation_sent_at'));

        $this->api($this->owner, 'PUT', '/api/v1/owner/branches/' . $this->branch->id . '/pos-settings', ['pos_settings' => ['booking_confirmation' => ['sms' => true]]])
            ->assertOk()->assertJsonPath('data.pos_settings.booking_confirmation', ['auto_send' => true, 'sms' => true, 'cancel_policy' => null]);

        $this->confirmation($uuid)->assertOk()->assertJsonPath('data.channels', ['telegram' => false, 'sms' => true]);
        $this->send($uuid)->assertOk()->assertJsonPath('data', ['channel' => 'sms', 'queued' => true]);

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame(self::PHONE, $this->sms->sent[0]['phone']);
        // The same words without the icons (cheaper SMS)
        $this->assertStringStartsWith("Bron tasdiqlandi\nLifecycle Burger, Chilonzor 9-kvartal\nPayshanba, 5-noyabr, 19:00–23:30\nXona: \"Kabinka 1\"", $this->sms->sent[0]['text']);
        $this->assertStringNotContainsString('✅', $this->sms->sent[0]['text']);
        $this->assertSame('sms', Booking::where('uuid', $uuid)->value('confirmation_channel'));
        $this->assertSame([], $this->telegram->sent);

        // A guest with Telegram gets it there even with SMS on; asking for SMS still texts
        $this->telegramCustomer();
        $this->send($uuid)->assertOk()->assertJsonPath('data.channel', 'telegram');
        $this->send($uuid, 'sms')->assertOk()->assertJsonPath('data.channel', 'sms');
        $this->assertCount(2, $this->sms->sent);
    }

    public function test_sent_by_itself_when_confirmed_and_never_again_on_a_change(): void
    {
        $this->telegramCustomer();

        // Requested: nothing yet
        $uuid = $this->booked();
        $this->assertSame([], $this->telegram->sent);

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/confirm", [], ['Idempotency-Key' => 'c-1'])->assertOk()
            ->assertJsonPath('data.confirmation_channel', 'telegram');
        $this->assertCount(1, $this->telegram->sent);
        $this->assertStringStartsWith("Bron tasdiqlandi ✅\n", $this->telegram->sent[0]['text']);
        $this->assertTrue(AuditLog::where('action', 'booking.confirmation_sent')->sole()->payload_json['auto']);

        // Moved, another room, more guests: the cashier resends by hand
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['starts_at' => '2026-11-05T20:00:00+05:00', 'dining_table_id' => $this->roomB, 'guests_planned' => 15])->assertOk();
        $this->assertCount(1, $this->telegram->sent);

        // Created confirmed, or confirmed by its first deposit: sent too
        $this->booked(['dining_table_id' => $this->roomC, 'confirm' => true]);
        $this->assertCount(2, $this->telegram->sent);
        $byDeposit = $this->booked(['starts_at' => '2026-11-06T19:00:00+05:00', 'ends_at' => '2026-11-06T23:00:00+05:00']);
        $this->takeDeposit($byDeposit, '500000')->assertCreated()->assertJsonPath('data.booking.status', 'confirmed');
        $this->assertCount(3, $this->telegram->sent);
        $this->assertStringContainsString("💳 Bo'nak olindi: 500 000 so'm", $this->telegram->sent[2]['text']);
        // A second deposit on a confirmed booking sends nothing
        $this->takeDeposit($byDeposit, '100000')->assertCreated();
        $this->assertCount(3, $this->telegram->sent);
    }

    public function test_auto_send_switched_off_or_without_a_channel_never_blocks_the_booking(): void
    {
        $this->posSettings(['booking_confirmation' => ['auto_send' => false]]);
        $this->telegramCustomer();
        $uuid = $this->booked();
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/confirm")->assertOk()->assertJsonPath('data.confirmation_sent_at', null);
        $this->assertSame([], $this->telegram->sent);

        // On, but no Telegram and no SMS: confirmed all the same, nothing sent
        $this->posSettings([]);
        $other = $this->booked(['dining_table_id' => $this->roomB, 'contact_phone' => '93 111 22 33', 'confirm' => true]);
        $this->api($this->cashier, 'GET', "/api/v1/pos/bookings/{$other}")->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmation_sent_at', null)
            ->assertJsonPath('data.confirmation_channel', null);
        $this->assertSame([], $this->telegram->sent);
        $this->assertSame(0, AuditLog::where('action', 'booking.confirmation_sent')->count());
    }

    public function test_one_send_per_idempotency_key_and_only_for_a_live_booking(): void
    {
        $this->telegramCustomer();
        $uuid = $this->booked();

        $this->send($uuid, null, 'k-1')->assertOk();
        $this->send($uuid, null, 'k-1')->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.channel', 'telegram');
        $this->assertCount(1, $this->telegram->sent);
        $this->send($uuid, null, 'k-2')->assertOk();
        $this->assertCount(2, $this->telegram->sent);
        $this->assertSame(2, AuditLog::where('action', 'booking.confirmation_sent')->count());

        $this->send($uuid, 'fax')->assertStatus(422)->assertJsonValidationErrors('channel');

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/cancel", ['reason' => 'Mehmon voz kechdi'])->assertOk();
        $this->send($uuid)->assertStatus(422)->assertJsonPath('data.reason', 'booking_not_editable');
        $this->assertStringStartsWith('Bron bekor qilindi ❌', $this->confirmation($uuid)->assertOk()->json('data.text'));
        $this->assertCount(2, $this->telegram->sent);
    }

    public function test_who_may_read_and_send_it_and_where(): void
    {
        $uuid = $this->booked();
        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $otherCashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);

        foreach ([$waiter, $this->customer] as $user) {
            $this->confirmation($uuid, $user)->assertForbidden();
            $this->send($uuid, null, null, $user)->assertForbidden();
        }
        foreach ([$this->foreignOwner, $otherCashier] as $user) {
            $this->confirmation($uuid, $user)->assertNotFound();
            $this->send($uuid, null, null, $user)->assertNotFound();
        }
        $this->confirmation($uuid, $this->owner)->assertOk();
        $this->confirmation($uuid, $this->manager)->assertOk();

        // The till's PIN session
        $session = $this->terminalSession($this->cashier, $this->branch);
        $this->apiToken($session['token'], 'GET', "/api/v1/pos/bookings/{$uuid}/confirmation")->assertOk()->assertJsonPath('data.lines.0.kind', 'header');
        $this->telegramCustomer();
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/confirmation/send", ['channel' => 'telegram'], ['Idempotency-Key' => 'pin-1'])
            ->assertOk()->assertJsonPath('data.channel', 'telegram');
    }

    public function test_the_branch_settings_block_is_validated_and_reaches_the_till(): void
    {
        $url = '/api/v1/owner/branches/' . $this->branch->id . '/pos-settings';
        $this->api($this->owner, 'GET', $url)->assertOk()
            ->assertJsonPath('data.pos_settings.booking_confirmation', ['auto_send' => true, 'sms' => false, 'cancel_policy' => null]);

        $this->api($this->owner, 'PUT', $url, ['pos_settings' => ['booking_confirmation' => ['auto_send' => false, 'cancel_policy' => '  24 soat oldin ayting.  ']]])->assertOk()
            ->assertJsonPath('data.pos_settings.booking_confirmation', ['auto_send' => false, 'sms' => false, 'cancel_policy' => '24 soat oldin ayting.']);
        // Key by key: the rest stays
        $this->api($this->owner, 'PUT', $url, ['pos_settings' => ['booking_confirmation' => ['sms' => true]]])->assertOk()
            ->assertJsonPath('data.pos_settings.booking_confirmation', ['auto_send' => false, 'sms' => true, 'cancel_policy' => '24 soat oldin ayting.']);
        $this->api($this->owner, 'PUT', $url, ['pos_settings' => ['booking_confirmation' => ['cancel_policy' => null]]])->assertOk()
            ->assertJsonPath('data.pos_settings.booking_confirmation.cancel_policy', null);

        $this->api($this->owner, 'PUT', $url, ['pos_settings' => ['booking_confirmation' => ['cancel_policy' => str_repeat('a', 301)]]])
            ->assertStatus(422)->assertJsonValidationErrors('pos_settings.booking_confirmation.cancel_policy');
        $this->api($this->owner, 'PUT', $url, ['pos_settings' => ['booking_confirmation' => ['sms' => 'ha']]])
            ->assertStatus(422)->assertJsonValidationErrors('pos_settings.booking_confirmation.sms');
        $this->api($this->owner, 'PUT', $url, ['pos_settings' => ['booking_confirmation' => ['whatsapp' => true]]])
            ->assertStatus(422)->assertJsonValidationErrors('pos_settings.booking_confirmation.whatsapp');

        $this->api($this->manager, 'GET', '/api/v1/pos/bootstrap')->assertOk()
            ->assertJsonPath('data.branch.pos_settings.booking_confirmation', ['auto_send' => false, 'sms' => true, 'cancel_policy' => null]);
    }
}
