<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('document_templates', 'consent_position')) {
                $table->string('consent_position', 16)->default('after')->after('consent_enabled');
            }

            if (! Schema::hasColumn('document_templates', 'consent_content_before')) {
                $table->longText('consent_content_before')->nullable()->after('consent_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('document_templates', 'consent_content_before')) {
                $table->dropColumn('consent_content_before');
            }

            if (Schema::hasColumn('document_templates', 'consent_position')) {
                $table->dropColumn('consent_position');
            }
        });
    }
};
