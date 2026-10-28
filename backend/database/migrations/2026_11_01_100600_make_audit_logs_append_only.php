<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-11: the platform's harakatlar jurnali cannot be changed. The model refuses updates and deletes
     * (App\Models\AuditLog); on PostgreSQL a trigger refuses them for any query too. The only change it lets through is
     * the ON DELETE SET NULL of a referenced user, store, branch or device (the entry stays, the link goes).
     * Admin entries also keep the client's user agent.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('user_agent', 255)->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'audit_logs is append-only: entries cannot be deleted';
                END IF;

                IF (to_jsonb(NEW) - ARRAY['user_id', 'store_id', 'branch_id', 'device_id'])
                        IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['user_id', 'store_id', 'branch_id', 'device_id'])
                    OR (NEW.user_id IS NOT NULL AND NEW.user_id IS DISTINCT FROM OLD.user_id)
                    OR (NEW.store_id IS NOT NULL AND NEW.store_id IS DISTINCT FROM OLD.store_id)
                    OR (NEW.branch_id IS NOT NULL AND NEW.branch_id IS DISTINCT FROM OLD.branch_id)
                    OR (NEW.device_id IS NOT NULL AND NEW.device_id IS DISTINCT FROM OLD.device_id) THEN
                    RAISE EXCEPTION 'audit_logs is append-only: entries cannot be changed';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs; DROP FUNCTION IF EXISTS audit_logs_append_only();');
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropColumn('user_agent');
        });
    }
};
