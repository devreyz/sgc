<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_layout_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('pdf_layout_templates', 'estimated_height_mm')) {
                // Estimated rendered height of the layout in mm.
                // Used by TemplatedPdfService to calculate the correct @page top/bottom margin
                // so fixed headers/footers never overlap the document content.
                // Default 20 mm covers a typical single-line branding header.
                // Set higher values (e.g. 28-32) for headers with logo + multiple info lines.
                $table->unsignedTinyInteger('estimated_height_mm')
                    ->default(20)
                    ->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdf_layout_templates', function (Blueprint $table) {
            $table->dropColumn('estimated_height_mm');
        });
    }
};
