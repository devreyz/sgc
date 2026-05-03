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
        Schema::table('project_payments', function (Blueprint $table) {
            // Número sequencial do extrato de pagamento ao associado
            $table->string('receipt_number', 20)->nullable()->after('notes')
                ->comment('Número do extrato gerado para o associado');

            // Após finalizado, o pagamento é imutável
            $table->timestamp('finalized_at')->nullable()->after('receipt_number')
                ->comment('Data/hora em que o pagamento foi finalizado (imutável após isso)');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')
                ->constrained('users')->nullOnDelete()
                ->comment('Usuário que finalizou o pagamento');

            // Saldo em aberto quando for pagamento parcial
            $table->decimal('balance_remaining', 15, 2)->default(0)->after('amount')
                ->comment('Saldo ainda a pagar (0 = quitado)');
        });
    }

    public function down(): void
    {
        Schema::table('project_payments', function (Blueprint $table) {
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['receipt_number', 'finalized_at', 'finalized_by', 'balance_remaining']);
        });
    }
};
