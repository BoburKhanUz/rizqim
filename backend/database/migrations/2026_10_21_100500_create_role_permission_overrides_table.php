<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A store's changes to the default role -> permission map (App\Services\Auth\RolePermissions): allowed = true
     * grants a permission the role lacks by default, false takes one away.
     */
    public function up(): void
    {
        Schema::create('role_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('role', 32);
            $table->string('permission', 64);
            $table->boolean('allowed');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'role', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_overrides');
    }
};
