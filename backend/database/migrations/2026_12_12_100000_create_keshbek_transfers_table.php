<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner decision A (keshbek): a keshbek balance moved from one users row to another, with a record — today only
     * User::mergeInviteInto, the unclaimed invite row's balance (a debt too) handed to the account that accepted the
     * invite. Before this the balance stayed frozen on a retired row nobody can sign in to. Why a table of its own:
     * there is no keshbek ledger (docs/BILLING.md "Keshbek majburiyati"); store_transactions rows are per store and
     * order, and the move is neither; audit_logs is partitioned and its old months are archived and dropped
     * (OPERATIONS 20-bo'lim), while KeshbekLiabilityReport reads every movement of all time and the customer card shows
     * the move for as long as the balance stands.
     *
     * - from_user_id: the row the balance left; a plain number, no foreign key — the invite row is deleted in the
     *   same transaction, and its id is what the record is about;
     * - to_user_id: the account that took it; ON DELETE SET NULL (an account merge deletes only zero-balance rows,
     *   AccountLinkingService), the amount stays counted;
     * - amount: the balance moved, signed (a debt is negative), as users.balance (12, 2);
     * - moved_friend_ids: the friends whose referred_by moved with it (KeshbekHistory leaves out their earlier
     *   bonuses, which the invite row received and which are inside `amount`).
     *
     * Append-only (KeshbekTransfer refuses an update or a delete). No store_id: a platform row, not under row-level
     * security (TenantRlsInventoryTest lists only tables with a store_id); RevisionMap: NONE. A new, empty table: no
     * lock on any existing one beyond the foreign key's brief one on users. Touches no cache or Redis.
     */
    public function up(): void
    {
        Schema::create('keshbek_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id');
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 32);
            $table->jsonb('moved_friend_ids')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // KeshbekHistory: the account's transfers, newest first
            $table->index(['to_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keshbek_transfers');
    }
};
