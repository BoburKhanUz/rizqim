<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review P6 wave 3 (C1): an edit in the combo constructor keeps the slots and options it did not change (their uuids
     * are what a till cached and sends back as picks) and soft-deletes the ones it removed, so an offline sale of a
     * removed option still finds it at sync. C10: a combo sold with no picks is a plain line (`combo_parts` null, as the
     * kitchen and the prep time report read it), never an empty list.
     */
    public function up(): void
    {
        Schema::table('combo_slots', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('combo_slot_options', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('order_items')->whereRaw("combo_parts = '[]'::jsonb")->update(['combo_parts' => null]);
    }

    public function down(): void
    {
        DB::table('combo_slot_options')->whereNotNull('deleted_at')->delete();
        DB::table('combo_slots')->whereNotNull('deleted_at')->delete();
        Schema::table('combo_slot_options', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('combo_slots', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
