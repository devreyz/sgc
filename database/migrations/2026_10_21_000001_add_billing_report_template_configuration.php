<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('sales_project_id')->nullable()->index('dt_report_project_idx');
            $table->unsignedBigInteger('customer_id')->nullable()->index('dt_report_customer_idx');
            $table->unsignedBigInteger('organization_id')->nullable()->index('dt_report_org_idx');
            $table->json('report_contents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropIndex('dt_report_project_idx');
            $table->dropIndex('dt_report_customer_idx');
            $table->dropIndex('dt_report_org_idx');
            $table->dropColumn(['sales_project_id', 'customer_id', 'organization_id', 'report_contents']);
        });
    }
};
