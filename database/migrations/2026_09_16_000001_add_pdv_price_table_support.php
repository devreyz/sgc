<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_tables', function (Blueprint $table) {
            $table->boolean('is_pdv_default')->default(false)->after('active');
            $table->index(['tenant_id', 'active', 'is_pdv_default'], 'price_tables_pdv_default_idx');
        });
        Schema::table('pdv_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('price_table_id')->nullable()->after('pdv_customer_id')->index();
        });

        foreach (DB::table('price_tables')->where('active', true)->distinct()->pluck('tenant_id') as $tenantId) {
            $id = DB::table('price_tables')->where('tenant_id', $tenantId)->where('active', true)->orderBy('id')->value('id');
            DB::table('price_tables')->where('id', $id)->update(['is_pdv_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('pdv_sales', function (Blueprint $table) {
            $table->dropIndex(['price_table_id']);
            $table->dropColumn('price_table_id');
        });
        Schema::table('price_tables', function (Blueprint $table) {
            $table->dropIndex('price_tables_pdv_default_idx');
            $table->dropColumn('is_pdv_default');
        });
    }
};
