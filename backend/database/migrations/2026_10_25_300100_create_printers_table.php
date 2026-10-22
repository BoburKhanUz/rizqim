<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-13: the branch's receipt, kitchen and label printers (POS_SPEC 2.11, 2.12). The terminal prints, not the
     * server, so this is the configuration the terminals read from GET /pos/bootstrap: a LAN printer is reached by
     * address and port from any terminal; a USB or built-in (Sunmi) printer belongs to one terminal (`device_id`),
     * so the setting survives a terminal being replaced. A kitchen printer may be tied to a station.
     */
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('purpose', 16)->default('receipt'); // App\Enums\PrinterPurpose
            $table->string('connection', 16); // App\Enums\PrinterConnection
            $table->string('address', 255)->nullable(); // host/IP of a LAN printer, MAC of a Bluetooth one
            $table->unsignedSmallInteger('port')->nullable(); // LAN, usually 9100
            $table->unsignedSmallInteger('paper_width')->default(80); // mm: 58 or 80
            $table->foreignId('station_id')->nullable()->constrained('kitchen_stations')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('encoding', 32)->nullable(); // code page when the printer's fonts need one (e.g. cp866); null: UTF-8
            $table->boolean('print_as_image')->default(false); // print the text as a picture when the printer has no suitable font
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index('station_id');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
