<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-39 (found by the fast food end-to-end test): `printers.port` was an unsigned small integer, which PostgreSQL
     * stores as a signed `smallint` (up to 32767), while the API accepts any TCP port (1–65535): a LAN printer on a high
     * port (a print server or an emulator on 33235) answered 500. The column becomes an integer.
     */
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->unsignedInteger('port')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->unsignedSmallInteger('port')->nullable()->change();
        });
    }
};
