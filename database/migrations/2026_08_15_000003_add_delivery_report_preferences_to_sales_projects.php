<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_projects', 'delivery_report_preferences')) {
            Schema::table('sales_projects', function (Blueprint $table): void {
                $table->json('delivery_report_preferences')->nullable()->after('associate_receipt_table_scale');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_projects', 'delivery_report_preferences')) {
            Schema::table('sales_projects', function (Blueprint $table): void {
                $table->dropColumn('delivery_report_preferences');
            });
        }
    }
};
