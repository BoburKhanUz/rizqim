<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review P6 wave 2 (S1/C1): the tips given to the person from the branch's tills in the period ("Choy puli
     * kassadan berilgan": `wage` cash movements of category `tips`), taken off a line whose rule pays the `tips` part:
     * payable = gross − advances − tips_paid + adjustment. 0 on a line without the tips part.
     */
    public function up(): void
    {
        Schema::table('staff_pay_statement_lines', function (Blueprint $table) {
            $table->decimal('tips_paid', 14, 2)->default(0)->after('advances');
        });
    }

    public function down(): void
    {
        Schema::table('staff_pay_statement_lines', function (Blueprint $table) {
            $table->dropColumn('tips_paid');
        });
    }
};
