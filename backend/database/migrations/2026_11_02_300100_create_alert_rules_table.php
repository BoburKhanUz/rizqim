<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-15: Monitoring → Qoidalar. One row per built-in rule kind (App\Services\Admin\Monitoring\AlertKind), created
     * with its defaults the first time the evaluator or the rules endpoint needs it. `threshold` and
     * `threshold_secondary` mean what the kind says (a count, minutes, hours...), `window_minutes` is the look-back
     * of the kinds that count events.
     */
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->unique();
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('threshold_secondary')->nullable();
            $table->unsignedInteger('window_minutes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('telegram')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
