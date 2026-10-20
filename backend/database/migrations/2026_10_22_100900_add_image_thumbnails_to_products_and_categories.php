<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu images may live on another disk (S3-compatible) than the old public one, and get small/medium thumbnails
     * made by a queued job ({"sm": path, "md": path}).
     */
    public function up(): void
    {
        foreach (['products', 'categories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('image_disk', 32)->nullable(); // null: the public disk, as before
                $table->jsonb('image_thumbs')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['image_disk', 'image_thumbs']);
            });
        }
    }
};
