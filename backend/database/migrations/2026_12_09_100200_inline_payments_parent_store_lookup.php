<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Third review L1: payments_parent_store(bigint, jsonb) (2026_11_27_100000, SECURITY DEFINER since 2026_11_28_100000 so
 * the payments_store_id trigger sees every order under row-level security) was callable by any role with EXECUTE —
 * PUBLIC by default, and scripts/db-roles.sh grants EXECUTE on every function of the schema to the application and
 * tenant roles on each run, so revoking it would not last. As the tenant role bound to one store it answered the store
 * of any other store's order or top-up. The lookup moves into the trigger function itself (SECURITY DEFINER, relations
 * schema-qualified, a fixed search_path, as the helper was), which PostgreSQL calls only as a trigger, and the helper
 * is dropped. Works the same with or without the split roles (it names none).
 *
 * No table lock: CREATE OR REPLACE FUNCTION and DROP FUNCTION lock only the functions; the triggers keep pointing at
 * payments_store_id(), whose new body no longer calls the helper by the time it is dropped. Touches no cache.
 * down() puts both back as 2026_11_28_100000 left them; it is safe while the application runs too (no table lock).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $schema = $this->schema();
        DB::unprepared(str_replace('{schema}', $schema, <<<'SQL'
            CREATE OR REPLACE FUNCTION {schema}.payments_store_id() RETURNS trigger LANGUAGE plpgsql
            SECURITY DEFINER SET search_path = pg_catalog, pg_temp AS $$
            DECLARE
                reference text;
                parent bigint;
            BEGIN
                IF NEW.order_id IS NOT NULL THEN
                    SELECT o.store_id INTO parent FROM {schema}.orders o WHERE o.id = NEW.order_id;
                ELSE
                    -- A store's Payme top-up (PaymeWebhookController::findTarget): the reference is checked before the
                    -- cast, so a malformed one resolves to NULL instead of failing the insert
                    reference := NEW.payload -> 'account' ->> 'order_id';
                    IF reference ~* '^topup_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' THEN
                        SELECT t.store_id INTO parent FROM {schema}.store_topup_requests t WHERE t.uuid = substr(reference, 7)::uuid;
                    END IF;
                END IF;
                IF parent IS NOT NULL AND NEW.store_id IS DISTINCT FROM parent THEN
                    IF NEW.store_id IS NOT NULL THEN
                        RAISE WARNING 'payments: store_id % replaced by % (the store of order %, payment %)', NEW.store_id, parent, NEW.order_id, NEW.id;
                    END IF;
                    NEW.store_id := parent;
                END IF;
                RETURN NEW;
            END
            $$;
        SQL));
        DB::statement("DROP FUNCTION IF EXISTS {$schema}.payments_parent_store(bigint, jsonb)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // The helper first (the trigger function's old body calls it), then the body that calls it
        DB::unprepared(str_replace('{schema}', $this->schema(), <<<'SQL'
            CREATE OR REPLACE FUNCTION {schema}.payments_parent_store(p_order_id bigint, p_payload jsonb) RETURNS bigint
            LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = pg_catalog, pg_temp AS $$
            DECLARE
                reference text;
                parent bigint;
            BEGIN
                IF p_order_id IS NOT NULL THEN
                    SELECT o.store_id INTO parent FROM {schema}.orders o WHERE o.id = p_order_id;
                    RETURN parent;
                END IF;
                reference := p_payload -> 'account' ->> 'order_id';
                IF reference ~* '^topup_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' THEN
                    SELECT t.store_id INTO parent FROM {schema}.store_topup_requests t WHERE t.uuid = substr(reference, 7)::uuid;
                END IF;
                RETURN parent;
            END
            $$;

            CREATE OR REPLACE FUNCTION {schema}.payments_store_id() RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER AS $$
            DECLARE
                parent bigint := {schema}.payments_parent_store(NEW.order_id, NEW.payload);
            BEGIN
                IF parent IS NOT NULL AND NEW.store_id IS DISTINCT FROM parent THEN
                    IF NEW.store_id IS NOT NULL THEN
                        RAISE WARNING 'payments: store_id % replaced by % (the store of order %, payment %)', NEW.store_id, parent, NEW.order_id, NEW.id;
                    END IF;
                    NEW.store_id := parent;
                END IF;
                RETURN NEW;
            END
            $$;
            ALTER FUNCTION {schema}.payments_store_id() RESET search_path;
        SQL));
    }

    private function schema(): string
    {
        return '"' . str_replace('"', '""', (string) DB::selectOne('SELECT current_schema() AS s')->s) . '"';
    }
};
