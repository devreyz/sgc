<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL não suporta índices parciais (WHERE deleted_at IS NULL).
        // A constraint global conflita com registros soft-deleted.
        // A unicidade é garantida via validação na aplicação (whereNull('deleted_at')).
        $indexExists = collect(DB::select("SHOW INDEX FROM customers WHERE Key_name = 'customers_cnpj_unique'"))->isNotEmpty();

        if ($indexExists) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('customers_cnpj_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = collect(DB::select("SHOW INDEX FROM customers WHERE Key_name = 'customers_cnpj_unique'"))->isNotEmpty();

        if (!$indexExists) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique('cnpj');
            });
        }
    }
};
