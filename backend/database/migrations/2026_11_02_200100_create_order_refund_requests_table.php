<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-14: refund requests of marketplace orders (Boshqaruv markazi → Buyurtmalar). A team member with
     * admin.orders.refund_request asks (amount, reason), another member with admin.orders.refund_approve approves or
     * rejects (four eyes: never the requester). Approval executes the refund through the existing refund path
     * (App\Services\Admin\Orders\RefundRequestService) and records what it did in `execution`. One pending request per
     * order at a time (partial unique index on PostgreSQL).
     */
    public function up(): void
    {
        Schema::create('order_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->string('reason', 500);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            // What approval did: mode (order_refunded | manual), the order's status before and after, the part returned
            // automatically (keshbek), the part to return by hand and how (the order's payment method)
            $table->jsonb('execution')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX order_refund_requests_one_pending ON order_refund_requests (order_id) WHERE status = 'pending'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refund_requests');
    }
};
