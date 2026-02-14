<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_payments', function (Blueprint $table) {
            $table->enum('type', ['client', 'provider'])->default('client')
                ->after('service_order_id')
                ->comment('Tipo: pagamento do cliente ou pagamento ao prestador');
            $table->string('receipt_path')->nullable()
                ->after('notes')
                ->comment('Comprovante do pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_payments', function (Blueprint $table) {
            $table->dropColumn(['type', 'receipt_path']);
        });
    }
};
