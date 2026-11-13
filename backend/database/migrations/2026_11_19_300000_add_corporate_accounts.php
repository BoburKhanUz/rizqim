<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-44 (D4): corporate accounts and talons. A customer account gains a kind (`customer`: nasiya as before;
     * `corporate`: a company whose staff eat on its account), the company's details, a monthly limit, whether a charge
     * at the till needs a manager's approval and the talon mode. Its members are the company's people: a scannable
     * code (the talon), a daily limit or meals per day. A charge entry records the member and its business day (the
     * day the limits count in).
     */
    public function up(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->string('kind', 16)->default('customer'); // customer | corporate
            $table->string('company_name', 160)->nullable();
            $table->string('inn', 14)->nullable(); // STIR (9 digits) or JShShIR (14)
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('invoice_email', 160)->nullable();
            // Corporate: what the company may eat on account in one month (business days); null: no monthly limit
            $table->decimal('monthly_limit', 12, 2)->nullable();
            // A charge at the till needs a manager's approval (nasiya: always; corporate: the owner's choice, off by default)
            $table->boolean('charge_approval')->default(true);
            $table->string('talon_mode', 16)->default('none'); // none | daily_limit | subscription

            $table->index(['store_id', 'kind']);
        });

        Schema::create('customer_account_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable(); // +998XXXXXXXXX
            $table->string('code', 32); // the talon: printed as a QR / scanned badge
            $table->decimal('daily_limit', 12, 2)->nullable(); // null: no daily sum limit
            $table->unsignedSmallInteger('meals_per_day')->nullable(); // subscription (abonement); null: no count limit
            $table->boolean('is_active')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->unique(['store_id', 'phone']); // a phone finds one member at the till (NULLs never collide)
            $table->index(['customer_account_id', 'is_active']);
        });

        Schema::table('customer_account_entries', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->constrained('customer_account_members')->nullOnDelete();
            // The branch's business day of a charge (and of its reversal): daily and monthly limits count by it
            $table->date('business_date')->nullable();

            $table->index(['member_id', 'business_date']);
            $table->index(['customer_account_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_account_entries', function (Blueprint $table) {
            $table->dropIndex(['member_id', 'business_date']);
            $table->dropIndex(['customer_account_id', 'business_date']);
            $table->dropConstrainedForeignId('member_id');
            $table->dropColumn('business_date');
        });
        Schema::dropIfExists('customer_account_members');
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'kind']);
            $table->dropColumn(['kind', 'company_name', 'inn', 'contact_name', 'contact_phone', 'invoice_email', 'monthly_limit', 'charge_approval', 'talon_mode']);
        });
    }
};
