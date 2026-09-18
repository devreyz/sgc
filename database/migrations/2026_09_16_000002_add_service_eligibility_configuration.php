<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_versions', function (Blueprint $table): void {
            $table->boolean('members_only')->default(false)->after('allow_provider_create_order');
        });
    }

    public function down(): void
    {
        Schema::table('service_versions', function (Blueprint $table): void {
            $table->dropColumn('members_only');
        });
    }
};
