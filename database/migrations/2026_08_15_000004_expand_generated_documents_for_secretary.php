<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('generated_documents', 'status')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                $table->string('status', 30)->default('draft')->after('content');
            });
        }
        if (! Schema::hasColumn('generated_documents', 'document_settings')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                $table->json('document_settings')->nullable()->after('variables_used');
            });
        }
        if (! Schema::hasColumn('generated_documents', 'last_edited_by')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                $table->foreignId('last_edited_by')->nullable()->after('generated_by')->index();
            });
        }

        if (DB::getDriverName() === 'mysql' && ! $this->hasEditorForeignKey()) {
            DB::statement('ALTER TABLE `generated_documents` ADD CONSTRAINT `generated_documents_last_edited_by_foreign` FOREIGN KEY (`last_edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && $this->hasEditorForeignKey()) {
            DB::statement('ALTER TABLE `generated_documents` DROP FOREIGN KEY `generated_documents_last_edited_by_foreign`');
        }
        Schema::table('generated_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('generated_documents', 'last_edited_by')) {
                $table->dropColumn('last_edited_by');
            }
            foreach (['document_settings', 'status'] as $column) {
                if (Schema::hasColumn('generated_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasEditorForeignKey(): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'generated_documents')
            ->where('COLUMN_NAME', 'last_edited_by')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
