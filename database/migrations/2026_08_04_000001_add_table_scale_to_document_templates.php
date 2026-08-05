<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_templates', 'table_scale')) {
            Schema::table('document_templates', function (Blueprint $table): void {
                $table->unsignedTinyInteger('table_scale')->default(100)->after('paper_orientation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_templates', 'table_scale')) {
            Schema::table('document_templates', fn (Blueprint $table) => $table->dropColumn('table_scale'));
        }
    }
};
