<?php

namespace Tests\Feature\PosBanquetOps;

use App\Domain\Pos\Banquet\BanquetPricing;
use App\Domain\Pos\Banquet\Payments\BanquetSchedule;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\BanquetEvent;
use App\Models\BanquetExtra;
use App\Models\BanquetHallRate;
use App\Models\BanquetOpsSetting;
use App\Models\BanquetPackage;
use App\Models\BanquetPackageItem;
use App\Models\Branch;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\PosBanquetPayments\PosBanquetPaymentsFixtures;

/**
 * P6-54 helpers on P6-53's (the till, the cashier and the manager's PIN sessions, the real fiscal service on the
 * sandbox driver, the `banquet` module on, "now" 14.11.2026 10:00 Tashkent) with the real advance ledger: two halls
 * with their evening rent, a package of 150 000 so'm per person with menu items (osh 1 portion, salad 0.2 kg, bread 1
 * piece, the osh routed to the "Osh sexi" station), two extras (a cake per event, napkins per person) and the final
 * sale's MXIK codes.
 */
trait PosBanquetOpsFixtures
{
    use PosBanquetPaymentsFixtures;

    protected int $hallA;

    protected int $hallB;

    protected BanquetPackage $package;

    protected BanquetExtra $cake;

    protected BanquetExtra $napkins;

    protected Product $osh;

    protected Product $salad;

    protected ProductVariant $drink;

    protected int $oshStation;

    protected function setUpBanquetOps(): void
    {
        $this->setUpBanquetPayments();
        $this->app->forgetInstance(AdvanceLedger::class);

        $this->product->forceFill(['mxik_code' => '10202001001000001'])->save();
        $this->drink = $this->variant;
        $this->hallA = $this->makeHall($this->branch, 'Oq zal');
        $this->hallB = $this->makeHall($this->branch, "Ko'k zal");
        foreach ([$this->hallA, $this->hallB] as $hall) {
            BanquetHallRate::create(['store_id' => $this->store->id, 'hall_id' => $hall, 'slot' => 'evening', 'price' => '5000000']);
        }

        $category = (int) $this->product->category_id;
        $this->osh = Product::create(['store_id' => $this->store->id, 'category_id' => $category, 'name' => "To'y oshi", 'slug' => 'osh-' . Str::random(5), 'status' => 'active']);
        $this->salad = Product::create(['store_id' => $this->store->id, 'category_id' => $category, 'name' => 'Achchiq-chuchuk', 'slug' => 'salad-' . Str::random(5), 'status' => 'active']);
        $this->oshStation = (int) KitchenStation::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Osh sexi'])->id;
        KitchenStationRoute::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'station_id' => $this->oshStation, 'product_id' => $this->osh->id]);

        $this->package = BanquetPackage::create([
            'store_id' => $this->store->id, 'name' => 'Standart', 'per_person_price' => '150000', 'minimum_guests' => 150,
            'included' => ['Osh', 'Salatlar'],
        ]);
        foreach ([[$this->osh->id, "To'y oshi", '1', 'portion'], [$this->salad->id, 'Achchiq-chuchuk', '0.2', 'kg'], [null, 'Non', '1', 'piece']] as $i => [$product, $name, $qty, $unit]) {
            BanquetPackageItem::create(['banquet_package_id' => $this->package->id, 'product_id' => $product, 'name' => $name, 'quantity_per_person' => $qty, 'unit' => $unit, 'sort_order' => $i]);
        }
        $this->cake = BanquetExtra::create(['store_id' => $this->store->id, 'name' => 'Tort', 'price' => '800000', 'pricing' => 'per_event']);
        $this->napkins = BanquetExtra::create(['store_id' => $this->store->id, 'name' => 'Salfetka', 'price' => '5000', 'pricing' => 'per_person']);

        BanquetOpsSetting::create([
            'store_id' => $this->store->id,
            'package_mxik' => '10305001003000000',
            'hall_mxik' => '10305001004000000',
            'extra_mxik' => '10305001005000000',
            'osh' => ['persons_per_kg' => '5', 'product_ids' => [$this->osh->id], 'ingredients' => [
                ['key' => 'rice', 'name' => 'Guruch', 'unit' => 'kg', 'per_kg' => '1'],
                ['key' => 'meat', 'name' => "Go'sht", 'unit' => 'kg', 'per_kg' => '1'],
                ['key' => 'oil', 'name' => "Yog'", 'unit' => 'l', 'per_kg' => '0.25'],
            ], 'kazans' => [10, 20, 50]],
        ]);
    }

    protected function makeHall(Branch $branch, string $name): int
    {
        return (int) DB::table('halls')->insertGetId([
            'store_id' => $branch->store_id, 'branch_id' => $branch->id, 'name' => $name, 'sort_order' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * An event priced by P6-52's rules (hall A, the package, a cake and napkins per person), its schedule planned.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function pricedEvent(int $guaranteed = 200, int $minimum = 150, string $date = '2027-01-20', array $attributes = [], ?Branch $branch = null, bool $extras = true): BanquetEvent
    {
        $branch ??= $this->branch;
        // "Now" is a Tashkent instant (P6-53's fixtures): Carbon reads the stored times in that zone, so they are written in it
        $starts = Carbon::parse("{$date} 18:00", 'Asia/Tashkent');
        $event = BanquetEvent::create($attributes + [
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'event_date' => $date,
            'slot' => 'evening',
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(5),
            'event_type' => 'toy',
            'status' => 'hold',
            'title' => "Aziz va Malika to'yi",
            'customer_name' => 'Karimovlar oilasi',
            'customer_phone' => '+998901234567',
            'guests_guaranteed' => $guaranteed,
            'guests_minimum' => $minimum,
            'banquet_package_id' => $this->package->id,
            'hold_expires_at' => now()->addDays(3),
        ]);
        $event->halls()->attach($branch->id === $this->branch->id ? [$this->hallA] : [$this->makeHall($branch, 'Filial zali')]);
        app(BanquetPricing::class)->apply($event, $extras ? [['extra_id' => $this->cake->id, 'quantity' => '1'], ['extra_id' => $this->napkins->id, 'quantity' => '1']] : []);
        $event->save();
        app(BanquetPricing::class)->freeze($event);
        $event->forceFill(['frozen_at' => now()])->save();
        app(BanquetSchedule::class)->sync($event->refresh());

        return $event->refresh();
    }

    /**
     * A drink line on the event's order (the event day), 3 × 20 000.
     */
    protected function eventDayLine(BanquetEvent $event, string $quantity = '3', string $total = '60000'): OrderItem
    {
        return OrderItem::create([
            'order_id' => $event->refresh()->order_id,
            'product_variant_id' => $this->drink->id,
            'product_name' => 'Cola',
            'quantity' => $quantity,
            'unit' => 'piece',
            'unit_price' => '20000',
            'total_price' => $total,
        ]);
    }
}
