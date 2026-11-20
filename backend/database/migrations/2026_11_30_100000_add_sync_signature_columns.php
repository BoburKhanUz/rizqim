<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave M5 S3 (docs/scale/M5_DESIGN.md §4): the signed offline queue (App\Domain\Pos\Sync\SyncSignature).
 *  - devices: the till's signing key (`sync_key`, base64 of 32 bytes, Laravel-encrypted: text), its number
 *    (`sync_key_id`, 0: no key yet), the key before the last rotation (`sync_key_prev`, encrypted) and the head of the
 *    chain of signed operations the server holds (`sync_chain`: `kid:mac`);
 *  - pos_sync_operations: what the signature of each operation was worth (`sig_status`: ok, missing, bad, chain_gap;
 *    null on rows from before this migration) and the mac it came with.
 * Nullable columns and one constant default: no table rewrite, safe on a live database. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->text('sync_key')->nullable();
            $table->unsignedInteger('sync_key_id')->default(0);
            $table->text('sync_key_prev')->nullable();
            $table->string('sync_chain', 80)->nullable();
        });

        Schema::table('pos_sync_operations', function (Blueprint $table) {
            $table->string('sig_status', 16)->nullable();
            $table->char('mac', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sync_operations', function (Blueprint $table) {
            $table->dropColumn(['sig_status', 'mac']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['sync_key', 'sync_key_id', 'sync_key_prev', 'sync_chain']);
        });
    }
};
