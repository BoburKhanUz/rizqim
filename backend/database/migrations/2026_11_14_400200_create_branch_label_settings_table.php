<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-32 (B7): a branch's sticker and label settings, one row per branch (none: the defaults). The label size
     * (`size`, App\Domain\Pos\Labels\LabelSize), which items get a cup sticker (`scope`, LabelScope: all, or only the
     * dishes of `category_ids`), one sticker per piece or one per line, and whether a takeaway order also gets a bag
     * label. Read by the till through GET /pos/bootstrap `labels` while the branch has the `labels` module.
     */
    public function up(): void
    {
        Schema::create('branch_label_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('size', 8)->default('40x30');
            $table->string('scope', 16)->default('all');
            $table->jsonb('category_ids')->nullable(); // category ids when scope is "categories"
            $table->boolean('per_unit')->default(true); // 3 cups on a line = 3 stickers
            $table->boolean('bag_label')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_label_settings');
    }
};
