<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('document_templates', 'color_theme')) {
                $table->string('color_theme', 20)->default('org')->after('paper_orientation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('color_theme');
        });
    }
};
