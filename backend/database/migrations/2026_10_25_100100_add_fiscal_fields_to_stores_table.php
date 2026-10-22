<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-11: the store as a taxpayer: its TIN (STIR, 9 digits; printed on receipts, the committent on Payme/Click
     * receipts), its tax regime (vat 12%, vat_simplified 6%, turnover: no VAT; null until the owner chooses) and the
     * fiscal settings (App\Domain\Fiscal\FiscalSettings: enabled, driver, charge MXIK codes, sandbox behaviour).
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('tin', 9)->nullable();
            $table->string('tax_regime', 16)->nullable(); // App\Domain\Fiscal\TaxRegime
            $table->jsonb('fiscal_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['tin', 'tax_regime', 'fiscal_settings']);
        });
    }
};
