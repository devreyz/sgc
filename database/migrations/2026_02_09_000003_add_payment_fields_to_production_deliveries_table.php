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
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->boolean('paid')->default(false)->after('approved_at')->comment('Se foi pago ao associado');
            $table->date('paid_date')->nullable()->after('paid')->comment('Data do pagamento');
            $table->foreignId('project_payment_id')->nullable()->after('paid_date')->constrained('project_payments')->nullOnDelete()->comment('Referência ao pagamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropForeign(['project_payment_id']);
            $table->dropColumn(['paid', 'paid_date', 'project_payment_id']);
        });
    }
};
