<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: the room fee ("xona puli", R3). A tariff prices one place (`dining_table_id`) or every place of a kind
 * (`table_kind`, optionally only in one hall) for a day type (weekday, weekend, holiday), within optional dates.
 * room_tariff_calendars holds the branch's weekend days and holidays the day type is read from (until the branch POS
 * settings carry a holiday list). App\Domain\Pos\Orders\PosChargesCalculator applies them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->cascadeOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained('halls')->cascadeOnDelete();
            $table->string('table_kind', 16)->nullable(); // App\Enums\DiningTableKind
            $table->string('day_type', 16); // App\Enums\DayType
            $table->decimal('price', 12, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'day_type']);
        });

        Schema::create('room_tariff_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('weekend_days')->nullable(); // ISO weekdays (1 = Monday); null: [6, 7]
            $table->jsonb('holidays')->nullable(); // ["2026-03-21", ...]
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            // A tariff is for one place, or for a kind of place; never neither
            DB::statement('ALTER TABLE room_tariffs ADD CONSTRAINT room_tariffs_subject CHECK (dining_table_id IS NOT NULL OR table_kind IS NOT NULL)');
            DB::statement('ALTER TABLE room_tariffs ADD CONSTRAINT room_tariffs_price_non_negative CHECK (price >= 0)');
            DB::statement('ALTER TABLE room_tariffs ADD CONSTRAINT room_tariffs_dates CHECK (valid_from IS NULL OR valid_to IS NULL OR valid_from <= valid_to)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_tariff_calendars');
        Schema::dropIfExists('room_tariffs');
    }
};
