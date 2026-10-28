<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-11: the superadmin's changes to a platform role's default admin permissions (allowed = grant or take away),
     * App\Services\Admin\AdminPermissionService. The superadmin role itself has no rows: it is fixed (everything).
     */
    public function up(): void
    {
        Schema::create('platform_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('platform_role', 16);
            $table->string('permission', 64);
            $table->boolean('allowed');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform_role', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_role_permissions');
    }
};
