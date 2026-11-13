<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-41: a branch's QR payment settings at the till (App\Domain\Pos\QrPayments\QrPaymentSettings): the providers the
 * cashier may offer, the one offered first and how long a QR stays valid. No row: the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_qr_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('providers');
            $table->string('default_provider', 16)->nullable();
            $table->unsignedSmallInteger('expiry_minutes')->default(5);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_qr_settings');
    }
};
