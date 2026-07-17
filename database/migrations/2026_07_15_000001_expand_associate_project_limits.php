<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_associates', function (Blueprint $table) {
            $table->decimal('financial_limit', 14, 2)->nullable()->after('associate_id');
            $table->string('status', 20)->default('active')->after('financial_limit');
            $table->text('notes')->nullable()->after('status');
            $table->date('valid_from')->nullable()->after('notes');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->foreignId('created_by')->nullable()->after('valid_until')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'sales_project_id', 'status'], 'pa_tenant_project_status_idx');
        });

        Schema::table('project_associate_product_limits', function (Blueprint $table) {
            $table->decimal('reference_unit_price', 14, 4)->nullable()->after('max_quantity');
            $table->string('status', 20)->default('active')->after('reference_unit_price');
            $table->text('notes')->nullable()->after('status');
            $table->timestamp('archived_at')->nullable()->after('notes');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason')->nullable()->after('archived_by');
            $table->foreignId('created_by')->nullable()->after('archive_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'sales_project_id', 'associate_id', 'status'], 'papl_project_assoc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_associate_product_limits', function (Blueprint $table) {
            $table->dropIndex('papl_project_assoc_status_idx');
            $table->dropForeign(['archived_by']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'reference_unit_price', 'status', 'notes', 'archived_at', 'archived_by',
                'archive_reason', 'created_by', 'updated_by',
            ]);
        });

        Schema::table('project_associates', function (Blueprint $table) {
            $table->dropIndex('pa_tenant_project_status_idx');
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'financial_limit', 'status', 'notes', 'valid_from', 'valid_until',
                'created_by', 'updated_by',
            ]);
        });
    }
};
