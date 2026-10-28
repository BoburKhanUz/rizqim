<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-22: store applications and the onboarding pipeline (Boshqaruv markazi → Ovqatxonalar → Ulash). An application
     * comes from the public form (POST /api/v1/store-applications: the website, later the bot) or is typed in by the
     * team, then moves through the stages ariza → tekshiruv → menyu → qurilmalar → o'qitish → sinov → faol (or rad
     * etildi), each with an assignee, a due date and a checklist (App\Enums\StoreApplicationStage). Converting it
     * creates the store through the same path as the Filament superadmin panel (StoreProvisioner) and links it here.
     */
    public function up(): void
    {
        Schema::create('store_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('format', 32); // App\Enums\StoreFormat
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('owner_name');
            $table->string('phone', 20); // +998 and nine digits
            $table->string('tin', 9)->nullable(); // STIR
            $table->text('comment')->nullable();
            $table->string('source', 16)->default('website'); // website | telegram | admin
            $table->string('stage', 16)->default('application'); // App\Enums\StoreApplicationStage
            $table->timestamp('stage_changed_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable(); // the current stage's deadline (Asia/Tashkent date)
            // {item_key: {done_at, by}} for the items ticked by hand; detected items are computed from the store
            $table->jsonb('checklist')->nullable();
            $table->foreignId('store_id')->nullable()->unique()->constrained('stores')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Public submissions only: where it came from, for abuse investigations
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['stage', 'due_on']);
            $table->index(['phone', 'created_at']);
            $table->index(['assignee_id', 'stage']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_applications');
    }
};
