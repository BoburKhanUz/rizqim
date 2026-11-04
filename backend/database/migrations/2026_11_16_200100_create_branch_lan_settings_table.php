<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6 "Uskunalar va lokal tarmoq": the local bridge of a branch (docs/POS_SPEC.md §5.5, pos/packages/pos_lan/README.md).
 * One row per branch: the branch's LAN secret (32 random bytes, encrypted with APP_KEY; `key_id` its version, bumped by
 * the owner's rotation), the hub the owner chose (null: the printing till, else the first paired till), the port, and
 * the device the server last handed the hub's secret to (`issued_hub_device_id`) and the one before it
 * (`previous_hub_device_id`, `hub_changed_at`): relayed operations are accepted from the hub only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_lan_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hub_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->unsignedInteger('port')->default(18480);
            $table->unsignedInteger('key_id')->default(1);
            $table->text('secret'); // encrypted (model cast): base64 of 32 random bytes
            $table->timestamp('rotated_at')->nullable();
            $table->foreignId('rotated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_hub_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('previous_hub_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamp('hub_changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_lan_settings');
    }
};
