<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_templates', 'show_recipient_signature')) {
            Schema::table('document_templates', function (Blueprint $table): void {
                $table->boolean('show_recipient_signature')
                    ->default(true)
                    ->after('consent_content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_templates', 'show_recipient_signature')) {
            Schema::table('document_templates', function (Blueprint $table): void {
                $table->dropColumn('show_recipient_signature');
            });
        }
    }
};
