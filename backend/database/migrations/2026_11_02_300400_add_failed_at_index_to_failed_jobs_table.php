<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    private const INDEX = 'failed_jobs_failed_at_index';

    /**
     * P4-15: the failed jobs spike rule counts the last minutes' failures every minute, and the Yiqilgan vazifalar tab
     * counts the last day's.
     */
    public function up(): void
    {
        ConcurrentIndex::create(self::INDEX, 'failed_jobs', ['failed_at']);
    }

    public function down(): void
    {
        ConcurrentIndex::drop(self::INDEX, 'failed_jobs');
    }
};
