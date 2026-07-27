<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('document_templates', 'show_representative_signature')) {
                $table->boolean('show_representative_signature')->default(true)->after('consent_content');
            }
        });

        Schema::table('project_fees', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_fees', 'receipt_column_name')) {
                $table->string('receipt_column_name', 40)->nullable()->after('name');
            }
        });

        Schema::table('customer_project_fees', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_project_fees', 'receipt_column_name')) {
                $table->string('receipt_column_name', 40)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('document_templates', 'show_representative_signature')) {
                $table->dropColumn('show_representative_signature');
            }
        });

        Schema::table('project_fees', function (Blueprint $table): void {
            if (Schema::hasColumn('project_fees', 'receipt_column_name')) {
                $table->dropColumn('receipt_column_name');
            }
        });

        Schema::table('customer_project_fees', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_project_fees', 'receipt_column_name')) {
                $table->dropColumn('receipt_column_name');
            }
        });
    }
};
