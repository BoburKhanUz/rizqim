<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit entries get the store/branch they belong to (owners read their own store's log), the terminal that acted,
     * the client IP and a before/after diff of safe fields (App\Services\Audit\AuditLogger). model_type/model_id stay
     * the subject and payload_json the extra context (reason etc.), so older entries keep their meaning.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();

            $table->index(['store_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropConstrainedForeignId('store_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn(['before', 'after', 'ip']);
        });
    }
};
