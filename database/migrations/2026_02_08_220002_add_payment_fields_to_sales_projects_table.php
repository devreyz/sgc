<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->date('delivered_date')->nullable()->after('end_date');
            $table->date('payment_received_date')->nullable()->after('delivered_date');
            $table->decimal('received_amount', 15, 2)->nullable()->after('total_value');
            $table->decimal('admin_fee_collected', 15, 2)->nullable()->after('received_amount');
            $table->decimal('associates_paid_amount', 15, 2)->nullable()->after('admin_fee_collected');
            $table->foreignId('payment_bank_account_id')->nullable()->after('customer_id')->constrained('bank_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropForeign(['payment_bank_account_id']);
            $table->dropColumn([
                'delivered_date',
                'payment_received_date',
                'received_amount',
                'admin_fee_collected',
                'associates_paid_amount',
                'payment_bank_account_id'
            ]);
        });
    }
};
