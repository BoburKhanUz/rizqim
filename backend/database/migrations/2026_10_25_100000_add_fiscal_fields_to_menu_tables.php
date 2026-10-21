<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-11: what a fiscal receipt line needs from the menu (VM 943, 1-ilova, 7-band): the MXIK (IKPU) code, the
     * package (qadoq) code, the VAT rate and the unit as the catalogue names it. A product is a dish or a service
     * (cooking service, kazan rent); both need an MXIK code before a fiscal store may sell them (FiscalReadiness).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('kind', 16)->default('dish'); // App\Enums\ProductKind: dish | service
            $table->string('mxik_code', 17)->nullable(); // 17 digits (tasnif.soliq.uz)
            $table->string('package_code', 20)->nullable(); // digits
            $table->decimal('vat_rate', 5, 2)->nullable(); // null: the store's tax regime decides
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('unit_label', 64)->nullable(); // the receipt's unit, e.g. "kg (guruch)"; null: the unit's own
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('unit_label');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['kind', 'mxik_code', 'package_code', 'vat_rate']);
        });
    }
};
