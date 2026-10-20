<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Orders not settled yet still hold the old snapshot ((items + delivery) * rate, ignoring the discount).
     * Settlement now charges the snapshot, so align it with what settlement charged until now: total_price * rate.
     */
    public function up(): void
    {
        DB::update(
            'UPDATE orders SET commission_amount = ROUND(total_price * (SELECT stores.commission_rate FROM stores WHERE stores.id = orders.store_id) / 100, 2)
             WHERE status NOT IN (?, ?, ?, ?) AND deleted_at IS NULL',
            ['delivered', 'canceled', 'rejected', 'refunded']
        );
    }

    public function down(): void
    {
        // The previous snapshot was derived data with a known error; it is not restored.
    }
};
