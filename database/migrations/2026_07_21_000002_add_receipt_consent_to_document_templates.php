<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `document_templates` MODIFY `system_template_key` VARCHAR(120) NULL');
        }

        Schema::table('document_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('document_templates', 'project_type')) {
                $table->string('project_type', 80)->nullable()->after('system_template_key');
            }

            if (! Schema::hasColumn('document_templates', 'consent_enabled')) {
                $table->boolean('consent_enabled')->default(true)->after('project_type');
            }

            if (! Schema::hasColumn('document_templates', 'consent_content')) {
                $table->longText('consent_content')->nullable()->after('consent_enabled');
            }

            if (! $this->hasIndex('document_templates', 'document_template_receipt_consent_idx')) {
                $table->index(
                    ['tenant_id', 'system_template_key', 'project_type', 'is_active'],
                    'document_template_receipt_consent_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropIndex('document_template_receipt_consent_idx');
            $table->dropColumn(['project_type', 'consent_enabled', 'consent_content']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `document_templates` MODIFY `system_template_key` VARCHAR(255) NULL');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
