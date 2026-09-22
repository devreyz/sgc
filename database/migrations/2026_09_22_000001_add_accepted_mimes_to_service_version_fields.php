<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_version_fields', function (Blueprint $table): void {
            $table->json('accepted_mime_types')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_version_fields', function (Blueprint $table): void {
            $table->dropColumn('accepted_mime_types');
        });
    }
};
