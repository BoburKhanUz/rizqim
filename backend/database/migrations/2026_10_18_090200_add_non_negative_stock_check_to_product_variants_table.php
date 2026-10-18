<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Each statement commits on its own, so ADD ... NOT VALID's brief exclusive lock is not held during VALIDATE
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Rows oversold by the old unconditional decrement are already unorderable; 0 keeps that meaning.
        DB::table('product_variants')->where('stock', '<', 0)->update(['stock' => 0]);

        $exists = DB::selectOne("SELECT 1 FROM pg_constraint WHERE conname = 'product_variants_stock_non_negative'");
        if (!$exists) {
            // NOT VALID: no table scan under the ACCESS EXCLUSIVE lock; new writes are checked from now on
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_non_negative CHECK (stock >= 0) NOT VALID');
        }

        // Scans existing rows under SHARE UPDATE EXCLUSIVE, which does not block reads or writes
        DB::statement('ALTER TABLE product_variants VALIDATE CONSTRAINT product_variants_stock_non_negative');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT IF EXISTS product_variants_stock_non_negative');
    }
};
