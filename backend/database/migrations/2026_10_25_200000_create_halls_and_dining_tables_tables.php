<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: the branch's halls and zones (inside, yard, summer: `is_active` closes a seasonal zone) and their places:
 * tables, rooms (kabinka) and tapchans, with their spot on the floor map and an optional minimum spend (R1, R4;
 * docs/POS_SPEC.md §2.4, §10.2). Soft deleted, so the orders that sat there keep their link; a name is unique per
 * branch among the live rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'sort_order']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained('halls')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('kind', 16)->default('table'); // App\Enums\DiningTableKind
            $table->unsignedSmallInteger('seats')->nullable();
            // The floor map (any unit the panel draws in: grid cells or pixels)
            $table->decimal('layout_x', 8, 2)->nullable();
            $table->decimal('layout_y', 8, 2)->nullable();
            $table->decimal('layout_w', 8, 2)->nullable();
            $table->decimal('layout_h', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // "Obyazalovka": the least the party spends on food, per room or per guest (App\Enums\MinSpendMode)
            $table->decimal('min_spend', 12, 2)->nullable();
            $table->string('min_spend_mode', 16)->default('per_table');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'hall_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX halls_branch_name ON halls (branch_id, lower(name)) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX dining_tables_branch_name ON dining_tables (branch_id, lower(name)) WHERE deleted_at IS NULL');
            DB::statement('ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_min_spend_non_negative CHECK (min_spend IS NULL OR min_spend >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('halls');
    }
};
