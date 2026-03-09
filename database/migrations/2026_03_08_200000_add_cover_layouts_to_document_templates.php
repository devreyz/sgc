<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('document_templates', 'cover_layout_id')) {
                $table->foreignId('cover_layout_id')
                    ->nullable()
                    ->after('footer_layout_id')
                    ->constrained('pdf_layout_templates')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('document_templates', 'back_cover_layout_id')) {
                $table->foreignId('back_cover_layout_id')
                    ->nullable()
                    ->after('cover_layout_id')
                    ->constrained('pdf_layout_templates')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_layout_id');
            $table->dropConstrainedForeignId('back_cover_layout_id');
        });
    }
};
