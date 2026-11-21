<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_negotiations', function (Blueprint $table): void {
            $table->uuid('operation_key')->nullable();
            $table->unique(['tenant_id', 'operation_key'], 'svc_negotiation_operation_uq');
        });
    }

    public function down(): void
    {
        Schema::table('service_negotiations', function (Blueprint $table): void {
            $table->dropUnique('svc_negotiation_operation_uq');
            $table->dropColumn('operation_key');
        });
    }
};
