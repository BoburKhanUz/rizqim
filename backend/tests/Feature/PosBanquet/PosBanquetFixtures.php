<?php

namespace Tests\Feature\PosBanquet;

use App\Models\BanquetExtra;
use App\Models\BanquetHallRate;
use App\Models\BanquetPackage;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBooking\PosBookingFixtures;

/**
 * P6-51/P6-52 helpers: the booking fixtures (store, branches, a foreign store, a cashier, rooms in the branch's
 * "Ichki zal") plus two banquet halls with their evening rent, a package and two extras, the branch's `banquet`
 * module on, and "now" fixed at 14.11.2026 10:00 Tashkent.
 */
trait PosBanquetFixtures
{
    use PosBookingFixtures;

    protected int $hallA;
    protected int $hallB;
    protected int $roomsHall;
    protected int $otherBranchHall;
    protected BanquetPackage $package;
    protected BanquetExtra $cake;
    protected BanquetExtra $napkins;

    protected function setUpBanquets(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 05:00:00', 'UTC')); // 10:00 Tashkent (the clock in UTC as the app's)
        $this->setUpBookings();
        $this->store->forceFill(['timezone' => 'Asia/Tashkent'])->save();

        $this->roomsHall = (int) DB::table('halls')->where('branch_id', $this->branch->id)->value('id');
        $this->hallA = $this->makeHall($this->branch, 'Oq zal');
        $this->hallB = $this->makeHall($this->branch, "Ko'k zal");
        $this->otherBranchHall = $this->makeHall($this->otherBranch, 'Filial zali');
        foreach ([$this->hallA, $this->hallB] as $hall) {
            BanquetHallRate::create(['store_id' => $this->store->id, 'hall_id' => $hall, 'slot' => 'evening', 'price' => '5000000']);
            BanquetHallRate::create(['store_id' => $this->store->id, 'hall_id' => $hall, 'slot' => 'day', 'price' => '3000000']);
        }

        $this->package = BanquetPackage::create([
            'store_id' => $this->store->id, 'name' => 'Standart', 'per_person_price' => '150000', 'minimum_guests' => 200,
            'included' => ['Osh', 'Salatlar', 'Ichimliklar'],
        ]);
        $this->cake = BanquetExtra::create(['store_id' => $this->store->id, 'name' => 'Tort', 'price' => '800000', 'pricing' => 'per_event']);
        $this->napkins = BanquetExtra::create(['store_id' => $this->store->id, 'name' => 'Salfetka', 'price' => '5000', 'pricing' => 'per_person']);

        $this->banquetModule(true);
    }

    protected function makeHall(Branch $branch, string $name): int
    {
        return (int) DB::table('halls')->insertGetId([
            'store_id' => $branch->store_id, 'branch_id' => $branch->id, 'name' => $name, 'sort_order' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function banquetModule(?bool $on, ?Branch $branch = null): void
    {
        $branch ??= $this->branch;
        $settings = is_array($branch->fresh()->pos_settings) ? $branch->fresh()->pos_settings : [];
        $modules = (array) ($settings['modules'] ?? []);
        $modules['banquet'] = $on;
        $settings['modules'] = $modules;
        Branch::whereKey($branch->id)->update(['pos_settings' => json_encode($settings)]);
        app()->forgetScopedInstances();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function eventPayload(array $overrides = []): array
    {
        return $overrides + [
            'branch_id' => $this->branch->id,
            'event_date' => '2027-03-20',
            'slot' => 'evening',
            'hall_ids' => [$this->hallA],
            'event_type' => 'toy',
            'customer_name' => 'Karimovlar oilasi',
            'customer_phone' => '90 123 45 67',
            'guests_guaranteed' => 180,
            'banquet_package_id' => $this->package->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function hold(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->api($as ?? $this->manager, 'POST', '/api/v1/pos/banquets', $this->eventPayload($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function held(array $overrides = []): string
    {
        return (string) $this->hold($overrides)->assertCreated()->json('data.uuid');
    }
}
