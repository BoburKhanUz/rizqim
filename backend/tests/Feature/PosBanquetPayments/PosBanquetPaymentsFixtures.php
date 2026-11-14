<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Banquet\Payments\BanquetSchedule;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\Branch;
use App\Models\User;
use App\Services\Notifications\NotificationRouter;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;

/**
 * P6-53 helpers: the billing fixtures (a till with its cashier PIN session, the manager with PIN 5926) on the real
 * fiscal service (sandbox driver) so the advance, refund and sale receipts are the real ones; the branch's `banquet`
 * module on; "now" fixed at 14.11.2026 10:00 Tashkent; owner alerts captured; banquet events made directly (their
 * schedule planned as the BanquetEventChanged listener does).
 */
trait PosBanquetPaymentsFixtures
{
    use PosBillingFixtures;

    /** @var PosOwnerAlerts&object{sent: list<string>} */
    protected PosOwnerAlerts $alerts;

    protected string $managerToken;

    /** @var array{device: \App\Models\Device, token: string} */
    protected array $managerTill;

    protected function setUpBanquetPayments(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tashkent'));
        $this->setUpPosBilling();
        $this->app->forgetInstance(FiscalIssuer::class);
        $this->app->forgetInstance(FiscalLineBuilder::class);
        $this->store->forceFill([
            'timezone' => 'Asia/Tashkent',
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'charges' => [
                'deposit' => ['mxik' => '10305001001000000'],
                'booking_fee' => ['mxik' => '10305001002000000'],
            ]],
        ])->save();
        $settings = is_array($this->branch->fresh()->pos_settings) ? $this->branch->fresh()->pos_settings : [];
        Branch::whereKey($this->branch->id)->update(['pos_settings' => json_encode(['modules' => ['banquet' => true]] + $settings)]);
        app()->forgetScopedInstances();

        $this->alerts = new class(app(TelegramMessenger::class), app(NotificationRouter::class)) extends PosOwnerAlerts
        {
            /** @var list<string> */
            public array $sent = [];

            public function send(int $storeId, string $text): void
            {
                $this->sent[] = $text;
            }
        };
        $this->app->instance(PosOwnerAlerts::class, $this->alerts);
        // The manager's own PIN session on a second till (one PIN session per terminal)
        $this->managerTill = $this->pairTerminal($this->owner, $this->branch);
        $this->managerToken = $this->pinSession($this->managerTill, $this->manager, self::MANAGER_PIN);
    }

    /**
     * A confirmed-able event (hold) of $total so'm on $date, its schedule planned from the store's template.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function event(string $total = '30000000', string $date = '2027-01-20', array $attributes = [], ?Branch $branch = null): BanquetEvent
    {
        $branch ??= $this->branch;
        $starts = Carbon::parse("{$date} 18:00", 'Asia/Tashkent')->utc();
        $event = BanquetEvent::create($attributes + [
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'event_date' => $date,
            'slot' => 'evening',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(5),
            'event_type' => 'toy',
            'status' => 'hold',
            'customer_name' => 'Karimovlar oilasi',
            'customer_phone' => '+998901234567',
            'guests_guaranteed' => 200,
            'guests_minimum' => 200,
            'total' => $total,
            'hold_expires_at' => now()->addDays(3),
        ]);
        app(BanquetSchedule::class)->sync($event);

        return $event->refresh();
    }

    /**
     * @return \Illuminate\Support\Collection<int, BanquetPayment>
     */
    protected function rows(BanquetEvent $event): \Illuminate\Support\Collection
    {
        return BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function payRow(BanquetEvent $event, BanquetPayment $row, array $data = ['tender' => 'cash'], ?string $token = null, ?string $key = null): TestResponse
    {
        return $this->pos('POST', "/banquets/{$event->uuid}/payments/{$row->uuid}/pay", $data, $token, $key);
    }

    /**
     * A request of the web panel (a normal sign-in, no terminal) as $user.
     *
     * @param  array<string, mixed>  $data
     */
    protected function panel(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())->json($method, '/api/v1' . $uri, $data);
    }
}
