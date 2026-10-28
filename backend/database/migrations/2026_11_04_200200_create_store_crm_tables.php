<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-22: CRM-lite of the Ovqatxonalar section: notes and tasks about a store or an application (one of the two),
     * and the next contact date of a store. Tasks due today or earlier and contacts due today show in "Bugun → Diqqat
     * talab qiladi" (App\Services\Admin\Stores\Attention\DueStoreTasksSource).
     */
    public function up(): void
    {
        Schema::create('store_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('store_applications')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
            $table->index(['application_id', 'created_at']);
        });

        Schema::create('store_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('store_applications')->cascadeOnDelete();
            $table->string('title', 255);
            $table->date('due_on'); // Asia/Tashkent date
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['done_at', 'due_on']);
            $table->index(['store_id', 'done_at']);
            $table->index(['application_id', 'done_at']);
            $table->index(['assignee_id', 'done_at']);
        });

        Schema::create('store_crm_profiles', function (Blueprint $table) {
            $table->foreignId('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->date('next_contact_on')->nullable()->index(); // Asia/Tashkent date
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_crm_profiles');
        Schema::dropIfExists('store_tasks');
        Schema::dropIfExists('store_notes');
    }
};
